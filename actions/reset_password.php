<?php
require_once __DIR__ . '/_bootstrap.php';

action_require_post();

$token = post_str('token');
$password = (string) ($_POST['password'] ?? '');

if ($token === '' || strlen($password) < 8) {
    flash('error', 'Reset token and valid password are required.');
    redirect('reset_password.php');
}

$mysqli = db();
$stmt = $mysqli->prepare('SELECT id, user_type, user_id, email, expires_at, used_at FROM password_resets WHERE reset_token = ? LIMIT 1');
$stmt->bind_param('s', $token);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row || $row['used_at'] !== null || strtotime($row['expires_at']) < time()) {
    flash('error', 'Reset token is invalid or expired.');
    redirect('reset_password.php');
}

$hash = password_hash($password, PASSWORD_BCRYPT);
$userType = $row['user_type'];
$userId = (int) $row['user_id'];

if ($userType === 'director') {
    $up = $mysqli->prepare('UPDATE director_users SET password_hash = ?, updated_at = NOW() WHERE id = ?');
    $up->bind_param('si', $hash, $userId);
    $up->execute();
    $up->close();
} else {
    $up = $mysqli->prepare('UPDATE tenant_users SET password_hash = ?, updated_at = NOW() WHERE id = ?');
    $up->bind_param('si', $hash, $userId);
    $up->execute();
    $up->close();
}

$done = $mysqli->prepare('UPDATE password_resets SET used_at = NOW() WHERE id = ?');
$resetId = (int) $row['id'];
$done->bind_param('i', $resetId);
$done->execute();
$done->close();

audit_log('auth', 'password_reset_complete', 'password_resets', $resetId, null, ['user_type' => $userType, 'user_id' => $userId]);
flash('success', 'Password reset successful. Sign in now.');
redirect('login.php');
