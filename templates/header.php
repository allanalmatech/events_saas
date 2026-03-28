<?php
require_once __DIR__ . '/../includes/functions.php';

$saasName = platform_saas_name();
$pageTitle = isset($pageTitle) ? $pageTitle : $saasName;
$auth = auth_user();
$themeTokens = current_theme_tokens();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo e($pageTitle); ?> - <?php echo e($saasName); ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.2/css/all.min.css">
    <link rel="stylesheet" href="<?php echo e(app_url('assets/css/app.css')); ?>">
    <style>
        :root {
            --bg: <?php echo e($themeTokens['bg']); ?>;
            --surface: <?php echo e($themeTokens['surface']); ?>;
            --surface-soft: <?php echo e($themeTokens['surface_soft']); ?>;
            --surface-strong: <?php echo e($themeTokens['surface_strong']); ?>;
            --text: <?php echo e($themeTokens['text']); ?>;
            --muted: <?php echo e($themeTokens['muted']); ?>;
            --primary: <?php echo e($themeTokens['primary']); ?>;
            --primary-strong: <?php echo e($themeTokens['primary_strong']); ?>;
            --on-primary: <?php echo e($themeTokens['on_primary']); ?>;
            --danger: <?php echo e($themeTokens['danger']); ?>;
            --success: <?php echo e($themeTokens['success']); ?>;
            --outline: <?php echo e($themeTokens['outline']); ?>;
            --sidebar-bg: <?php echo e($themeTokens['sidebar_bg']); ?>;
            --sidebar-flyout-bg: <?php echo e($themeTokens['sidebar_flyout_bg']); ?>;
            --input-bg: <?php echo e($themeTokens['input_bg']); ?>;
        }
    </style>
</head>
<body>
