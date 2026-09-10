<?php
// filepath: /var/www/html/auth/install.php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/lang.php';

if (file_exists(__DIR__ . '/.installed')) {
    exit('Installation has already been completed.');
}

$message = '';
$success = false;

$host = 'localhost';
$dbname = '';
$dbUser = '';
$dbPassword = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $host = trim($_POST['host'] ?? '');
    $dbname = trim($_POST['dbname'] ?? '');
    $dbUser = trim($_POST['username'] ?? '');
    $dbPassword = $_POST['password'] ?? '';

    if ($host === '' || preg_match('/[\s;\'"`]/', $host)) {
        $message = is_persian() ? 'لطفاً میزبان معتبر پایگاه داده را وارد کنید.' : 'Please enter a valid database host.';
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $dbname)) {
        $message = is_persian() ? 'نام پایگاه داده فقط می‌تواند شامل حروف، اعداد و زیرخط باشد.' : 'Database name can only contain letters, numbers, and underscores.';
    } elseif ($dbUser === '') {
        $message = is_persian() ? 'نام کاربری پایگاه داده الزامی است.' : 'Database username is required.';
    } else {
        try {
            $pdo = null;

            // First try connecting without selecting a database.
            try {
                $serverPdo = new PDO(
                    "mysql:host={$host};charset=utf8mb4",
                    $dbUser,
                    $dbPassword,
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    ]
                );

                // Works when the account has CREATE DATABASE privilege.
                $quotedDbName = '`' . str_replace('`', '``', $dbname) . '`';
                $serverPdo->exec(
                    "CREATE DATABASE IF NOT EXISTS {$quotedDbName}
                     CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
                );
            } catch (PDOException $e) {
                // The database may already exist, while the user lacks
                // permission to create databases.
            }

            // Connect directly to the selected database.
            $pdo = new PDO(
                "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
                $dbUser,
                $dbPassword,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]
            );

            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS users (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    username VARCHAR(100) NOT NULL UNIQUE,
                    email VARCHAR(255) NOT NULL UNIQUE,
                    password VARCHAR(255) NOT NULL,
                    reset_token VARCHAR(255) DEFAULT NULL,
                    reset_expires DATETIME DEFAULT NULL,
                    two_factor_enabled TINYINT(1) NOT NULL DEFAULT 0,
                    two_factor_secret VARCHAR(255) DEFAULT NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );

            $userColumns = $pdo->query('SHOW COLUMNS FROM users')
                ->fetchAll(PDO::FETCH_COLUMN);

            if (!in_array('two_factor_enabled', $userColumns, true)) {
                $pdo->exec(
                    "ALTER TABLE users
                     ADD COLUMN two_factor_enabled TINYINT(1) NOT NULL DEFAULT 0"
                );
            }

            if (!in_array('two_factor_secret', $userColumns, true)) {
                $pdo->exec(
                    "ALTER TABLE users
                     ADD COLUMN two_factor_secret VARCHAR(255) DEFAULT NULL"
                );
            }

            // ================================================================
            // Lab digitization module tables (fuel & oil lab indicator log)
            // ================================================================

            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS sample_types (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    code TINYINT NOT NULL UNIQUE,
                    name_fa VARCHAR(100) NOT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );

            // Seed the 9 fixed sample types (INSERT IGNORE so re-running
            // the installer before .installed is written won't error out
            // on the UNIQUE code column).
            $pdo->exec(
                "INSERT IGNORE INTO sample_types (code, name_fa) VALUES
                    (1, 'گازوئیل'),
                    (2, 'روغن'),
                    (3, 'آب'),
                    (4, 'کلرید سدیم'),
                    (5, 'اسید کلریدریک'),
                    (6, 'هیدروکلرید سدیم'),
                    (7, 'رسوب'),
                    (8, 'B&B'),
                    (9, 'نفت سفید')"
            );

            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS samples (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    sample_number VARCHAR(30) NOT NULL UNIQUE,
                    sample_type_id INT NOT NULL,
                    sample_name VARCHAR(150) NULL,
                    jalali_year SMALLINT NOT NULL,
                    quantity DECIMAL(10,2) NULL,
                    quantity_unit VARCHAR(20) NULL,
                    sampling_date DATE NULL,
                    delivery_date DATE NULL,
                    sampling_location VARCHAR(150) NULL,
                    referrer VARCHAR(150) NULL,
                    receiver VARCHAR(150) NULL,
                    status VARCHAR(50) DEFAULT 'in_progress',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (sample_type_id) REFERENCES sample_types(id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );

            // Shared per-Jalali-year sample number counter (atomic,
            // race-safe numbering — see lab_generate_sample_number()).
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS sample_number_counters (
                    jalali_year SMALLINT NOT NULL PRIMARY KEY,
                    last_seq INT NOT NULL DEFAULT 0
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );

            // ================================================================
            // End lab digitization module tables
            // ================================================================

            $config = "<?php\n"
                . "declare(strict_types=1);\n\n"
                . "\$host = " . var_export($host, true) . ";\n"
                . "\$dbname = " . var_export($dbname, true) . ";\n"
                . "\$user = " . var_export($dbUser, true) . ";\n"
                . "\$pass = " . var_export($dbPassword, true) . ";\n\n"
                . "if (session_status() !== PHP_SESSION_ACTIVE) {\n"
                . "    session_start();\n"
                . "}\n\n"
                . "try {\n"
                . "    \$pdo = new PDO(\n"
                . "        \"mysql:host=\$host;dbname=\$dbname;charset=utf8mb4\",\n"
                . "        \$user,\n"
                . "        \$pass,\n"
                . "        [\n"
                . "            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,\n"
                . "            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,\n"
                . "            PDO::ATTR_EMULATE_PREPARES => false,\n"
                . "        ]\n"
                . "    );\n"
                . "} catch (PDOException \$e) {\n"
                . "    error_log('Database connection failed: ' . \$e->getMessage());\n"
                . "    exit('Database connection failed.');\n"
                . "}\n";

            $configPath = __DIR__ . '/config.php';

            if (file_put_contents($configPath, $config, LOCK_EX) === false) {
                throw new RuntimeException('Could not create config.php. Check permissions.');
            }

            chmod($configPath, 0640);

            $encryptionKeyPath = __DIR__ . '/.auth_encryption_key';
            if (!file_exists($encryptionKeyPath)) {
                $encryptionKey = base64_encode(random_bytes(32)) . PHP_EOL;
                if (file_put_contents(
                    $encryptionKeyPath,
                    $encryptionKey,
                    LOCK_EX
                ) === false) {
                    throw new RuntimeException('Could not create the encryption key file.');
                }
                chmod($encryptionKeyPath, 0640);
            }

            $lockPath = __DIR__ . '/.installed';

            if (file_put_contents(
                $lockPath,
                'Installed on ' . date('Y-m-d H:i:s'),
                LOCK_EX
            ) === false) {
                throw new RuntimeException('Could not create installation lock file.');
            }

            $success = true;
                    $message = is_persian() ? 'نصب با موفقیت انجام شد!' : 'Installation successful!';
        } catch (PDOException $e) {
            error_log('Installation database error: ' . $e->getMessage());
            $message = is_persian()
                ? 'اتصال به پایگاه داده ممکن نبود. اطلاعات اتصال را بررسی کنید.'
                : 'Could not connect to the database. Verify the host, database name, username, and password.';
        } catch (Throwable $e) {
            error_log('Installation error: ' . $e->getMessage());
            $message = $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo t('install_title'); ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body dir="<?php echo is_persian() ? 'rtl' : 'ltr'; ?>" lang="<?php echo is_persian() ? 'fa' : 'en'; ?>">
<div class="auth-container">
    <div class="auth-card">
        <div class="logo">A</div>

        <div class="language-switch"><a href="<?php echo htmlspecialchars(language_url($currentLanguage === 'fa' ? 'en' : 'fa'), ENT_QUOTES, 'UTF-8'); ?>"><?php echo t('language'); ?></a></div>
        <h1><?php echo t('install_title'); ?></h1>
        <p class="subtitle"><?php echo t('install_subtitle'); ?></p>

        <?php if ($message !== ''): ?>
            <div class="<?= $success ? 'success-message' : 'error-message' ?>">
                <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <?php if (!$success): ?>
            <form method="post">
                <div class="form-group">
                    <label for="host"><?php echo t('database_host'); ?></label>
                    <input
                        id="host"
                        type="text"
                        name="host"
                        value="<?= htmlspecialchars($host, ENT_QUOTES, 'UTF-8') ?>"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="dbname"><?php echo t('database_name'); ?></label>
                    <input
                        id="dbname"
                        type="text"
                        name="dbname"
                        value="<?= htmlspecialchars($dbname, ENT_QUOTES, 'UTF-8') ?>"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="username"><?php echo t('database_username'); ?></label>
                    <input
                        id="username"
                        type="text"
                        name="username"
                        value="<?= htmlspecialchars($dbUser, ENT_QUOTES, 'UTF-8') ?>"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="password"><?php echo t('database_password'); ?></label>
                    <input id="password" type="password" name="password">
                </div>

                <button type="submit"><?php echo t('install'); ?></button>
            </form>
        <?php else: ?>
            <p>
                <a href="register"><?php echo t('go_registration'); ?></a>
            </p>
        <?php endif; ?>
    </div>
</div>
</body>
</html>