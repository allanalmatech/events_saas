<?php
$configFile = __DIR__ . '/includes/config.php';
$lockFile = __DIR__ . '/storage/install.lock';

if (!file_exists($configFile) && !file_exists($lockFile)) {
    header('Location: setup.php');
    exit;
}

require_once __DIR__ . '/includes/functions.php';

if (auth_check()) {
    redirect('dashboard.php');
}

redirect('login.php');
