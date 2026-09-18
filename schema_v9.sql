-- ============================================================
-- schema_v9 — Admin/audit tables for an EXISTING installation
-- Run against the live database once (idempotent, safe to re-run):
--   mysql -u <app_user> -p <dbname> < schema_v9.sql
--
-- Adds:
--   - users.is_admin  (only if a users table exists)
--   - activity_logs   (lab actions via lab_log_activity())
--   - login_logs      (login attempts via auth/login.php)
-- ============================================================

SET @has_users := (
    SELECT COUNT(*) FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users'
);
SET @has_is_admin := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'users'
      AND COLUMN_NAME = 'is_admin'
);
SET @add_is_admin := IF(
    @has_users > 0 AND @has_is_admin = 0,
    'ALTER TABLE users ADD COLUMN is_admin TINYINT(1) NOT NULL DEFAULT 0',
    'SELECT 1'
);
PREPARE stmt_is_admin FROM @add_is_admin;
EXECUTE stmt_is_admin;
DEALLOCATE PREPARE stmt_is_admin;

CREATE TABLE IF NOT EXISTS activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL,
    username VARCHAR(100) NULL,
    action VARCHAR(100) NOT NULL,
    details TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_activity_action (action),
    INDEX idx_activity_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NULL,
    success TINYINT(1) NOT NULL DEFAULT 0,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_login_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;