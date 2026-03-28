<?php
require_once __DIR__ . '/../includes/functions.php';

$token = get_str('token', '');
$expected = getenv('EVENTSAAS_CRON_TOKEN') ?: 'change-me';
if ($token === '' || !hash_equals($expected, $token)) {
    http_response_code(403);
    exit('Forbidden');
}

$mysqli = db();
$sent = 0;

$sql = 'SELECT ts.tenant_id, ts.id AS subscription_id, ts.expires_at, ts.outstanding_balance, t.business_name FROM tenant_subscriptions ts INNER JOIN tenants t ON t.id = ts.tenant_id WHERE ts.subscription_status IN ("trial","active","pending_payment","overdue")';
$res = $mysqli->query($sql);
$rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];

foreach ($rows as $row) {
    $expires = strtotime($row['expires_at']);
    $today = strtotime(date('Y-m-d'));
    $diffDays = (int) floor(($expires - $today) / 86400);

    $type = null;
    if ($diffDays === 7) {
        $type = 'before_due';
    } elseif ($diffDays === 0) {
        $type = 'due_date';
    } elseif ($diffDays < 0) {
        $type = 'after_due';
    }

    if ($type === null) {
        continue;
    }

    $tenantId = (int) $row['tenant_id'];
    $check = $mysqli->prepare('SELECT id FROM tenant_billing_reminders WHERE tenant_id = ? AND reminder_type = ? AND DATE(sent_at) = CURDATE() LIMIT 1');
    $check->bind_param('is', $tenantId, $type);
    $check->execute();
    $exists = $check->get_result()->fetch_assoc();
    $check->close();
    if ($exists) {
        continue;
    }

    $message = 'Subscription reminder for ' . $row['business_name'] . '. Due date: ' . $row['expires_at'] . '. Outstanding: ' . number_format((float) $row['outstanding_balance'], 2) . ' ' . APP_CURRENCY . '.';

    $ins = $mysqli->prepare('INSERT INTO tenant_billing_reminders (tenant_id, billing_invoice_id, reminder_type, channel_key, reminder_message, follow_up_state, sent_at) VALUES (?, NULL, ?, "in_app", ?, "pending", NOW())');
    $ins->bind_param('iss', $tenantId, $type, $message);
    $ins->execute();
    $ins->close();

    notify_user($tenantId, null, 'Subscription Reminder', $message, 'in_app', $type, 'tenant_subscriptions', (int) $row['subscription_id']);
    $sent++;
}

header('Content-Type: application/json');
echo json_encode([
    'status' => 'ok',
    'timestamp' => now_sql(),
    'reminders_sent' => $sent,
], JSON_PRETTY_PRINT);
