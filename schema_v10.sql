-- ============================================================
-- schema_v10 — Numeric allowed-range columns for test definitions
-- Run against the live database once (idempotent, safe to re-run):
--   mysql -u <app_user> -p <dbname> < schema_v10.sql
--
-- Adds to main_log_sheet_test_definitions:
--   limit_min DECIMAL(14,4) NULL
--   limit_max DECIMAL(14,4) NULL
-- Used by the "محدوده‌های مجاز" page and the live red/green
-- validation in the internal log sheet entry form.
-- ============================================================

SET @has_limit_min := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'main_log_sheet_test_definitions'
      AND COLUMN_NAME = 'limit_min'
);
SET @has_limit_max := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'main_log_sheet_test_definitions'
      AND COLUMN_NAME = 'limit_max'
);
SET @add_limit_min := IF(
    @has_limit_min = 0,
    'ALTER TABLE main_log_sheet_test_definitions ADD COLUMN limit_min DECIMAL(14,4) NULL AFTER limit_used',
    'SELECT 1'
);
SET @add_limit_max := IF(
    @has_limit_max = 0,
    'ALTER TABLE main_log_sheet_test_definitions ADD COLUMN limit_max DECIMAL(14,4) NULL AFTER limit_min',
    'SELECT 1'
);
PREPARE stmt_limit_min FROM @add_limit_min;
EXECUTE stmt_limit_min;
DEALLOCATE PREPARE stmt_limit_min;
PREPARE stmt_limit_max FROM @add_limit_max;
EXECUTE stmt_limit_max;
DEALLOCATE PREPARE stmt_limit_max;