<?php
require_once __DIR__ . '/_bootstrap.php';

action_require_post();
require_director();

$from = post_str('delete_from');
$to = post_str('delete_to');

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
    flash('error', 'Valid delete date range is required.');
    action_redirect_back('modules/audit_logs/index.php');
}

if ($from > $to) {
    flash('error', 'Delete from date cannot be after delete to date.');
    action_redirect_back('modules/audit_logs/index.php');
}

$mysqli = db();
$stmt = $mysqli->prepare('DELETE FROM audit_logs WHERE DATE(created_at) BETWEEN ? AND ?');
$stmt->bind_param('ss', $from, $to);
$stmt->execute();
$deleted = (int) $stmt->affected_rows;
$stmt->close();

audit_log('audit_logs', 'purge', 'audit_logs', null, null, ['delete_from' => $from, 'delete_to' => $to, 'deleted_count' => $deleted]);
flash('success', 'Deleted ' . $deleted . ' audit log entries.');
action_redirect_back('modules/audit_logs/index.php');
