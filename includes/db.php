<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

function db(): mysqli
{
    static $connection = null;

    if ($connection instanceof mysqli) {
        return $connection;
    }

    $connection = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
    if ($connection->connect_errno) {
        throw new RuntimeException('Database connection failed: ' . $connection->connect_error);
    }
    if (!$connection->set_charset('utf8mb4')) {
        throw new RuntimeException('Could not set database charset.');
    }

    return $connection;
}

function db_try(): ?mysqli
{
    try {
        return db();
    } catch (Throwable $exception) {
        return null;
    }
}
