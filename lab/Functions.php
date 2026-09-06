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
 * Generate the sample number as: {jalali_year}-{type_code:02d}-{sequence:03d}
 * e.g. 1405-02-001
 *
 * The sequence is shared by every sample in the Jalali year, regardless
 * of sample type.
 */
function lab_generate_sample_number(PDO $pdo, int $typeCode, int $jalaliYear): string
{
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) AS c
         FROM samples
         WHERE jalali_year = :jalali_year"
    );
    $stmt->execute(['jalali_year' => $jalaliYear]);
    $count = (int) $stmt->fetch()['c'];
    $seq = $count + 1;

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
 * Fetch recent samples (with type name and Jalali dates) for the
 * listing table on indicator.php
 */
function lab_get_recent_samples(PDO $pdo, int $limit = 50): array
{
    $stmt = $pdo->prepare(
        "SELECT s.id, s.sample_number, s.sample_name, st.name_fa AS type_name,
                s.quantity, s.quantity_unit,
                s.sampling_date, s.delivery_date,
                s.sampling_location, s.referrer, s.receiver, s.status
         FROM samples s
         JOIN sample_types st ON s.sample_type_id = st.id
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