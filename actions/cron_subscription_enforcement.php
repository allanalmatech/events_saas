<?php
require_once __DIR__ . '/../includes/functions.php';

$token = get_str('token', '');
$expected = getenv('EVENTSAAS_CRON_TOKEN') ?: 'change-me';
if ($token === '' || !hash_equals($expected, $token)) {
    http_response_code(403);
    exit('Forbidden');
}

$stats = run_subscription_automation();

header('Content-Type: application/json');
echo json_encode([
    'status' => 'ok',
    'timestamp' => now_sql(),
    'stats' => $stats,
], JSON_PRETTY_PRINT);
