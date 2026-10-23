<?php
/** Authentication regressions run only inside the disposable baseline database. */
$authIds=[];$authEmails=[];
foreach (['STAFF','MANAGER','CUSTOMER'] as $authRole) {
    $authEmail='personnel-'.strtolower($authRole).'-'.bin2hex(random_bytes(5)).'@example.test';
    $pdo->prepare('INSERT INTO users (full_name,email,role,password_hash,is_active) VALUES (?,?,?,?,1)')->execute(['Password test '.$authRole,$authEmail,$authRole,password_hash('Original-password-42',PASSWORD_DEFAULT)]);
    $authIds[$authRole]=(int)$pdo->lastInsertId();$authEmails[$authRole]=$authEmail;
}
$authIp='192.0.2.81';
$authResult=personnel_password_login(strtoupper($authEmails['STAFF']),'Original-password-42',$authIp);
baseline_check($authResult['ok'] && $authResult['user_id']===$authIds['STAFF'] && $authResult['role']==='STAFF', 'Staff signs in with email and password, normalizing email');
baseline_check(personnel_password_login($authEmails['MANAGER'],'Original-password-42',$authIp)['ok'], 'Manager signs in with password');
$authWrong=personnel_password_login($authEmails['STAFF'],'Wrong-password-42',$authIp);
$authUnknown=personnel_password_login('unknown-'.bin2hex(random_bytes(5)).'@example.test','Wrong-password-42',$authIp);
baseline_check(!$authWrong['ok'] && !$authUnknown['ok'] && $authWrong['message']===$authUnknown['message'], 'Wrong and unknown credentials use the same rejection');
baseline_check(!personnel_password_login($authEmails['CUSTOMER'],'Original-password-42',$authIp)['ok'], 'Customer cannot use personnel password sign-in');
$pdo->exec('UPDATE users SET is_active=0 WHERE id='.$authIds['STAFF']);
baseline_check(!personnel_password_login($authEmails['STAFF'],'Original-password-42',$authIp)['ok'], 'Inactive personnel cannot sign in with correct password');
$pdo->exec('UPDATE users SET is_active=1,password_hash=NULL WHERE id='.$authIds['STAFF']);
baseline_check(!personnel_password_login($authEmails['STAFF'],'Original-password-42',$authIp)['ok'], 'Existing account without password cannot bypass setup');
baseline_check(personnel_password_save($authIds['STAFF'],'Manager-set-password-42',$authIds['MANAGER'])['ok'], 'Manager can set an existing staff password');
$authHash=$pdo->query('SELECT password_hash FROM users WHERE id='.$authIds['STAFF'])->fetchColumn();
baseline_check($authHash!=='Manager-set-password-42' && password_verify('Manager-set-password-42',$authHash), 'Personnel password is stored as a hash');
baseline_check(personnel_password_login($authEmails['STAFF'],'Manager-set-password-42',$authIp)['ok'], 'Manager-set password works without email verification');
baseline_check(!personnel_password_save($authIds['STAFF'],'short',$authIds['MANAGER'])['ok'] && !personnel_password_save($authIds['STAFF'],str_repeat('a',73),$authIds['MANAGER'])['ok'], 'Password length is validated on server');
baseline_check(!personnel_password_save($authIds['MANAGER'],'Unauthorized-password',$authIds['STAFF'])['ok'], 'Staff cannot reset another personnel account');
baseline_check(!personnel_password_save($authIds['STAFF'],'Unauthorized-password',$authIds['CUSTOMER'])['ok'], 'Customer cannot reset personnel password');
baseline_check(!personnel_password_save($authIds['STAFF'],'Unverified-password',$authIds['STAFF'])['ok'], 'Staff self-service requires verified email proof');
$_SESSION['personnel_password_proof']=['user_id'=>$authIds['STAFF'],'expires_at'=>time()-1];
baseline_check(!personnel_password_save($authIds['STAFF'],'Expired-proof-password',$authIds['STAFF'])['ok'], 'Expired password recovery proof is rejected');
$_SESSION['personnel_password_proof']=['user_id'=>$authIds['MANAGER'],'expires_at'=>time()+600];
baseline_check(!personnel_password_save($authIds['STAFF'],'Other-proof-password',$authIds['STAFF'])['ok'], 'Recovery proof is bound to the correct account');
$_SESSION['personnel_password_proof']=['user_id'=>$authIds['STAFF'],'expires_at'=>time()+600];
baseline_check(personnel_password_save($authIds['STAFF'],'Recovered-password-42',$authIds['STAFF'])['ok'] && !isset($_SESSION['personnel_password_proof']), 'Verified staff can choose password and proof is consumed');
baseline_check(personnel_password_login($authEmails['STAFF'],'Recovered-password-42',$authIp)['ok'] && !personnel_password_login($authEmails['STAFF'],'Manager-set-password-42',$authIp)['ok'], 'Reset password replaces the previous password');
// Fresh manager account key isolates the ten-attempt limit from earlier checks.
for ($authAttempt=0;$authAttempt<9;$authAttempt++) personnel_password_login($authEmails['MANAGER'],'Incorrect-password',$authIp);
$authLimited=personnel_password_login($authEmails['MANAGER'],'Original-password-42',$authIp);
baseline_check(!$authLimited['ok'] && str_contains($authLimited['message'],'Too many'), 'Account attempt limit blocks repeated guessing, including a later correct password');
$authFloodIp='192.0.2.82';
for ($authAttempt=0;$authAttempt<50;$authAttempt++) personnel_password_login('flood-'.$authAttempt.'@example.test','Incorrect-password',$authFloodIp);
$authLimited=personnel_password_login($authEmails['CUSTOMER'],'Original-password-42',$authFloodIp);
baseline_check(!$authLimited['ok'] && str_contains($authLimited['message'],'Too many'), 'IP attempt limit blocks guesses across different email addresses');
