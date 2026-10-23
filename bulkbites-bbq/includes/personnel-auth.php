<?php
/** Password access for invited personnel; customer verification stays email-only. */
function personnel_password_valid(string $password): bool
{
    return strlen($password) >= 8 && strlen($password) <= 72;
}

/** Validate credentials without changing the caller's session. */
function personnel_password_login(string $email, string $password, string $ip): array
{
    $email = normalize_email($email);
    $failure = ['ok'=>false, 'message'=>'Email or password is incorrect. If you have not set a password, use Set or reset password.'];
    // Count both keys on every attempt; no account existence is disclosed.
    $accountAllowed = rate_limit_hit('personnel_password', hash('sha256', $email), 10, 900);
    $ipAllowed = rate_limit_hit('personnel_password_ip', $ip, 50, 900);
    if (!$accountAllowed || !$ipAllowed) return ['ok'=>false, 'message'=>'Too many sign-in attempts. Please try again in 15 minutes.'];
    if (!valid_login_email($email) || !personnel_password_valid($password)) return $failure;
    $stmt = db()->prepare('SELECT id,role,is_active,password_hash FROM users WHERE email=?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    // A fixed dummy hash keeps unknown accounts on the same password-check path.
    $hash = $user['password_hash'] ?? '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi';
    $verified = password_verify($password, $hash);
    if (!$user || !$verified || !(int)$user['is_active'] || !in_array($user['role'], ['STAFF','MANAGER'], true) || empty($user['password_hash'])) return $failure;
    if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
        db()->prepare('UPDATE users SET password_hash=? WHERE id=? AND password_hash=?')->execute([password_hash($password, PASSWORD_DEFAULT), $user['id'], $hash]);
    }
    return ['ok'=>true, 'user_id'=>(int)$user['id'], 'role'=>$user['role']];
}

/** Managers manage personnel passwords; email-verified personnel may set their own. */
function personnel_password_save(int $targetId, string $password, int $actorId): array
{
    if (!personnel_password_valid($password)) return ['ok'=>false, 'message'=>'Use a password between 8 and 72 characters.'];
    $pdo = db();
    $stmt = $pdo->prepare('SELECT id,role,is_active FROM users WHERE id IN (?,?)');
    $stmt->execute([$actorId,$targetId]);
    $users = [];
    foreach ($stmt->fetchAll() as $row) $users[(int)$row['id']] = $row;
    $actor = $users[$actorId] ?? null;
    $target = $users[$targetId] ?? null;
    $proof = $_SESSION['personnel_password_proof'] ?? [];
    $selfVerified = $targetId === $actorId && (int)($proof['user_id'] ?? 0) === $actorId && (int)($proof['expires_at'] ?? 0) > time();
    if (!$actor || !(int)$actor['is_active'] || !in_array($actor['role'], ['STAFF','MANAGER'], true)
        || (!$selfVerified && $actor['role'] !== 'MANAGER') || !$target || !in_array($target['role'], ['STAFF','MANAGER'], true)) {
        return ['ok'=>false, 'message'=>'You cannot change this account password.'];
    }
    $pdo->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([password_hash($password, PASSWORD_DEFAULT),$targetId]);
    if ($targetId === $actorId) unset($_SESSION['personnel_password_proof']);
    return ['ok'=>true, 'message'=>'Password saved. Sign in with your email and password next time.'];
}
