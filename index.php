<?php
require_once __DIR__ . '/includes/functions.php';

if (auth_check()) {
    redirect('dashboard.php');
}

redirect('login.php');
