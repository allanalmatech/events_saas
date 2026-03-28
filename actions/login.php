<?php
require_once __DIR__ . '/_bootstrap.php';

action_require_post();

$email = post_str('email');
$password = (string) ($_POST['password'] ?? '');

if ($email === '' || $password === '') {
    flash('error', 'Email and password are required.');
    redirect('login.php');
}

$roleType = 'tenant_user';
$ok = false;

$mysqli = db();
$directorExists = false;
$tenantUserExists = false;

$directorLookup = $mysqli->prepare('SELECT id FROM director_users WHERE email = ? LIMIT 1');
if ($directorLookup) {
    $directorLookup->bind_param('s', $email);
    $directorLookup->execute();
    $directorExists = (bool) $directorLookup->get_result()->fetch_assoc();
    $directorLookup->close();
}

$tenantLookup = $mysqli->prepare('SELECT id FROM tenant_users WHERE email = ? LIMIT 1');
if ($tenantLookup) {
    $tenantLookup->bind_param('s', $email);
    $tenantLookup->execute();
    $tenantUserExists = (bool) $tenantLookup->get_result()->fetch_assoc();
    $tenantLookup->close();
}

if ($directorExists) {
    $roleType = 'director';
    $ok = auth_login_director($email, $password);
} elseif ($tenantUserExists) {
    $roleType = 'tenant_user';
    $ok = auth_login_tenant_user($email, $password);
} else {
    $ok = false;
}

if (!$ok) {
    if ($roleType === 'tenant_user') {
        $stmt = db()->prepare('SELECT u.account_status, t.account_status AS tenant_status FROM tenant_users u INNER JOIN tenants t ON t.id = u.tenant_id WHERE u.email = ? LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($row) {
                if ($row['tenant_status'] === 'pending') {
                    flash('error', 'Tenant account is pending Director approval.');
                    redirect('login.php');
                }
                if ($row['tenant_status'] === 'rejected') {
                    flash('error', 'Tenant account was rejected. Contact platform support.');
                    redirect('login.php');
                }
                if (in_array($row['tenant_status'], ['suspended', 'locked'], true)) {
                    flash('error', 'Tenant account is restricted (' . $row['tenant_status'] . '). Clear billing or contact support.');
                    redirect('login.php');
                }
                if (!in_array($row['account_status'], ['active'], true)) {
                    flash('error', 'User account is not active. Ask your super admin to activate it.');
                    redirect('login.php');
                }
            }
        }

        $tenantStmt = db()->prepare('SELECT id, business_name FROM tenants WHERE email = ? LIMIT 1');
        if ($tenantStmt) {
            $tenantStmt->bind_param('s', $email);
            $tenantStmt->execute();
            $tenant = $tenantStmt->get_result()->fetch_assoc();
            $tenantStmt->close();

            if ($tenant) {
                flash('error', 'That email belongs to the tenant business profile, not a login user. Use the owner/staff email created under this tenant.');
                redirect('login.php');
            }
        }
    } elseif ($roleType === 'director') {
        $directorStatusStmt = db()->prepare('SELECT status FROM director_users WHERE email = ? LIMIT 1');
        if ($directorStatusStmt) {
            $directorStatusStmt->bind_param('s', $email);
            $directorStatusStmt->execute();
            $directorRow = $directorStatusStmt->get_result()->fetch_assoc();
            $directorStatusStmt->close();
            if ($directorRow && ($directorRow['status'] ?? '') !== 'active') {
                flash('error', 'Director account is not active.');
                redirect('login.php');
            }
        }
    }

    flash('error', 'Invalid credentials. Confirm email and password.');
    redirect('login.php');
}

audit_log('auth', 'login', null, null, null, ['role_type' => $roleType]);
redirect('dashboard.php');
