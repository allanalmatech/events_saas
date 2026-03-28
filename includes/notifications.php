<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function notify_user(?int $tenantId, ?int $userId, string $title, string $message, string $channel = 'in_app', ?string $type = null, ?string $relatedTable = null, ?int $relatedId = null): void
{
    $mysqli = db_try();
    if (!$mysqli) {
        return;
    }

    $stmt = $mysqli->prepare('INSERT INTO notifications (tenant_id, user_id, title, message, channel_key, reminder_type, related_table, related_id, is_read, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, NOW())');
    if (!$stmt) {
        return;
    }

    $stmt->bind_param('iisssssi', $tenantId, $userId, $title, $message, $channel, $type, $relatedTable, $relatedId);
    $stmt->execute();
    $stmt->close();
}
