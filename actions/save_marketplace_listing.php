<?php
require_once __DIR__ . '/_bootstrap.php';

action_require_post();
require_tenant_user();
require_permission('marketplace.create');
enforce_tenant_lock_for_write();
ensure_marketplace_social_tables();

$tenantId = (int) current_tenant_id();
enforceFeatureForCurrentTenant('has_marketplace', 'subscriptions');
$countRes = db()->query('SELECT COUNT(*) c FROM marketplace_catalogue WHERE tenant_id = ' . $tenantId . ' AND is_active = 1');
$currentCount = (int) (($countRes ? $countRes->fetch_assoc()['c'] : 0) ?? 0);
enforceLimitForCurrentTenant('max_marketplace_ads', $currentCount, 'marketplace');

$title = post_str('title');
$listingType = post_str('listing_type', 'service');
$description = post_str('description');
$availability = post_str('availability_status', 'Available');

if (!in_array($listingType, ['service', 'item'], true)) {
    $listingType = 'service';
}

if ($title === '') {
    flash('error', 'Listing title is required.');
    action_redirect_back('modules/marketplace/index.php');
}

$dir = __DIR__ . '/../storage/marketplace_ads';
if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
    flash('error', 'Could not prepare ad image directory.');
    action_redirect_back('modules/marketplace/index.php');
}

$storedPaths = [];
$extMap = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
    'image/gif' => 'gif',
];

if (isset($_FILES['listing_images']) && is_array($_FILES['listing_images']['name'] ?? null)) {
    $names = $_FILES['listing_images']['name'];
    $tmpNames = $_FILES['listing_images']['tmp_name'] ?? [];
    $errors = $_FILES['listing_images']['error'] ?? [];
    $sizes = $_FILES['listing_images']['size'] ?? [];

    $count = count($names);
    if ($count > 4) {
        flash('error', 'You can upload up to 4 images per ad.');
        action_redirect_back('modules/marketplace/index.php');
    }

    for ($i = 0; $i < $count; $i++) {
        $error = (int) ($errors[$i] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        if ($error !== UPLOAD_ERR_OK) {
            flash('error', 'Could not upload one of the ad images.');
            action_redirect_back('modules/marketplace/index.php');
        }

        $tmpName = (string) ($tmpNames[$i] ?? '');
        $size = (int) ($sizes[$i] ?? 0);
        if ($tmpName === '' || !is_uploaded_file($tmpName) || $size <= 0) {
            flash('error', 'Invalid uploaded ad image.');
            action_redirect_back('modules/marketplace/index.php');
        }
        if ($size > (8 * 1024 * 1024)) {
            flash('error', 'One image is too large. Maximum size is 8MB per image.');
            action_redirect_back('modules/marketplace/index.php');
        }

        $imageInfo = @getimagesize($tmpName);
        $mime = strtolower((string) ($imageInfo['mime'] ?? ''));
        if (!isset($extMap[$mime])) {
            flash('error', 'Unsupported image format. Use JPG, PNG, WEBP, or GIF.');
            action_redirect_back('modules/marketplace/index.php');
        }

        $filename = 't' . $tenantId . '_ad_' . date('YmdHis') . '_' . $i . '_' . substr(bin2hex(random_bytes(4)), 0, 8) . '.' . $extMap[$mime];
        $target = $dir . '/' . $filename;
        if (!move_uploaded_file($tmpName, $target)) {
            flash('error', 'Could not save uploaded ad image.');
            action_redirect_back('modules/marketplace/index.php');
        }

        $storedPaths[] = 'storage/marketplace_ads/' . $filename;
    }
}

if (count($storedPaths) > 4) {
    flash('error', 'You can upload up to 4 images per ad.');
    action_redirect_back('modules/marketplace/index.php');
}

$mediaPath = $storedPaths[0] ?? null;

$mysqli = db();
$stmt = $mysqli->prepare('INSERT INTO marketplace_catalogue (tenant_id, title, listing_type, description, availability_status, media_path, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, 1, NOW(), NOW())');
$stmt->bind_param('isssss', $tenantId, $title, $listingType, $description, $availability, $mediaPath);
$stmt->execute();
$id = (int) $stmt->insert_id;
$stmt->close();

if ($id > 0 && $storedPaths) {
    $imgStmt = $mysqli->prepare('INSERT INTO marketplace_listing_images (listing_id, tenant_id, image_path, sort_order, created_at) VALUES (?, ?, ?, ?, NOW())');
    if ($imgStmt) {
        foreach ($storedPaths as $idx => $path) {
            $sortOrder = $idx;
            $imgStmt->bind_param('iisi', $id, $tenantId, $path, $sortOrder);
            $imgStmt->execute();
        }
        $imgStmt->close();
    }
}

audit_log('marketplace', 'create', 'marketplace_catalogue', $id, null, ['title' => $title, 'image_count' => count($storedPaths)]);
flash('success', 'Marketplace listing published.');
action_redirect_back('modules/marketplace/index.php');
