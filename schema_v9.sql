-- ============================================================
-- Lab digitization module — schema v9
-- 1) Adds is_admin flag to users (needed to gate the log viewer)
-- 2) Adds login_logs and activity_logs tables
-- 3) Adds the remaining internal-log-sheet test types so every
--    paper form (داخلی.pdf) has a matching test type
-- ============================================================

-- ------------------------------------------------------------
-- 1) Admin flag
-- ------------------------------------------------------------
SET @has_is_admin := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'is_admin'
);
SET @add_is_admin_sql := IF(@has_is_admin = 0,
    'ALTER TABLE users ADD COLUMN is_admin TINYINT(1) NOT NULL DEFAULT 0',
    'SELECT 1');
PREPARE stmt FROM @add_is_admin_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- IMPORTANT: after running this, make yourself admin, e.g.:
--   UPDATE users SET is_admin = 1 WHERE username = 'your_username';

-- ------------------------------------------------------------
-- 2) Log tables
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS login_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    username VARCHAR(100) NOT NULL,
    success TINYINT(1) NOT NULL,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    username VARCHAR(100),
    action VARCHAR(100) NOT NULL,
    details VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 3) Remaining internal-log-sheet test types
--    (دانسیته / ویسکوزیته already exist as ids 1 و 2)
-- ------------------------------------------------------------
INSERT IGNORE INTO test_types (id, name, unit) VALUES
    (3,  'توانائی جداسازی هوا', 'min'),
    (4,  'توانائی جداسازی آب', 'Sec'),
    (5,  'اندازه‌گیری کف', 'cm3'),
    (6,  'مقدار آب', '%wt'),
    (7,  'عدد خنثی‌سازی', 'mgKOH/g'),
    (8,  'نقطه ابری شدن', 'C'),
    (9,  'نقطه انجماد', 'C'),
    (10, 'نقطه ریزش', 'C'),
    (11, 'خوردگی مس', NULL),
    (12, 'خوردگی فولاد', NULL);
