-- ============================================================
-- schema_v11 — Remove duplicate test_type rows + guard future ones
-- Run against the live database once (safe to re-run):
--   mysql -u <app_user> -p <dbname> < schema_v11.sql
--
-- Why: the "internal log sheet" pills showed two ویسکوزیته and
-- two دانسیته options. test_types was seeded without a UNIQUE
-- constraint on `name`, so re-running the seed created duplicate
-- rows.
--
-- What it does:
--   1. Deletes duplicate test_types that have the same name as an
--      earlier row AND are not referenced by any internal_log_sheet
--      (unused duplicates — keeps the lowest id, which is the one
--      the seed/existing sheets reference).
--   2. Adds a UNIQUE index on test_types(name) so the seed can
--      never create duplicates again.
-- ============================================================

DELETE tt
FROM test_types tt
JOIN (
    SELECT name, MIN(id) AS keep_id
    FROM test_types
    GROUP BY name
    HAVING COUNT(*) > 1
) dup ON tt.name = dup.name AND tt.id <> dup.keep_id
LEFT JOIN internal_log_sheets ils ON ils.test_type_id = tt.id
WHERE ils.id IS NULL;

-- Prevent future duplicates. If this fails because a duplicate that
-- still HAS internal log sheets was kept, migrate its sheets/results
-- to the kept row first (or contact admin) — the pills should then
-- be clean on the next reload.
ALTER TABLE test_types
    ADD UNIQUE INDEX uq_test_types_name (name);