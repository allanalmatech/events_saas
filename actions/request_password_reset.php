<?php
require_once __DIR__ . '/_bootstrap.php';

action_require_post();

$roleType = post_str('role_type', 'tenant_user');
$email = post_str('email');

if ($email === '') {
    flash('error', 'Email is required.');
    redirect('forgot_password.php');
}

$mysqli = db();
$user = null;
$userId = 0;

if ($roleType === 'director') {
    $stmt = $mysqli->prepare('SELECT id, email FROM director_users WHERE email = ? LIMIT 1');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
} else {
    $stmt = $mysqli->prepare('SELECT id, email FROM tenant_users WHERE email = ? LIMIT 1');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

if (!$user) {
    flash('error', 'Account not found for this email.');
    redirect('forgot_password.php');
}

$userId = (int) $user['id'];
$token = bin2hex(random_bytes(24));
$expiresAt = date('Y-m-d H:i:s', strtotime('+30 minutes'));

$stmt = $mysqli->prepare('INSERT INTO password_resets (user_type, user_id, email, reset_token, expires_at, created_at) VALUES (?, ?, ?, ?, ?, NOW())');
$stmt->bind_param('sisss', $roleType, $userId, $email, $token, $expiresAt);
$stmt->execute();
$stmt->close();

audit_log('auth', 'password_reset_request', 'password_resets', $userId, null, ['user_type' => $roleType]);
flash('success', 'Reset token generated. Token: ' . $token . ' (valid 30 minutes).');
redirect('reset_password.php');
