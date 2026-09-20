<?php
// filepath: /var/www/html/auth/install.php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/lang.php';
require_once __DIR__ . '/security.php';

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
    csrf_validate();

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

            // Re-fetch columns (the ALTERs above may have changed the set).
            $userColumns = $pdo->query('SHOW COLUMNS FROM users')
                ->fetchAll(PDO::FETCH_COLUMN);

            if (!in_array('is_admin', $userColumns, true)) {
                $pdo->exec(
                    "ALTER TABLE users
                     ADD COLUMN is_admin TINYINT(1) NOT NULL DEFAULT 0"
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

                                    // Main log sheet types (fuel & oil product categories) — must exist
            // before samples table (which has a FK to it).
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS main_log_sheet_types (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    code VARCHAR(20) NOT NULL UNIQUE,
                    name_fa VARCHAR(150) NOT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );

            // Seed main log sheet types
            $pdo->exec(
                "INSERT IGNORE INTO main_log_sheet_types (code, name_fa) VALUES
                    ('FG-PC-0201', 'سوخت مایع'),
                    ('FG-PC-0206', 'روغن دیزل ژنراتور'),
                    ('FG-PC-0428', 'روغن توربین گاز'),
                    ('FG-PC-0202', 'روغن توربین بخار'),
                    ('FG-PC-0429', 'روغن کنترل'),
                    ('FG-PC-0203', 'روغن ترانسفورماتور'),
                    ('FG-PC-0191', 'مایعات سیکل خنک‌کننده بسته')"
            );

            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS samples (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    sample_number VARCHAR(30) NOT NULL UNIQUE,
                    sample_type_id INT NOT NULL,
                    main_log_sheet_type_id INT NOT NULL,
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
                    FOREIGN KEY (sample_type_id) REFERENCES sample_types(id),
                    FOREIGN KEY (main_log_sheet_type_id) REFERENCES main_log_sheet_types(id)
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

            // Migration: add main_log_sheet_type_id to samples if missing
            // (for existing DBs that have the old samples table without this column)
            $sampleColumns = $pdo->query('SHOW COLUMNS FROM samples')
                ->fetchAll(PDO::FETCH_COLUMN);
            if (!in_array('main_log_sheet_type_id', $sampleColumns, true)) {
                $pdo->exec(
                    "ALTER TABLE samples
                     ADD COLUMN main_log_sheet_type_id INT NOT NULL DEFAULT 1
                     AFTER sample_type_id"
                );
            }

                        // Test definitions per main log sheet type
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS main_log_sheet_test_definitions (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    main_log_sheet_type_id INT NOT NULL,
                    row_order INT NOT NULL,
                    test_name VARCHAR(150) NOT NULL,
                    unit VARCHAR(30),
                    method VARCHAR(50),
                    limit_new VARCHAR(50),
                    limit_used VARCHAR(50),
                    limit_min DECIMAL(14,4) NULL,
                    limit_max DECIMAL(14,4) NULL,
                    test_location VARCHAR(50) DEFAULT 'داخل نیروگاه',
                    FOREIGN KEY (main_log_sheet_type_id) REFERENCES main_log_sheet_types(id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );

            // Migration: add numeric allowed-range columns if missing
            // (for existing DBs created before schema_v10).
            $defColumns = $pdo->query('SHOW COLUMNS FROM main_log_sheet_test_definitions')
                ->fetchAll(PDO::FETCH_COLUMN);
            if (!in_array('limit_min', $defColumns, true)) {
                $pdo->exec(
                    "ALTER TABLE main_log_sheet_test_definitions
                     ADD COLUMN limit_min DECIMAL(14,4) NULL AFTER limit_used"
                );
            }
            if (!in_array('limit_max', $defColumns, true)) {
                $pdo->exec(
                    "ALTER TABLE main_log_sheet_test_definitions
                     ADD COLUMN limit_max DECIMAL(14,4) NULL AFTER limit_min"
                );
            }

            // Test types (Density, Viscosity, etc.)
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS test_types (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(100) NOT NULL,
                    unit VARCHAR(20),
                    UNIQUE KEY uq_test_types_name (name)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );

            // Seed test types
            $pdo->exec(
                "INSERT IGNORE INTO test_types (id, name, unit) VALUES
                    (1, 'دانسیته', 'kg/m3'),
                    (2, 'ویسکوزیته', 'cSt')"
            );

            // Internal log sheets (one per test type, accumulating over time)
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS internal_log_sheets (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    test_type_id INT NOT NULL,
                    sheet_date DATE NOT NULL,
                    title VARCHAR(150),
                    FOREIGN KEY (test_type_id) REFERENCES test_types(id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );

            // Test results on internal log sheets
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS test_results (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    sample_id INT NOT NULL,
                    internal_log_sheet_id INT NOT NULL,
                    result_value VARCHAR(100),
                    tested_date DATE NOT NULL,
                    is_used_in_main_sheet BOOLEAN DEFAULT FALSE,
                    FOREIGN KEY (sample_id) REFERENCES samples(id),
                    FOREIGN KEY (internal_log_sheet_id) REFERENCES internal_log_sheets(id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );

            // Main log sheets (one per sample)
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS main_log_sheets (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    sample_id INT NOT NULL UNIQUE,
                    compiled_date DATE,
                    status ENUM('draft','final','sent') DEFAULT 'draft',
                    word_file_path VARCHAR(255),
                    sent_to_cmms_date DATE,
                    cmms_reference_no VARCHAR(100),
                    FOREIGN KEY (sample_id) REFERENCES samples(id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );

                        // Main log sheet results (one per test definition per main sheet)
            $pdo->exec("DROP TABLE IF EXISTS main_log_sheet_results");
            $pdo->exec(
                "CREATE TABLE main_log_sheet_results (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    main_log_sheet_id INT NOT NULL,
                    test_definition_id INT NOT NULL,
                    result_value VARCHAR(100),
                    FOREIGN KEY (main_log_sheet_id) REFERENCES main_log_sheets(id),
                    FOREIGN KEY (test_definition_id) REFERENCES main_log_sheet_test_definitions(id),
                    UNIQUE KEY uniq_sheet_test (main_log_sheet_id, test_definition_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );

            // ================================================================
            // End lab digitization module tables
            // ================================================================

            // ----------------------------------------------------------------
            // Audit / admin tables (lab activity log + login attempts log)
            // ----------------------------------------------------------------
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS activity_logs (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT UNSIGNED NULL,
                    username VARCHAR(100) NULL,
                    action VARCHAR(100) NOT NULL,
                    details TEXT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_activity_action (action),
                    INDEX idx_activity_created (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );

            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS login_logs (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    username VARCHAR(100) NULL,
                    success TINYINT(1) NOT NULL DEFAULT 0,
                    ip_address VARCHAR(45) NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_login_created (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );

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
    <link rel="stylesheet" href="style.css?v=20260920">
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
                <?php echo csrf_field(); ?>

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
<div class="site-signature">Developed by Mehdi Habibi</div>

</body>
</html>
