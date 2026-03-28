<?php
require_once __DIR__ . '/_bootstrap.php';

if (!auth_check()) {
    redirect('login.php');
}

audit_log('auth', 'logout');
auth_logout();
flash('success', 'You have been signed out.');
redirect('login.php');
