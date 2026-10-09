<?php
/**
 * OTP lifecycle: creation, delivery, verification, cooldown, attempt limits.
 *
 * Codes are 6 digits, stored ONLY as bcrypt hashes (password_hash), expire
 * after app.otp_expiry_minutes, allow app.otp_max_attempts wrong tries, and
 * enforce app.otp_resend_cooldown_seconds between requests plus hourly caps
 * per phone and per IP (rate_limits table).
 */

function otp_latest(string $phone): ?array
{
    $stmt = db()->prepare('SELECT * FROM otp_records WHERE phone = ? ORDER BY id DESC LIMIT 1');
    $stmt->execute([$phone]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Request (create or resend) an OTP for a phone number.
 *
 * @return array{ok: bool, message: string, retry_after: int}
 */
function otp_request(string $phone, string $ip): array
{
    $fail = function (string $message, int $retry = 0): array {
        return ['ok' => false, 'message' => $message, 'retry_after' => $retry];
    };

    // 1. Rate limits (per phone and per IP) — prevents OTP flooding
    if (!rate_limit_hit('otp_request', 'ph:' . $phone, (int) config('app.otp_request_max_per_hour', 5), 3600)) {
        return $fail('Too many code requests for this number. Please try again in about an hour.');
    }
    if (!rate_limit_hit('otp_request', 'ip:' . $ip, (int) config('app.otp_request_ip_max_per_hour', 10), 3600)) {
        return $fail('Too many code requests from this device. Please try again later.');
    }

    // 2. Resend cooldown
    $latest = otp_latest($phone);
    if ($latest && strtotime($latest['resend_after']) > time()) {
        $wait = strtotime($latest['resend_after']) - time();
        return $fail("Please wait {$wait} second(s) before requesting another code.", $wait);
    }

    // 3. Staff/Manager accounts must use the password login, not OTP
    $stmt = db()->prepare('SELECT role, is_active FROM users WHERE phone = ?');
    $stmt->execute([$phone]);
    $account = $stmt->fetch();
    if ($account && $account['role'] !== 'CUSTOMER') {
        return $fail('This number belongs to a staff account. Please use the Staff/Manager login.');
    }
    if ($account && !(int) $account['is_active']) {
        return $fail('This account is deactivated. Please contact the manager.');
    }

    // 4. Opportunistic cleanup of long-expired codes
    db()->exec("DELETE FROM otp_records WHERE expires_at < DATE_SUB(NOW(), INTERVAL 1 DAY)");

    // 5. Generate a 6-digit code and store only its hash
    $expiryMinutes = (int) config('app.otp_expiry_minutes', 5);
    $cooldown      = (int) config('app.otp_resend_cooldown_seconds', 60);
    $code          = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

    $ins = db()->prepare(
        'INSERT INTO otp_records (phone, purpose, otp_hash, attempts, max_attempts, expires_at, resend_after, request_ip)
         VALUES (?, "LOGIN", ?, 0, ?, ?, ?, ?)'
    );
    $ins->execute([
        $phone,
        password_hash($code, PASSWORD_DEFAULT),
        (int) config('app.otp_max_attempts', 3),
        date('Y-m-d H:i:s', time() + $expiryMinutes * 60),
        date('Y-m-d H:i:s', time() + $cooldown),
        $ip,
    ]);

    // 6. Deliver through the pluggable sender
    $sent = sms_sender()->send($phone, "BulkBites BBQ: your verification code is {$code}. Valid for {$expiryMinutes} minutes. Do not share it.");
    if (!$sent) {
        return $fail('Could not send the verification code right now. Please try again.');
    }

    // 7. Debug delivery: the code is written to logs/otp_debug.log (filesystem
    //    only — HTTP access to logs/ is denied by .htaccess). Reveal it on the
    //    verify screen ONLY for loopback clients: on a server reachable from the
    //    network (0.0.0.0), a remote caller could otherwise request a code for
    //    someone else's number and read it straight back from their own session —
    //    full account takeover. Test suites read codes from the log, never the screen.
    $isLoopback = in_array(client_ip(), ['127.0.0.1', '::1', '::ffff:127.0.0.1'], true);
    if (otp_debug_mode() && $isLoopback) {
        $_SESSION['debug_otp'] = ['code' => $code, 'expires_at' => time() + $expiryMinutes * 60];
    } else {
        unset($_SESSION['debug_otp']);
    }

    $message = 'Verification code sent to ' . mask_phone($phone) . '.';
    if (otp_debug_mode()) {
        $message .= $isLoopback
            ? ' (Debug mode: the code is also written to logs/otp_debug.log and shown on the next screen.)'
            : ' (Debug mode: the code is written to logs/otp_debug.log on the server only.)';
    }
    return ['ok' => true, 'message' => $message, 'retry_after' => $cooldown];
}

/**
 * Verify a submitted OTP code against the latest active code for the phone.
 *
 * @return array{ok: bool, message: string}
 */
function otp_verify(string $phone, string $code): array
{
    $fail = function (string $message): array {
        return ['ok' => false, 'message' => $message];
    };

    // Rate limit brute-force attempts per phone
    if (!rate_limit_hit('otp_verify', 'ph:' . $phone, (int) config('app.otp_verify_max_per_hour', 10), 3600)) {
        return $fail('Too many verification attempts. Please wait a while or request a new code.');
    }

    $row = otp_latest($phone);

    if (!$row || $row['consumed_at'] !== null) {
        return $fail('No active verification code. Please request a new one.');
    }
    if (strtotime($row['expires_at']) <= time()) {
        return $fail('That code has expired. Please request a new one.');
    }
    if ((int) $row['attempts'] >= (int) $row['max_attempts']) {
        db()->prepare('UPDATE otp_records SET consumed_at = NOW() WHERE id = ?')->execute([$row['id']]);
        return $fail('Too many wrong attempts for that code. Please request a new one.');
    }

    if (!password_verify($code, $row['otp_hash'])) {
        $attempts = (int) $row['attempts'] + 1;
        db()->prepare('UPDATE otp_records SET attempts = ? WHERE id = ?')->execute([$attempts, $row['id']]);
        $left = max(0, (int) $row['max_attempts'] - $attempts);
        return $fail("Incorrect verification code. {$left} attempt(s) remaining.");
    }

    // Success: single-use — mark consumed
    db()->prepare('UPDATE otp_records SET consumed_at = NOW(), attempts = ? WHERE id = ?')
        ->execute([(int) $row['attempts'] + 1, $row['id']]);

    return ['ok' => true, 'message' => 'Code verified.'];
}

/**
 * Complete an OTP login: find-or-create the CUSTOMER account, then create a
 * fresh authenticated session (session ID regenerated).
 *
 * @return array{ok: bool, message: string}
 */
function complete_otp_login(string $phone): array
{
    $pdo = db();

    $stmt = $pdo->prepare('SELECT id, role, is_active FROM users WHERE phone = ?');
    $stmt->execute([$phone]);
    $user = $stmt->fetch();

    if ($user) {
        if ($user['role'] !== 'CUSTOMER') {
            return ['ok' => false, 'message' => 'This number belongs to a staff account. Please use the Staff/Manager login.'];
        }
        if (!(int) $user['is_active']) {
            return ['ok' => false, 'message' => 'This account is deactivated. Please contact the manager.'];
        }
        $userId = (int) $user['id'];
    } else {
        // First login: create the customer account (OTP-only — no password).
        // Both INSERTs run in one transaction: a failure can never leave a
        // user row without its customers row (or vice versa).
        $ownTx = !$pdo->inTransaction();
        try {
            if ($ownTx) {
                $pdo->beginTransaction();
            }
            $pdo->prepare('INSERT INTO users (full_name, phone, role, password_hash, is_active) VALUES (?, ?, "CUSTOMER", NULL, 1)')
                ->execute(['Customer ' . substr($phone, -4), $phone]);
            $userId = (int) $pdo->lastInsertId();
            $pdo->prepare('INSERT INTO customers (user_id) VALUES (?)')->execute([$userId]);
            if ($ownTx) {
                $pdo->commit();
            }
        } catch (PDOException $ex) {
            if ($ownTx && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            // Unique-phone race: another request created it first — re-read it.
            $stmt->execute([$phone]);
            $user = $stmt->fetch();
            if (!$user || $user['role'] !== 'CUSTOMER' || !(int) $user['is_active']) {
                return ['ok' => false, 'message' => 'Could not create the customer account. Please try again.'];
            }
            $userId = (int) $user['id'];
        }
    }

    // Fresh session on privilege change (anti session-fixation)
    session_regenerate_id(true);
    $_SESSION['user_id']      = $userId;
    $_SESSION['role']         = 'CUSTOMER';
    $_SESSION['login_at']     = time();
    $_SESSION['last_activity'] = time();
    unset($_SESSION['csrf_token'], $_SESSION['human_token'], $_SESSION['debug_otp'],
          $_SESSION['otp_phone'], $_SESSION['otp_resend_after'], $_SESSION['otp_expires_at']);

    return ['ok' => true, 'message' => 'Logged in successfully.'];
}
