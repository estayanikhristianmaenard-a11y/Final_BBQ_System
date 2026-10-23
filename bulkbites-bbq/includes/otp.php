<?php
/** Email one-time passwords: hashed storage, expiry, cooldown, and attempt limits. */
function normalize_email(string $email): string
{
    return strtolower(trim($email));
}

function valid_login_email(string $email): bool
{
    return strlen($email) <= 254 && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function mask_email(string $email): string
{
    [$name, $domain] = explode('@', $email, 2);
    return substr($name, 0, 1) . '***@' . $domain;
}

function otp_latest(string $email, string $purpose = 'CUSTOMER'): ?array
{
    $stmt = db()->prepare('SELECT * FROM email_otp_records WHERE email = ? AND purpose = ? ORDER BY id DESC LIMIT 1');
    $stmt->execute([$email, $purpose]);
    return $stmt->fetch() ?: null;
}

/** @return array{ok: bool, message: string, retry_after: int} */
function otp_request(string $email, string $ip, string $purpose = 'CUSTOMER'): array
{
    $email = normalize_email($email);
    $fail = static fn(string $message, int $retry = 0): array =>
        ['ok' => false, 'message' => $message, 'retry_after' => $retry];
    if (!in_array($purpose, ['CUSTOMER', 'STAFF'], true) || !valid_login_email($email)) {
        return $fail('Please enter a valid email address.');
    }
    if (!email_configured()) {
        return $fail('Email sign-in is not available yet. Please contact the store to set up email delivery.');
    }
    // Hash long addresses to fit the existing 64-character rate-limit identifier.
    if (!rate_limit_hit('email_otp_request', hash('sha256', $email), (int) config('app.otp_request_max_per_hour', 5), 3600)) {
        return $fail('Too many code requests for this email. Please try again in about an hour.');
    }
    if (!rate_limit_hit('email_otp_request', 'ip:' . $ip, (int) config('app.otp_request_ip_max_per_hour', 10), 3600)) {
        return $fail('Too many code requests from this device. Please try again later.');
    }

    $pdo = db();
    // Serialize requests for the same email even from different browsers.
    $lockName = 'email-otp:' . substr(hash('sha256', $email), 0, 54);
    $lock = $pdo->prepare('SELECT GET_LOCK(?, 2)');
    $lock->execute([$lockName]);
    if ((int) $lock->fetchColumn() !== 1) {
        return $fail('A code is already being sent. Please wait a moment and try again.');
    }
    try {
        $latest = otp_latest($email, $purpose);
        if ($latest && strtotime($latest['resend_after']) > time()) {
            $wait = strtotime($latest['resend_after']) - time();
            return $fail("Please wait {$wait} second(s) before requesting another code.", $wait);
        }
        $stmt = $pdo->prepare('SELECT role, is_active FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $account = $stmt->fetch();
        if ($purpose === 'STAFF' && (!$account || !in_array($account['role'], ['STAFF', 'MANAGER'], true))) {
            return $fail('This email is not an authorized staff or manager account. Ask a manager to add it.');
        }
        if ($purpose === 'CUSTOMER' && $account && $account['role'] !== 'CUSTOMER') {
            return $fail('Use Staff/Manager login for this email address.');
        }
        if ($account && !(int) $account['is_active']) {
            return $fail('Email sign-in is unavailable for this account. Please contact the manager.');
        }
        $pdo->exec('DELETE FROM email_otp_records WHERE expires_at < DATE_SUB(NOW(), INTERVAL 1 DAY)');
        $expiry = max(1, (int) config('app.otp_expiry_minutes', 5));
        $cooldown = max(1, (int) config('app.otp_resend_cooldown_seconds', 60));
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        // A failed resend keeps the previous code valid and creates no cooldown.
        $pdo->beginTransaction();
        $pdo->prepare('UPDATE email_otp_records SET consumed_at = NOW() WHERE email = ? AND purpose = ? AND consumed_at IS NULL')
            ->execute([$email, $purpose]);
        $pdo->prepare('INSERT INTO email_otp_records
            (email, purpose, otp_hash, max_attempts, expires_at, resend_after, request_ip)
            VALUES (?, ?, ?, ?, ?, ?, ?)')->execute([
                $email, $purpose, password_hash($code, PASSWORD_DEFAULT), max(1, (int) config('app.otp_max_attempts', 3)),
                date('Y-m-d H:i:s', time() + $expiry * 60), date('Y-m-d H:i:s', time() + $cooldown), $ip,
            ]);
        if (!send_login_email($email, $code, $expiry)) {
            $pdo->rollBack();
            return $fail('We could not email your code. Please try again or contact the store.');
        }
        $pdo->commit();
        unset($_SESSION['debug_otp']);
        return ['ok' => true, 'message' => 'Verification code sent to ' . mask_email($email) . '. Check your inbox or spam folder.',
            'retry_after' => $cooldown];
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('[BulkBites] Email code request failed.');
        return $fail('We could not send your code. Please try again.');
    } finally {
        $pdo->prepare('SELECT RELEASE_LOCK(?)')->execute([$lockName]);
    }
}

/** Validate and consume under a row lock to prevent simultaneous code reuse. */
function otp_verify(string $email, string $code, string $purpose = 'CUSTOMER'): array
{
    $email = normalize_email($email);
    unset($_SESSION['verified_otp_email']);
    $fail = static fn(string $message): array => ['ok' => false, 'message' => $message];
    if (!in_array($purpose, ['CUSTOMER', 'STAFF'], true) || !valid_login_email($email) || !preg_match('/^[0-9]{6}$/D', $code)) {
        return $fail('Please enter the 6-digit verification code.');
    }
    if (!rate_limit_hit('email_otp_verify', hash('sha256', $email), (int) config('app.otp_verify_max_per_hour', 10), 3600)) {
        return $fail('Too many verification attempts. Please try again later.');
    }
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT * FROM email_otp_records WHERE email = ? AND purpose = ? ORDER BY id DESC LIMIT 1 FOR UPDATE');
        $stmt->execute([$email, $purpose]);
        $row = $stmt->fetch();
        if (!$row || $row['consumed_at'] !== null) {
            return $fail('No active verification code. Please request a new one.');
        }
        if (strtotime($row['expires_at']) <= time()) {
            return $fail('That code has expired. Please request a new one.');
        }
        if ((int) $row['attempts'] >= (int) $row['max_attempts']) {
            return $fail('Too many wrong attempts. Please request a new code.');
        }
        $correct = password_verify($code, $row['otp_hash']);
        $attempts = (int) $row['attempts'] + 1;
        $consumed = $correct || $attempts >= (int) $row['max_attempts'];
        $pdo->prepare('UPDATE email_otp_records SET attempts = ?, consumed_at = ? WHERE id = ?')
            ->execute([$attempts, $consumed ? date('Y-m-d H:i:s') : null, $row['id']]);
        $pdo->commit();
        if (!$correct) {
            $left = max(0, (int) $row['max_attempts'] - $attempts);
            return $fail("Incorrect verification code. {$left} attempt(s) remaining.");
        }
        $_SESSION['verified_otp_email'] = ['email' => $email, 'purpose' => $purpose, 'expires_at' => time() + 60];
        return ['ok' => true, 'message' => 'Email verified.'];
    } finally {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
    }
}

