-- ============================================================
-- schema_v12 — Merge duplicate test_types that still have
-- internal log sheets, then add the UNIQUE index.
-- Run once:  mysql -u <app_user> -p <dbname> < schema_v12.sql
--
-- Fixes the "#1062 Duplicate entry 'دانسیته'" error from v11:
-- a duplicate test_types row was kept because internal_log_sheets
-- still reference it. This migration:
--   1. Re-points every internal_log_sheet from the duplicate type
--      id to the kept (lowest-id) type id — test_results follow
--      automatically, since they reference the internal sheet.
--   2. Deletes the (now unused) duplicate test_types rows.
--   3. Adds the UNIQUE index on test_types(name), if not present.
--
-- Safe to re-run.
-- ============================================================

-- 1) Reassign internal log sheets of duplicate types to the kept row
UPDATE internal_log_sheets ils
JOIN (
    SELECT tt.id AS dup_id, keep.keep_id
    FROM test_types tt
    JOIN (
        SELECT name, MIN(id) AS keep_id
        FROM test_types
        GROUP BY name
        HAVING COUNT(*) > 1
    ) keep ON tt.name = keep.name AND tt.id <> keep.keep_id
) d ON ils.test_type_id = d.dup_id
SET ils.test_type_id = d.keep_id;

-- 2) Delete the duplicate test_types rows (now unreferenced)
DELETE tt
FROM test_types tt
JOIN (
    SELECT name, MIN(id) AS keep_id
    FROM test_types
    GROUP BY name
    HAVING COUNT(*) > 1
) keep ON tt.name = keep.name AND tt.id <> keep.keep_id;

-- 3) Add the UNIQUE index if it isn't already there
SET @has_uq := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'test_types'
      AND INDEX_NAME = 'uq_test_types_name'
);
SET @add_uq := IF(
    @has_uq = 0,
    'ALTER TABLE test_types ADD UNIQUE INDEX uq_test_types_name (name)',
    'SELECT 1'
);
PREPARE stmt_uq FROM @add_uq;
EXECUTE stmt_uq;
DEALLOCATE PREPARE stmt_uq;