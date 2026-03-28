<?php
require_once __DIR__ . '/_bootstrap.php';

action_require_post();
require_auth();

$actor = auth_user() ?: [];
$role = auth_role();
$userId = (int) ($actor['id'] ?? 0);

if ($userId <= 0) {
    flash('error', 'Session expired. Please sign in again.');
    redirect('login.php');
}

$fullName = post_str('full_name');
$email = post_str('email');
$newPassword = (string) ($_POST['new_password'] ?? '');
$croppedProfileImage = trim((string) ($_POST['cropped_profile_image'] ?? ''));

if ($fullName === '' || $email === '') {
    flash('error', 'Full name and email are required.');
    action_redirect_back('modules/profile/index.php');
}

if ($newPassword !== '' && strlen($newPassword) < 8) {
    flash('error', 'New password must be at least 8 characters.');
    action_redirect_back('modules/profile/index.php');
}

$mysqli = db();

/**
 * Save a base64 data URL as compressed JPG.
 */
function save_profile_image_from_data_url(string $dataUrl, int $tenantId, int $userId): string
{
    if (!preg_match('/^data:image\/(jpeg|jpg|png|webp);base64,/i', $dataUrl)) {
        throw new RuntimeException('Invalid image format.');
    }

    $parts = explode(',', $dataUrl, 2);
    if (count($parts) !== 2) {
        throw new RuntimeException('Invalid image payload.');
    }

    $binary = base64_decode($parts[1], true);
    if ($binary === false || strlen($binary) < 32) {
        throw new RuntimeException('Image payload is empty.');
    }
    if (strlen($binary) > 8 * 1024 * 1024) {
        throw new RuntimeException('Image is too large.');
    }

    $dir = __DIR__ . '/../storage/profile_images';
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException('Could not prepare profile image directory.');
    }

    $filename = 't' . $tenantId . '_u' . $userId . '_' . date('YmdHis') . '_' . substr(bin2hex(random_bytes(4)), 0, 8) . '.jpg';
    $path = $dir . '/' . $filename;

    $written = false;
    if (function_exists('imagecreatefromstring') && function_exists('imagejpeg')) {
        $img = @imagecreatefromstring($binary);
        if ($img !== false) {
            $written = imagejpeg($img, $path, 82);
            imagedestroy($img);
        }
    }

    if (!$written) {
        $written = file_put_contents($path, $binary) !== false;
    }

    if (!$written) {
        throw new RuntimeException('Could not save profile image.');
    }

    return 'storage/profile_images/' . $filename;
}

function save_director_profile_meta(int $directorId, string $profileImagePath): void
{
    $file = __DIR__ . '/../storage/director_profile_settings.json';
    $dir = dirname($file);
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException('Could not prepare director profile storage.');
    }

    $payload = [];
    if (file_exists($file)) {
        $raw = file_get_contents($file);
        $json = $raw ? json_decode($raw, true) : null;
        if (is_array($json)) {
            $payload = $json;
        }
    }

    $payload[(string) $directorId] = [
        'profile_image_path' => $profileImagePath,
        'updated_at' => now_sql(),
    ];

    if (file_put_contents($file, json_encode($payload, JSON_PRETTY_PRINT)) === false) {
        throw new RuntimeException('Could not save director profile image settings.');
    }
}

try {
    if ($role === 'director') {
        $profileImagePath = trim((string) ($actor['profile_image_path'] ?? director_profile_image_path($userId)));
        if ($croppedProfileImage !== '') {
            $profileImagePath = save_profile_image_from_data_url($croppedProfileImage, 0, $userId);
            save_director_profile_meta($userId, $profileImagePath);
        }

        if ($newPassword !== '') {
            $hash = password_hash($newPassword, PASSWORD_BCRYPT);
            $stmt = $mysqli->prepare('UPDATE director_users SET full_name = ?, email = ?, password_hash = ?, updated_at = NOW() WHERE id = ? LIMIT 1');
            $stmt->bind_param('sssi', $fullName, $email, $hash, $userId);
        } else {
            $stmt = $mysqli->prepare('UPDATE director_users SET full_name = ?, email = ?, updated_at = NOW() WHERE id = ? LIMIT 1');
            $stmt->bind_param('ssi', $fullName, $email, $userId);
        }

        $stmt->execute();
        $stmt->close();

        $_SESSION['auth_user']['name'] = $fullName;
        $_SESSION['auth_user']['email'] = $email;
        $_SESSION['auth_user']['profile_image_path'] = $profileImagePath;

        audit_log('profile', 'update', 'director_users', $userId, null, ['full_name' => $fullName, 'email' => $email]);
        flash('success', 'Profile updated.');
        action_redirect_back('modules/profile/index.php');
    }

    require_tenant_user();
    enforce_tenant_lock_for_write();

    $tenantId = (int) ($actor['tenant_id'] ?? 0);
    $phone = post_str('phone');
    $existingStmt = $mysqli->prepare('SELECT profile_image_path FROM tenant_users WHERE id = ? AND tenant_id = ? LIMIT 1');
    $existingStmt->bind_param('ii', $userId, $tenantId);
    $existingStmt->execute();
    $existing = $existingStmt->get_result()->fetch_assoc();
    $existingStmt->close();

    $profileImagePath = (string) ($existing['profile_image_path'] ?? '');
    if ($croppedProfileImage !== '') {
        $profileImagePath = save_profile_image_from_data_url($croppedProfileImage, $tenantId, $userId);
    }

    if ($newPassword !== '') {
        $hash = password_hash($newPassword, PASSWORD_BCRYPT);
        $stmt = $mysqli->prepare('UPDATE tenant_users SET full_name = ?, email = ?, phone = ?, profile_image_path = ?, password_hash = ?, updated_at = NOW() WHERE id = ? AND tenant_id = ? LIMIT 1');
        $stmt->bind_param('sssssii', $fullName, $email, $phone, $profileImagePath, $hash, $userId, $tenantId);
    } else {
        $stmt = $mysqli->prepare('UPDATE tenant_users SET full_name = ?, email = ?, phone = ?, profile_image_path = ?, updated_at = NOW() WHERE id = ? AND tenant_id = ? LIMIT 1');
        $stmt->bind_param('ssssii', $fullName, $email, $phone, $profileImagePath, $userId, $tenantId);
    }

    $stmt->execute();
    $stmt->close();

    $_SESSION['auth_user']['name'] = $fullName;
    $_SESSION['auth_user']['email'] = $email;
    $_SESSION['auth_user']['profile_image_path'] = $profileImagePath;

    audit_log('profile', 'update', 'tenant_users', $userId, null, ['full_name' => $fullName, 'email' => $email]);
    flash('success', 'Profile updated.');
} catch (Throwable $exception) {
    $message = $exception->getMessage();
    if (stripos($message, 'duplicate') !== false) {
        flash('error', 'That email is already in use.');
    } else {
        flash('error', 'Could not update profile: ' . $message);
    }
}

action_redirect_back('modules/profile/index.php');