/** Staff are invited by managers; only customer sign-in may create an account. */
function complete_otp_login(string $email, string $purpose = 'CUSTOMER'): array
{
    $email = normalize_email($email);
    $proof = $_SESSION['verified_otp_email'] ?? [];
    unset($_SESSION['verified_otp_email']);
    if (!in_array($purpose, ['CUSTOMER', 'STAFF'], true) || ($proof['email'] ?? '') !== $email
        || ($proof['purpose'] ?? '') !== $purpose || ($proof['expires_at'] ?? 0) <= time()) {
        return ['ok' => false, 'message' => 'Please verify your email code first.'];
    }
    $pdo = db();
    $stmt = $pdo->prepare('SELECT id, role, is_active FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if (!$user && $purpose === 'STAFF') {
        return ['ok' => false, 'message' => 'This email has not been added as a staff or manager account.'];
    }
    if (!$user) {
        try {
            $pdo->beginTransaction();
            $pdo->prepare('INSERT INTO users (full_name, email, phone, role, password_hash, is_active)
                VALUES (?, ?, NULL, "CUSTOMER", NULL, 1)')->execute(['Customer', $email]);
            $userId = (int) $pdo->lastInsertId();
            $pdo->prepare('INSERT INTO customers (user_id, email) VALUES (?, ?)')->execute([$userId, $email]);
            $pdo->commit();
        } catch (PDOException $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            // Another verified request may have created the same email first.
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            if (!$user) {
                return ['ok' => false, 'message' => 'Could not create the customer account. Please try again.'];
            }
        }
    }
    if ($user) {
        $allowedRoles = $purpose === 'STAFF' ? ['STAFF', 'MANAGER'] : ['CUSTOMER'];
        if (!in_array($user['role'], $allowedRoles, true) || !(int) $user['is_active']) {
            return ['ok' => false, 'message' => 'This account cannot use this sign-in page. Please contact the manager.'];
        }
        $userId = (int) $user['id'];
    }
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
    $_SESSION['role'] = $user['role'] ?? 'CUSTOMER';
    $_SESSION['login_at'] = time();
    $_SESSION['last_activity'] = time();
    unset($_SESSION['csrf_token'], $_SESSION['human_token'], $_SESSION['debug_otp'], $_SESSION['personnel_password_proof'],
        $_SESSION['otp_phone'], $_SESSION['otp_email'], $_SESSION['otp_purpose'], $_SESSION['otp_resend_after'], $_SESSION['otp_expires_at']);
    return ['ok' => true, 'message' => 'Logged in successfully.'];
}
