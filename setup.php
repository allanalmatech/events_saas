<?php
declare(strict_types=1);

$baseDir = __DIR__;
$migrationDir = $baseDir . DIRECTORY_SEPARATOR . 'migrations';
$includesDir = $baseDir . DIRECTORY_SEPARATOR . 'includes';
$storageDir = $baseDir . DIRECTORY_SEPARATOR . 'storage';
$lockFile = $storageDir . DIRECTORY_SEPARATOR . 'install.lock';

$messages = [];
$errors = [];
$isInstalled = file_exists($lockFile);

$defaults = [
    'db_host' => 'localhost',
    'db_port' => '3306',
    'db_name' => '',
    'db_user' => '',
    'db_password' => '',
    'app_url' => guessAppUrl(),
    'app_name' => 'EventSaaS',
    'timezone' => 'Africa/Nairobi',
    'currency' => 'UGX',
    'director_name' => 'System Director',
    'director_email' => '',
];

$input = $defaults;

$requirements = [
    'PHP 7.0+' => version_compare(PHP_VERSION, '7.0.0', '>='),
    'mysqli extension' => extension_loaded('mysqli'),
    'migrations folder exists' => is_dir($migrationDir),
    'includes folder writable' => is_dir($includesDir) && is_writable($includesDir),
    'project root writable for lock file' => is_writable($baseDir),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isInstalled) {
    foreach ($input as $key => $value) {
        if ($key === 'db_password') {
            continue;
        }
        if (isset($_POST[$key])) {
            $input[$key] = trim((string) $_POST[$key]);
        }
    }

    $input['db_password'] = isset($_POST['db_password']) ? (string) $_POST['db_password'] : '';
    $directorPassword = isset($_POST['director_password']) ? (string) $_POST['director_password'] : '';

    if ($input['db_name'] === '' || !preg_match('/^[A-Za-z0-9_]+$/', $input['db_name'])) {
        $errors[] = 'Database name is required and must contain only letters, numbers, and underscore.';
    }
    if ($input['db_user'] === '') {
        $errors[] = 'Database username is required.';
    }
    if ($input['director_email'] === '' || !filter_var($input['director_email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'A valid Director email is required.';
    }
    if (strlen($directorPassword) < 8) {
        $errors[] = 'Director password must be at least 8 characters.';
    }
    if (!in_array($input['timezone'], timezone_identifiers_list(), true)) {
        $errors[] = 'Invalid timezone selected.';
    }
    if ($input['currency'] === '') {
        $errors[] = 'Currency is required.';
    }
    if (!ctype_digit($input['db_port'])) {
        $errors[] = 'Database port must be numeric.';
    }

    foreach ($requirements as $label => $ok) {
        if (!$ok) {
            $errors[] = 'Requirement failed: ' . $label;
        }
    }

    if (empty($errors)) {
        $mysqli = @new mysqli(
            $input['db_host'],
            $input['db_user'],
            $input['db_password'],
            '',
            (int) $input['db_port']
        );

        if ($mysqli->connect_errno) {
            $errors[] = 'Database connection failed: ' . $mysqli->connect_error;
        } else {
            $dbName = $input['db_name'];
            if (!$mysqli->query('CREATE DATABASE IF NOT EXISTS `' . $mysqli->real_escape_string($dbName) . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci')) {
                $errors[] = 'Could not create database (or no permission): ' . $mysqli->error;
            } elseif (!$mysqli->select_db($dbName)) {
                $errors[] = 'Could not select database: ' . $mysqli->error;
            } elseif (!$mysqli->set_charset('utf8mb4')) {
                $errors[] = 'Could not set database charset: ' . $mysqli->error;
            }

            if (empty($errors)) {
                try {
                    runMigrations($mysqli, $migrationDir, $messages);
                    createDirectorUser($mysqli, $input['director_name'], $input['director_email'], $directorPassword);
                    writeConfigFiles($includesDir, $input);
                    writeLockFile($storageDir, $lockFile, $input['app_url']);
                    $messages[] = 'Installation completed successfully. Remove setup.php or keep lock file protected.';
                    $isInstalled = true;
                } catch (Exception $exception) {
                    $errors[] = $exception->getMessage();
                }
            }

            $mysqli->close();
        }
    }
}

function guessAppUrl(): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
    $script = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '/setup.php';
    return rtrim($scheme . '://' . $host . str_replace('setup.php', '', $script), '/');
}

function runMigrations(mysqli $mysqli, string $migrationDir, array &$messages): void
{
    $files = glob($migrationDir . DIRECTORY_SEPARATOR . '*.sql');
    if (!$files) {
        throw new RuntimeException('No SQL migration files found in migrations directory.');
    }

    sort($files, SORT_NATURAL);

    foreach ($files as $filePath) {
        $migrationName = basename($filePath);
        if (isMigrationApplied($mysqli, $migrationName)) {
            $messages[] = 'Skipped already applied migration: ' . $migrationName;
            continue;
        }

        $sql = file_get_contents($filePath);
        if ($sql === false || trim($sql) === '') {
            throw new RuntimeException('Migration file is empty or unreadable: ' . $migrationName);
        }

        if (!$mysqli->multi_query($sql)) {
            throw new RuntimeException('Migration failed (' . $migrationName . '): ' . $mysqli->error);
        }

        do {
            if ($result = $mysqli->store_result()) {
                $result->free();
            }
        } while ($mysqli->more_results() && $mysqli->next_result());

        if ($mysqli->errno) {
            throw new RuntimeException('Migration execution error (' . $migrationName . '): ' . $mysqli->error);
        }

        if (tableExists($mysqli, 'migrations')) {
            $stmt = $mysqli->prepare('INSERT INTO migrations (migration_name) VALUES (?)');
            if ($stmt) {
                $stmt->bind_param('s', $migrationName);
                $stmt->execute();
                $stmt->close();
            }
        }

        $messages[] = 'Applied migration: ' . $migrationName;
    }
}

function tableExists(mysqli $mysqli, string $tableName): bool
{
    $safeTable = $mysqli->real_escape_string($tableName);
    $result = $mysqli->query("SHOW TABLES LIKE '{$safeTable}'");
    if (!$result) {
        return false;
    }
    $exists = $result->num_rows > 0;
    $result->free();
    return $exists;
}

function isMigrationApplied(mysqli $mysqli, string $migrationName): bool
{
    if (!tableExists($mysqli, 'migrations')) {
        return false;
    }
    $stmt = $mysqli->prepare('SELECT id FROM migrations WHERE migration_name = ? LIMIT 1');
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('s', $migrationName);
    $stmt->execute();
    $stmt->store_result();
    $applied = $stmt->num_rows > 0;
    $stmt->close();
    return $applied;
}

function createDirectorUser(mysqli $mysqli, string $name, string $email, string $plainPassword): void
{
    $passwordHash = password_hash($plainPassword, PASSWORD_BCRYPT);
    if ($passwordHash === false) {
        throw new RuntimeException('Could not hash director password.');
    }

    $stmt = $mysqli->prepare('SELECT id FROM director_users WHERE email = ? LIMIT 1');
    if (!$stmt) {
        throw new RuntimeException('Could not prepare director lookup statement.');
    }
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $stmt->store_result();
    $exists = $stmt->num_rows > 0;
    $stmt->close();

    if ($exists) {
        throw new RuntimeException('Director email already exists. Use a different email.');
    }

    $stmt = $mysqli->prepare('INSERT INTO director_users (full_name, email, password_hash, status) VALUES (?, ?, ?, "active")');
    if (!$stmt) {
        throw new RuntimeException('Could not prepare director insert statement.');
    }
    $stmt->bind_param('sss', $name, $email, $passwordHash);
    if (!$stmt->execute()) {
        $stmt->close();
        throw new RuntimeException('Could not create director account: ' . $mysqli->error);
    }
    $stmt->close();
}

function writeConfigFiles(string $includesDir, array $input): void
{
    $configPath = $includesDir . DIRECTORY_SEPARATOR . 'config.php';
    $dbPath = $includesDir . DIRECTORY_SEPARATOR . 'db.php';

    $configContent = "<?php\n" .
        "declare(strict_types=1);\n\n" .
        "define('APP_NAME', " . var_export($input['app_name'], true) . ");\n" .
        "define('APP_URL', " . var_export(rtrim($input['app_url'], '/'), true) . ");\n" .
        "define('APP_TIMEZONE', " . var_export($input['timezone'], true) . ");\n" .
        "define('APP_CURRENCY', " . var_export(strtoupper($input['currency']), true) . ");\n\n" .
        "define('DB_HOST', " . var_export($input['db_host'], true) . ");\n" .
        "define('DB_PORT', " . (int) $input['db_port'] . ");\n" .
        "define('DB_NAME', " . var_export($input['db_name'], true) . ");\n" .
        "define('DB_USER', " . var_export($input['db_user'], true) . ");\n" .
        "define('DB_PASS', " . var_export($input['db_password'], true) . ");\n\n" .
        "date_default_timezone_set(APP_TIMEZONE);\n";

    $dbContent = "<?php\n" .
        "declare(strict_types=1);\n\n" .
        "require_once __DIR__ . '/config.php';\n\n" .
        "function db(): mysqli\n" .
        "{\n" .
        "    static \$connection = null;\n\n" .
        "    if (\$connection instanceof mysqli) {\n" .
        "        return \$connection;\n" .
        "    }\n\n" .
        "    \$connection = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);\n" .
        "    if (\$connection->connect_errno) {\n" .
        "        throw new RuntimeException('Database connection failed: ' . \$connection->connect_error);\n" .
        "    }\n" .
        "    if (!\$connection->set_charset('utf8mb4')) {\n" .
        "        throw new RuntimeException('Could not set database charset.');\n" .
        "    }\n\n" .
        "    return \$connection;\n" .
        "}\n";

    if (file_put_contents($configPath, $configContent) === false) {
        throw new RuntimeException('Could not write includes/config.php');
    }
    if (file_put_contents($dbPath, $dbContent) === false) {
        throw new RuntimeException('Could not write includes/db.php');
    }
}

function writeLockFile(string $storageDir, string $lockFile, string $appUrl): void
{
    if (!is_dir($storageDir) && !mkdir($storageDir, 0755, true) && !is_dir($storageDir)) {
        throw new RuntimeException('Could not create storage directory for install lock.');
    }

    $payload = [
        'installed_at' => date('c'),
        'php_version' => PHP_VERSION,
        'app_url' => $appUrl,
    ];

    if (file_put_contents($lockFile, json_encode($payload, JSON_PRETTY_PRINT)) === false) {
        throw new RuntimeException('Could not write installation lock file.');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>EventSaaS Installer</title>
    <style>
        :root {
            --bg: #141312;
            --surface: rgba(54, 52, 51, 0.62);
            --surface-soft: rgba(32, 31, 30, 0.78);
            --text: #e6e2df;
            --muted: #c0b2ad;
            --primary: #e7bdb1;
            --primary-dark: #d4ada1;
            --outline: rgba(156, 141, 137, 0.22);
            --danger: #ffb4ab;
            --success: #94d3a2;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            color: var(--text);
            min-height: 100vh;
            background:
                radial-gradient(circle at 10% 10%, rgba(231, 189, 177, 0.15), transparent 45%),
                radial-gradient(circle at 90% 90%, rgba(93, 64, 55, 0.3), transparent 40%),
                var(--bg);
            padding: 24px;
        }

        .wrap {
            max-width: 980px;
            margin: 0 auto;
        }

        .panel {
            background: var(--surface);
            border: 1px solid var(--outline);
            backdrop-filter: blur(18px);
            border-radius: 24px;
            padding: 24px;
            box-shadow: 0 14px 40px rgba(0, 0, 0, 0.34);
        }

        h1 {
            margin-top: 0;
            margin-bottom: 6px;
            font-size: 32px;
            letter-spacing: 0.2px;
        }

        .sub {
            margin-top: 0;
            color: var(--muted);
            margin-bottom: 24px;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 14px;
        }

        label {
            display: block;
            margin-bottom: 6px;
            color: var(--muted);
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.8px;
        }

        input {
            width: 100%;
            border-radius: 14px;
            border: 1px solid var(--outline);
            background: var(--surface-soft);
            color: var(--text);
            padding: 11px 12px;
            font-size: 14px;
            outline: none;
        }

        input:focus {
            border-color: rgba(231, 189, 177, 0.6);
            box-shadow: 0 0 0 4px rgba(231, 189, 177, 0.12);
        }

        .row-full { grid-column: 1 / -1; }

        .btn {
            border: none;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: #442a22;
            font-weight: 700;
            border-radius: 16px;
            padding: 13px 18px;
            cursor: pointer;
            margin-top: 18px;
        }

        .state {
            background: rgba(32, 31, 30, 0.6);
            border: 1px solid var(--outline);
            border-radius: 14px;
            padding: 12px;
            margin: 12px 0;
        }

        .ok { color: var(--success); }
        .bad { color: var(--danger); }

        ul {
            margin-top: 8px;
            margin-bottom: 0;
            padding-left: 18px;
        }

        .locked {
            padding: 14px;
            border-radius: 12px;
            border: 1px solid rgba(255, 180, 171, 0.4);
            background: rgba(105, 0, 5, 0.2);
            margin-bottom: 16px;
        }

        @media (max-width: 720px) {
            body { padding: 12px; }
            .panel { padding: 16px; border-radius: 18px; }
            h1 { font-size: 24px; }
        }
    </style>
</head>
<body>
<div class="wrap">
    <div class="panel">
        <h1>EventSaaS Setup</h1>
        <p class="sub">Shared-hosting friendly installer for PHP 7 + mysqli. Default timezone is Africa/Nairobi (GMT+3) and currency is UGX.</p>

        <?php if ($isInstalled): ?>
            <div class="locked">Installation lock detected at <code>storage/install.lock</code>. Remove it only if you intentionally want to reinstall.</div>
        <?php endif; ?>

        <div class="state">
            <strong>Environment checks</strong>
            <ul>
                <?php foreach ($requirements as $label => $ok): ?>
                    <li class="<?php echo $ok ? 'ok' : 'bad'; ?>"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?> - <?php echo $ok ? 'OK' : 'Failed'; ?></li>
                <?php endforeach; ?>
            </ul>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="state bad">
                <strong>Installation errors</strong>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (!empty($messages)): ?>
            <div class="state ok">
                <strong>Installer output</strong>
                <ul>
                    <?php foreach ($messages as $message): ?>
                        <li><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (!$isInstalled): ?>
            <form method="post" autocomplete="off">
                <div class="grid">
                    <div>
                        <label for="app_name">Application name</label>
                        <input id="app_name" name="app_name" value="<?php echo htmlspecialchars($input['app_name'], ENT_QUOTES, 'UTF-8'); ?>" required>
                    </div>
                    <div>
                        <label for="app_url">Application URL</label>
                        <input id="app_url" name="app_url" value="<?php echo htmlspecialchars($input['app_url'], ENT_QUOTES, 'UTF-8'); ?>" required>
                    </div>

                    <div>
                        <label for="timezone">Timezone</label>
                        <input id="timezone" name="timezone" value="<?php echo htmlspecialchars($input['timezone'], ENT_QUOTES, 'UTF-8'); ?>" required>
                    </div>
                    <div>
                        <label for="currency">Default currency</label>
                        <input id="currency" name="currency" value="<?php echo htmlspecialchars($input['currency'], ENT_QUOTES, 'UTF-8'); ?>" required>
                    </div>

                    <div>
                        <label for="db_host">Database host</label>
                        <input id="db_host" name="db_host" value="<?php echo htmlspecialchars($input['db_host'], ENT_QUOTES, 'UTF-8'); ?>" required>
                    </div>
                    <div>
                        <label for="db_port">Database port</label>
                        <input id="db_port" name="db_port" value="<?php echo htmlspecialchars($input['db_port'], ENT_QUOTES, 'UTF-8'); ?>" required>
                    </div>

                    <div>
                        <label for="db_name">Database name</label>
                        <input id="db_name" name="db_name" value="<?php echo htmlspecialchars($input['db_name'], ENT_QUOTES, 'UTF-8'); ?>" required>
                    </div>
                    <div>
                        <label for="db_user">Database username</label>
                        <input id="db_user" name="db_user" value="<?php echo htmlspecialchars($input['db_user'], ENT_QUOTES, 'UTF-8'); ?>" required>
                    </div>

                    <div class="row-full">
                        <label for="db_password">Database password</label>
                        <input id="db_password" name="db_password" type="password" value="">
                    </div>

                    <div>
                        <label for="director_name">Director full name</label>
                        <input id="director_name" name="director_name" value="<?php echo htmlspecialchars($input['director_name'], ENT_QUOTES, 'UTF-8'); ?>" required>
                    </div>
                    <div>
                        <label for="director_email">Director email</label>
                        <input id="director_email" name="director_email" type="email" value="<?php echo htmlspecialchars($input['director_email'], ENT_QUOTES, 'UTF-8'); ?>" required>
                    </div>

                    <div class="row-full">
                        <label for="director_password">Director password</label>
                        <input id="director_password" name="director_password" type="password" required>
                    </div>
                </div>

                <button class="btn" type="submit">Install System</button>
            </form>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
