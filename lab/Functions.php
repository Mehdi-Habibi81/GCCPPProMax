<?php
declare(strict_types=1);

/**
 * Shared helpers for the lab digitization module.
 * Include this after auth/config.php (which provides $pdo and starts the session).
 *
 * Requires: composer require morilog/jalali
 * (run this from the project root, where composer.json already lives)
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Carbon\Carbon;
use Morilog\Jalali\Jalalian;

/**
 * Make sure the user is logged in before showing any lab page.
 */
function lab_require_login(): void
{
    if (empty($_SESSION['user_id'])) {
        header('Location: /auth/login');
        exit;
    }
}

/**
 * Is the currently-logged-in user an admin? Requires the
 * is_admin column added in schema_v9.sql.
 */
function lab_is_admin(PDO $pdo): bool
{
    if (empty($_SESSION['user_id'])) {
        return false;
    }
    $stmt = $pdo->prepare("SELECT is_admin FROM users WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $_SESSION['user_id']]);
    return (int)($stmt->fetchColumn() ?: 0) === 1;
}

/**
 * Redirect away (to the dashboard) unless the current user is an
 * admin. Call this at the top of admin-only pages.
 */
function lab_require_admin(PDO $pdo): void
{
    lab_require_login();
    if (!lab_is_admin($pdo)) {
        header('Location: /dashboard');
        exit;
    }
}

/**
 * Record one row in activity_logs. Call this right after any
 * meaningful lab action (sample created/edited, result recorded,
 * main sheet finalized/sent, etc).
 */
function lab_log_activity(PDO $pdo, string $action, ?string $details = null): void
{
    $userId = $_SESSION['user_id'] ?? null;
    $username = null;

    if ($userId) {
        $stmt = $pdo->prepare("SELECT username FROM users WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $userId]);
        $username = $stmt->fetchColumn() ?: null;
    }

    $stmt = $pdo->prepare(
        "INSERT INTO activity_logs (user_id, username, action, details)
         VALUES (:user_id, :username, :action, :details)"
    );
    $stmt->execute([
        'user_id'  => $userId,
        'username' => $username,
        'action'   => $action,
        'details'  => $details,
    ]);
}

/**
 * Recent activity log entries for the admin log viewer.
 */
function lab_get_activity_logs(PDO $pdo, int $limit = 200): array
{
    $stmt = $pdo->prepare("SELECT * FROM activity_logs ORDER BY id DESC LIMIT :limit");
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * Recent login attempts (success and failure) for the admin log viewer.
 */
function lab_get_login_logs(PDO $pdo, int $limit = 200): array
{
    $stmt = $pdo->prepare("SELECT * FROM login_logs ORDER BY id DESC LIMIT :limit");
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * All 9 sample types, ordered by code, for the dropdown.
 */
function lab_get_sample_types(PDO $pdo): array
{
    $stmt = $pdo->query("SELECT id, code, name_fa FROM sample_types ORDER BY code");
    return $stmt->fetchAll();
}

/**
 * The 7 main log sheet product types, for the (now required)
 * "which main log sheet does this sample belong to" dropdown.
 */
function lab_get_main_log_sheet_types(PDO $pdo): array
{
    $stmt = $pdo->query("SELECT id, code, name_fa FROM main_log_sheet_types ORDER BY id");
    return $stmt->fetchAll();
}

/**
 * Generate the sample number as: {jalali_year}-{type_code:02d}-{sequence:03d}
 * e.g. 1405-02-001
 *
 * The sequence is ONE shared counter per Jalali year across ALL sample
 * types — two samples registered in the same year never get the same
 * sequence number, regardless of their type.
 *
 * Uses a dedicated counter table (sample_number_counters) with an
 * atomic INSERT ... ON DUPLICATE KEY UPDATE + LAST_INSERT_ID() trick,
 * so concurrent requests can never be handed the same sequence number.
 */
function lab_generate_sample_number(PDO $pdo, int $typeCode, int $jalaliYear): string
{
    $stmt = $pdo->prepare(
        "INSERT INTO sample_number_counters (jalali_year, last_seq)
         VALUES (:jalali_year, 1)
         ON DUPLICATE KEY UPDATE last_seq = LAST_INSERT_ID(last_seq + 1)"
    );
    $stmt->execute(['jalali_year' => $jalaliYear]);

    $seq = (int) $pdo->lastInsertId();

    return sprintf('%d-%02d-%03d', $jalaliYear, $typeCode, $seq);
}

/**
 * Convert a Jalali date string like "1404/06/16" or "1404-06-16"
 * into a Gregorian "Y-m-d" string for storage. Returns null on
 * invalid input so the caller can show a validation error.
 */
function lab_jalali_to_gregorian(string $jalaliDate): ?string
{
    $normalized = str_replace('-', '/', trim($jalaliDate));
    try {
        return Jalalian::fromFormat('Y/m/d', $normalized)->toCarbon()->format('Y-m-d');
    } catch (\Throwable $e) {
        return null;
    }
}

/**
 * Convert a stored Gregorian "Y-m-d" date back to Jalali "Y/m/d"
 * for display in forms and tables.
 */
function lab_gregorian_to_jalali(string $gregorianDate): string
{
    try {
        return Jalalian::fromCarbon(Carbon::parse($gregorianDate))->format('Y/m/d');
    } catch (\Throwable $e) {
        return $gregorianDate;
    }
}

/**
 * Convert a stored Gregorian datetime ("Y-m-d H:i:s") to a Jalali
 * date "Y/m/d" keeping the original time, e.g.
 * "2024-06-16 14:30:00" -> "1403/03/27 14:30:00".
 */
function lab_gregorian_datetime_to_jalali(string $gregorianDatetime): string
{
    if (preg_match('/^(\d{4}-\d{2}-\d{2})(.*)$/', trim($gregorianDatetime), $m)) {
        return lab_gregorian_to_jalali($m[1]) . $m[2];
    }

    return $gregorianDatetime;
}

/**
 * Current Jalali year (used for numbering new samples).
 */
function lab_current_jalali_year(): int
{
    return (int) Jalalian::now()->getYear();
}

/**
 * Fetch a single sample by id, with Jalali-formatted dates for
 * pre-filling the edit form.
 */
function lab_get_sample_by_id(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare(
        "SELECT s.*, st.name_fa AS type_name, st.code AS type_code,
                mlt.name_fa AS main_log_sheet_type_name
         FROM samples s
         JOIN sample_types st ON s.sample_type_id = st.id
         LEFT JOIN main_log_sheet_types mlt ON s.main_log_sheet_type_id = mlt.id
         WHERE s.id = :id
         LIMIT 1"
    );
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }
    $row['sampling_date_fa'] = $row['sampling_date'] ? lab_gregorian_to_jalali($row['sampling_date']) : '';
    $row['delivery_date_fa'] = $row['delivery_date'] ? lab_gregorian_to_jalali($row['delivery_date']) : '';
    return $row;
}

/**
 * Fetch recent samples (with type name and Jalali dates) for the
 * listing table on indicator.php
 */
function lab_get_recent_samples(PDO $pdo, int $limit = 50): array
{
    $stmt = $pdo->prepare(
        "SELECT s.id, s.sample_number, mlt.name_fa AS main_log_sheet_type_name,
                st.name_fa AS type_name,
                s.quantity, s.quantity_unit,
                s.sampling_date, s.delivery_date,
                s.sampling_location, s.referrer, s.receiver, s.status
         FROM samples s
         JOIN sample_types st ON s.sample_type_id = st.id
         LEFT JOIN main_log_sheet_types mlt ON s.main_log_sheet_type_id = mlt.id
         ORDER BY s.id DESC
         LIMIT :limit"
    );
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();

    foreach ($rows as &$row) {
        $row['sampling_date_fa'] = $row['sampling_date'] ? lab_gregorian_to_jalali($row['sampling_date']) : '';
        $row['delivery_date_fa'] = $row['delivery_date'] ? lab_gregorian_to_jalali($row['delivery_date']) : '';
    }

    return $rows;
}

/**
 * All test types (Density, Viscosity, ...) for the internal log
 * sheet selector.
 */
function lab_get_test_types(PDO $pdo): array
{
    $stmt = $pdo->query("SELECT id, name, unit FROM test_types ORDER BY id");
    return $stmt->fetchAll();
}

/**
 * Get the single ongoing internal log sheet for a test type,
 * creating it the first time it's needed. This models the paper
 * workflow: one notebook per test type that keeps accumulating
 * entries over time (rather than one sheet per day/batch).
 */
function lab_get_or_create_internal_sheet(PDO $pdo, int $testTypeId): int
{
    $stmt = $pdo->prepare(
        "SELECT id FROM internal_log_sheets WHERE test_type_id = :test_type_id LIMIT 1"
    );
    $stmt->execute(['test_type_id' => $testTypeId]);
    $row = $stmt->fetch();
    if ($row) {
        return (int) $row['id'];
    }

    $typeStmt = $pdo->prepare("SELECT name FROM test_types WHERE id = :id");
    $typeStmt->execute(['id' => $testTypeId]);
    $typeName = $typeStmt->fetchColumn() ?: '';

    $insert = $pdo->prepare(
        "INSERT INTO internal_log_sheets (test_type_id, sheet_date, title)
         VALUES (:test_type_id, CURDATE(), :title)"
    );
    $insert->execute([
        'test_type_id' => $testTypeId,
        'title'        => 'لاگ‌شیت ' . $typeName,
    ]);

    return (int) $pdo->lastInsertId();
}

/**
 * Samples for the "which sample is this result for" dropdown.
 * Ordered newest first; limited so the dropdown stays usable.
 */
function lab_get_samples_for_select(PDO $pdo, int $limit = 200): array
{
    $stmt = $pdo->prepare(
        "SELECT s.id, s.sample_number,
                s.main_log_sheet_type_id,
                mlt.name_fa AS main_log_sheet_type_name
         FROM samples s
         LEFT JOIN main_log_sheet_types mlt ON s.main_log_sheet_type_id = mlt.id
         ORDER BY s.id DESC
         LIMIT :limit"
    );
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * Test results already recorded on a given internal log sheet,
 * with the related sample number/type and Jalali test date.
 */
function lab_get_results_for_sheet(PDO $pdo, int $sheetId): array
{
    $stmt = $pdo->prepare(
        "SELECT tr.id, tr.result_value, tr.tested_date, tr.is_used_in_main_sheet,
                s.sample_number, mlt.name_fa AS main_log_sheet_type_name
         FROM test_results tr
         JOIN samples s ON tr.sample_id = s.id
         LEFT JOIN main_log_sheet_types mlt ON s.main_log_sheet_type_id = mlt.id
         WHERE tr.internal_log_sheet_id = :sheet_id
         ORDER BY tr.id DESC"
    );
    $stmt->execute(['sheet_id' => $sheetId]);
    $rows = $stmt->fetchAll();

    foreach ($rows as &$row) {
        $row['tested_date_fa'] = lab_gregorian_to_jalali($row['tested_date']);
    }

    return $rows;
}

/**
 * Get the main log sheet for a sample, creating it the first
 * time it's needed (one main log sheet per sample).
 */
function lab_get_or_create_main_sheet(PDO $pdo, int $sampleId): int
{
    $stmt = $pdo->prepare("SELECT id FROM main_log_sheets WHERE sample_id = :sample_id LIMIT 1");
    $stmt->execute(['sample_id' => $sampleId]);
    $row = $stmt->fetch();
    if ($row) {
        return (int) $row['id'];
    }

    $insert = $pdo->prepare(
        "INSERT INTO main_log_sheets (sample_id, compiled_date, status)
         VALUES (:sample_id, CURDATE(), 'draft')"
    );
    $insert->execute(['sample_id' => $sampleId]);

    return (int) $pdo->lastInsertId();
}

/**
 * The fixed test rows for a sample's main log sheet type, each
 * joined to any result already saved on this main log sheet.
 */
function lab_get_main_sheet_rows(PDO $pdo, int $mainLogSheetId, int $mainLogSheetTypeId): array
{
    $stmt = $pdo->prepare(
        "SELECT d.id AS test_definition_id, d.row_order, d.test_name, d.unit, d.method,
                d.limit_new, d.limit_used, d.test_location,
                r.result_value
         FROM main_log_sheet_test_definitions d
         LEFT JOIN main_log_sheet_results r
                ON r.test_definition_id = d.id AND r.main_log_sheet_id = :main_log_sheet_id
         WHERE d.main_log_sheet_type_id = :main_log_sheet_type_id
         ORDER BY d.row_order"
    );
    $stmt->execute([
        'main_log_sheet_id'      => $mainLogSheetId,
        'main_log_sheet_type_id' => $mainLogSheetTypeId,
    ]);
    return $stmt->fetchAll();
}

/**
 * Save (insert or update) the result value for one test row on a
 * main log sheet. Uses the uniq_sheet_test constraint (schema_v8)
 * so repeated saves update in place instead of duplicating rows.
 */
function lab_save_main_sheet_result(PDO $pdo, int $mainLogSheetId, int $testDefinitionId, string $resultValue): void
{
    $stmt = $pdo->prepare(
        "INSERT INTO main_log_sheet_results (main_log_sheet_id, test_definition_id, result_value)
         VALUES (:main_log_sheet_id, :test_definition_id, :result_value)
         ON DUPLICATE KEY UPDATE result_value = VALUES(result_value)"
    );
    $stmt->execute([
        'main_log_sheet_id'  => $mainLogSheetId,
        'test_definition_id' => $testDefinitionId,
        'result_value'       => $resultValue,
    ]);
}

/**
 * All internal log sheet results recorded for one sample (across all
 * test types), with the test type name/unit and transfer status.
 */
function lab_get_sample_internal_results(PDO $pdo, int $sampleId): array
{
    $stmt = $pdo->prepare(
        "SELECT tr.id, tr.result_value, tr.tested_date, tr.is_used_in_main_sheet,
                t.id AS test_type_id, t.name AS test_type_name, t.unit,
                ils.title AS internal_sheet_title
         FROM test_results tr
         JOIN internal_log_sheets ils ON tr.internal_log_sheet_id = ils.id
         JOIN test_types t ON ils.test_type_id = t.id
         WHERE tr.sample_id = :sample_id
         ORDER BY tr.id DESC"
    );
    $stmt->execute(['sample_id' => $sampleId]);
    $rows = $stmt->fetchAll();

    foreach ($rows as &$row) {
        $row['tested_date_fa'] = lab_gregorian_to_jalali($row['tested_date']);
    }

    return $rows;
}

/**
 * Find the single main-log-sheet test definition that this internal
 * test type maps to (matched by name prefix, e.g. test type
 * "دانسیته" -> test definition "دانسیته در 15 درجه سانتیگراد").
 *
 * Returns null when there is no match OR more than one match (e.g.
 * "ویسکوزیته 40" vs "ویسکوزیته 100"), so the caller falls back to
 * asking the user which row to use.
 */
function lab_find_matching_test_definition(PDO $pdo, int $mainLogSheetTypeId, int $testTypeId): ?int
{
    $nameStmt = $pdo->prepare("SELECT name FROM test_types WHERE id = :id");
    $nameStmt->execute(['id' => $testTypeId]);
    $testTypeName = (string)$nameStmt->fetchColumn();
    if ($testTypeName === '') {
        return null;
    }

    $stmt = $pdo->prepare(
        "SELECT id, test_name FROM main_log_sheet_test_definitions
         WHERE main_log_sheet_type_id = :type_id
         ORDER BY row_order"
    );
    $stmt->execute(['type_id' => $mainLogSheetTypeId]);

    $matches = [];
    foreach ($stmt->fetchAll() as $d) {
        if (str_starts_with((string)$d['test_name'], $testTypeName)) {
            $matches[] = (int)$d['id'];
        }
    }

    return count($matches) === 1 ? $matches[0] : null;
}

/**
 * Copy one internal-log-sheet result into the sample's main log sheet
 * (creating the main sheet if needed) and mark it as transferred.
 * Returns false when the result is missing/empty or not owned by the sample.
 */
function lab_transfer_internal_result_to_main_sheet(PDO $pdo, int $sampleId, int $testResultId, int $testDefinitionId): bool
{
    $stmt = $pdo->prepare(
        "SELECT result_value FROM test_results
         WHERE id = :id AND sample_id = :sample_id
         LIMIT 1"
    );
    $stmt->execute(['id' => $testResultId, 'sample_id' => $sampleId]);
    $value = $stmt->fetchColumn();

    if ($value === false || trim((string)$value) === '') {
        return false;
    }

    $mainLogSheetId = lab_get_or_create_main_sheet($pdo, $sampleId);
    lab_save_main_sheet_result($pdo, $mainLogSheetId, $testDefinitionId, trim((string)$value));

    $upd = $pdo->prepare(
        "UPDATE test_results SET is_used_in_main_sheet = 1 WHERE id = :id"
    );
    $upd->execute(['id' => $testResultId]);

    return true;
}

/**
 * Display a unit string with proper superscript exponents,
 * e.g. "g/cm3" -> "g/cm³", "mm2/s" -> "mm²/s".
 * Plain text output (Unicode superscripts); safe to escape further.
 */
function lab_format_unit(?string $unit): string
{
    if ($unit === null || $unit === '') {
        return '';
    }

    $superscripts = [
        '0' => '⁰', '1' => '¹', '2' => '²', '3' => '³', '4' => '⁴',
        '5' => '⁵', '6' => '⁶', '7' => '⁷', '8' => '⁸', '9' => '⁹',
    ];

    return strtr($unit, $superscripts);
}

/**
 * Test definitions (rows) for one main log sheet product type, used
 * by the "محدوده‌های مجاز" (allowed ranges) configuration page.
 */
function lab_get_test_definitions_for_type(PDO $pdo, int $mainLogSheetTypeId): array
{
    $stmt = $pdo->prepare(
        "SELECT id, row_order, test_name, unit, method, test_location,
                limit_new, limit_used, limit_min, limit_max
         FROM main_log_sheet_test_definitions
         WHERE main_log_sheet_type_id = :type_id
         ORDER BY row_order"
    );
    $stmt->execute(['type_id' => $mainLogSheetTypeId]);
    return $stmt->fetchAll();
}

/**
 * For a given internal test type, the allowed-range candidates on
 * EVERY main log sheet type (matched by test-name prefix, same rule
 * as lab_find_matching_test_definition).
 *
 * Returns an array keyed by main_log_sheet_type_id:
 *   [typeId] => [ ['name' => ..., 'min' => float|null, 'max' => float|null], ... ]
 *
 * Used to feed the live red/green check in the internal sheet form.
 */
function lab_get_range_candidates_for_test_type(PDO $pdo, int $testTypeId): array
{
    $nameStmt = $pdo->prepare("SELECT name FROM test_types WHERE id = :id");
    $nameStmt->execute(['id' => $testTypeId]);
    $testTypeName = (string)$nameStmt->fetchColumn();
    if ($testTypeName === '') {
        return [];
    }

    $stmt = $pdo->prepare(
        "SELECT id, main_log_sheet_type_id, test_name, test_location,
                limit_min, limit_max
         FROM main_log_sheet_test_definitions
         ORDER BY main_log_sheet_type_id, row_order"
    );
    $stmt->execute();

    $result = [];
    foreach ($stmt->fetchAll() as $d) {
        if (!str_starts_with((string)$d['test_name'], $testTypeName)) {
            continue;
        }
        $typeId = (int)$d['main_log_sheet_type_id'];
        $result[$typeId][] = [
            'name' => (string)$d['test_name'],
            'location' => (string)($d['test_location'] ?? ''),
            'min' => $d['limit_min'] !== null ? (float)$d['limit_min'] : null,
            'max' => $d['limit_max'] !== null ? (float)$d['limit_max'] : null,
        ];
    }

    return $result;
}
