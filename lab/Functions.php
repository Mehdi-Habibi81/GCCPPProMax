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
        return Jalalian::fromFormat('Y-m-d', $gregorianDate)->format('Y/m/d');
    } catch (\Throwable $e) {
        return $gregorianDate;
    }
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
        "SELECT s.id, s.sample_number, mlt.name_fa AS main_log_sheet_type_name
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