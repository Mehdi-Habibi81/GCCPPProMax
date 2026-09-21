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
 * Is the currently-logged-in user an admin?
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
 * Redirect away to dashboard unless the current user is an admin.
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
 * Record one row in activity_logs.
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
 * Recent activity log entries.
 */
function lab_get_activity_logs(PDO $pdo, int $limit = 200): array
{
    $stmt = $pdo->prepare(
        "SELECT *
         FROM activity_logs
         ORDER BY id DESC
         LIMIT :limit"
    );

    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

/**
 * Recent login attempts.
 */
function lab_get_login_logs(PDO $pdo, int $limit = 200): array
{
    $stmt = $pdo->prepare(
        "SELECT *
         FROM login_logs
         ORDER BY id DESC
         LIMIT :limit"
    );

    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

/**
 * All sample types, ordered by code.
 */
function lab_get_sample_types(PDO $pdo): array
{
    $stmt = $pdo->query(
        "SELECT id, code, name_fa
         FROM sample_types
         ORDER BY code"
    );

    return $stmt->fetchAll();
}

/**
 * Main log sheet product types.
 */
function lab_get_main_log_sheet_types(PDO $pdo): array
{
    $stmt = $pdo->query(
        "SELECT id, code, name_fa
         FROM main_log_sheet_types
         ORDER BY id"
    );

    return $stmt->fetchAll();
}

/**
 * Generate sample number:
 * {jalali_year}-{type_code:02d}-{sequence:03d}
 */
function lab_generate_sample_number(PDO $pdo, int $typeCode, int $jalaliYear): string
{
    $stmt = $pdo->prepare(
        "INSERT INTO sample_number_counters (jalali_year, last_seq)
         VALUES (:jalali_year, 1)
         ON DUPLICATE KEY UPDATE last_seq = LAST_INSERT_ID(last_seq + 1)"
    );

    $stmt->execute([
        'jalali_year' => $jalaliYear
    ]);

    $seq = (int)$pdo->lastInsertId();

    return sprintf(
        '%d-%02d-%03d',
        $jalaliYear,
        $typeCode,
        $seq
    );
}

/**
 * Convert Jalali date to Gregorian.
 *
 * Accepts:
 * 1404/06/16
 * 1404-06-16
 * ۱۴۰۴/۰۶/۱۶
 * ١٤٠٤/٠٦/١٦
 *
 * Invalid text returns null BEFORE entering Morilog/Jalali.
 */
function lab_jalali_to_gregorian(string $jalaliDate): ?string
{
    $normalized = trim($jalaliDate);

    if ($normalized === '') {
        return null;
    }

    // Persian digits -> English
    $normalized = strtr($normalized, [
        '۰' => '0',
        '۱' => '1',
        '۲' => '2',
        '۳' => '3',
        '۴' => '4',
        '۵' => '5',
        '۶' => '6',
        '۷' => '7',
        '۸' => '8',
        '۹' => '9',

        '٠' => '0',
        '١' => '1',
        '٢' => '2',
        '٣' => '3',
        '٤' => '4',
        '٥' => '5',
        '٦' => '6',
        '٧' => '7',
        '٨' => '8',
        '٩' => '9',
    ]);

    $normalized = str_replace('-', '/', $normalized);

    $normalized = preg_replace(
        '/\s+/u',
        '',
        $normalized
    ) ?? '';

    // Reject arbitrary text such as «لیتر».
    if (!preg_match(
        '/^\d{4}\/\d{1,2}\/\d{1,2}$/',
        $normalized
    )) {
        return null;
    }

    try {
        return Jalalian::fromFormat(
            'Y/m/d',
            $normalized
        )
            ->toCarbon()
            ->format('Y-m-d');
    } catch (\Throwable $e) {
        return null;
    }
}

/**
 * Convert Gregorian date to Jalali.
 */
function lab_gregorian_to_jalali(string $gregorianDate): string
{
    try {
        return Jalalian::fromCarbon(
            Carbon::parse($gregorianDate)
        )->format('Y/m/d');
    } catch (\Throwable $e) {
        return $gregorianDate;
    }
}

/**
 * Convert Gregorian datetime to Jalali while keeping the time.
 */
function lab_gregorian_datetime_to_jalali(string $gregorianDatetime): string
{
    if (preg_match(
        '/^(\d{4}-\d{2}-\d{2})(.*)$/',
        trim($gregorianDatetime),
        $m
    )) {
        return lab_gregorian_to_jalali($m[1]) . $m[2];
    }

    return $gregorianDatetime;
}

/**
 * Current Jalali year.
 */
function lab_current_jalali_year(): int
{
    return (int)Jalalian::now()->getYear();
}

/**
 * Fetch one sample by id.
 */
function lab_get_sample_by_id(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare(
        "SELECT s.*,
                st.name_fa AS type_name,
                st.code AS type_code,
                mlt.name_fa AS main_log_sheet_type_name
         FROM samples s
         JOIN sample_types st
              ON s.sample_type_id = st.id
         LEFT JOIN main_log_sheet_types mlt
              ON s.main_log_sheet_type_id = mlt.id
         WHERE s.id = :id
         LIMIT 1"
    );

    $stmt->execute([
        'id' => $id
    ]);

    $row = $stmt->fetch();

    if (!$row) {
        return null;
    }

    $row['sampling_date_fa'] = $row['sampling_date']
        ? lab_gregorian_to_jalali($row['sampling_date'])
        : '';

    $row['delivery_date_fa'] = $row['delivery_date']
        ? lab_gregorian_to_jalali($row['delivery_date'])
        : '';

    return $row;
}

/**
 * Fetch recent samples for indicator.php.
 */
function lab_get_recent_samples(PDO $pdo, int $limit = 50): array
{
    $stmt = $pdo->prepare(
        "SELECT s.id,
                s.sample_number,
                mlt.name_fa AS main_log_sheet_type_name,
                st.name_fa AS type_name,
                s.quantity,
                s.quantity_unit,
                s.sampling_date,
                s.delivery_date,
                s.sampling_location,
                s.referrer,
                s.receiver,
                s.status
         FROM samples s
         JOIN sample_types st
              ON s.sample_type_id = st.id
         LEFT JOIN main_log_sheet_types mlt
              ON s.main_log_sheet_type_id = mlt.id
         ORDER BY s.id DESC
         LIMIT :limit"
    );

    $stmt->bindValue(
        ':limit',
        $limit,
        PDO::PARAM_INT
    );

    $stmt->execute();

    $rows = $stmt->fetchAll();

    foreach ($rows as &$row) {
        $row['sampling_date_fa'] = $row['sampling_date']
            ? lab_gregorian_to_jalali($row['sampling_date'])
            : '';

        $row['delivery_date_fa'] = $row['delivery_date']
            ? lab_gregorian_to_jalali($row['delivery_date'])
            : '';
    }

    unset($row);

    return $rows;
}

/**
 * Look up sample type id by exact Persian name.
 */
function lab_get_sample_type_id_by_name(PDO $pdo, string $name): ?int
{
    $stmt = $pdo->prepare(
        "SELECT id
         FROM sample_types
         WHERE name_fa = :name
         LIMIT 1"
    );

    $stmt->execute([
        'name' => trim($name)
    ]);

    $id = $stmt->fetchColumn();

    return $id !== false
        ? (int)$id
        : null;
}

/**
 * Look up main log sheet type id by exact Persian name.
 */
function lab_get_main_log_sheet_type_id_by_name(
    PDO $pdo,
    string $name
): ?int {
    $stmt = $pdo->prepare(
        "SELECT id
         FROM main_log_sheet_types
         WHERE name_fa = :name
         LIMIT 1"
    );

    $stmt->execute([
        'name' => trim($name)
    ]);

    $id = $stmt->fetchColumn();

    return $id !== false
        ? (int)$id
        : null;
}

/**
 * All samples for Excel export.
 */
function lab_get_all_samples_for_export(PDO $pdo): array
{
    $stmt = $pdo->query(
        "SELECT s.sample_number,
                st.name_fa AS sample_type_name,
                mlt.name_fa AS main_log_sheet_type_name,
                s.quantity,
                s.quantity_unit,
                s.sampling_date,
                s.delivery_date,
                s.sampling_location,
                s.referrer,
                s.receiver,
                s.status
         FROM samples s
         JOIN sample_types st
              ON s.sample_type_id = st.id
         LEFT JOIN main_log_sheet_types mlt
              ON s.main_log_sheet_type_id = mlt.id
         ORDER BY s.id"
    );

    $rows = $stmt->fetchAll();

    foreach ($rows as &$row) {
        $row['sampling_date_fa'] = $row['sampling_date']
            ? lab_gregorian_to_jalali($row['sampling_date'])
            : '';

        $row['delivery_date_fa'] = $row['delivery_date']
            ? lab_gregorian_to_jalali($row['delivery_date'])
            : '';
    }

    unset($row);

    return $rows;
}

/**
 * All internal test types.
 */
function lab_get_test_types(PDO $pdo): array
{
    $stmt = $pdo->query(
        "SELECT id, name, unit
         FROM test_types
         ORDER BY id"
    );

    return $stmt->fetchAll();
}

/**
 * Definitions of all fields for every internal test type.
 */
function lab_get_internal_test_fields(int $testTypeId): array
{
    $defs = [

        // 1 — دانسیته
        1 => [
            'fields' => [
                [
                    'key'   => 'pycnometer_empty_weight',
                    'label' => 'وزن پیکنومتر خالی',
                    'unit'  => 'g',
                ],
                [
                    'key'   => 'pycnometer_sample_weight',
                    'label' => 'وزن پیکنومتر و نمونه',
                    'unit'  => 'g',
                ],
                [
                    'key'   => 'temperature',
                    'label' => 'درجه حرارت',
                    'unit'  => '°C',
                ],
                [
                    'key'   => 'sample_weight',
                    'label' => 'وزن نمونه',
                    'unit'  => 'g',
                ],
            ],
            'result_label' => 'دانسیته در ۱۵ درجه سانتیگراد',
            'result_unit'  => 'g/cm3',
        ],

        // 2 — ویسکوزیته
        2 => [
            'fields' => [
                [
                    'key'   => 'density_at_test_temp',
                    'label' => 'دانسیته در درجه حرارت آزمایش',
                    'unit'  => 'g/cm3',
                ],
                [
                    'key'   => 'avg_dynamic_viscosity',
                    'label' => 'ویسکوزیته دینامیک متوسط',
                    'unit'  => 'mPa.S',
                ],
                [
                    'key'   => 'torque',
                    'label' => 'گشتاور',
                    'unit'  => 'N.Cm',
                ],
                [
                    'key'   => 'temperature',
                    'label' => 'درجه حرارت',
                    'unit'  => '°C',
                ],
                [
                    'key'   => 'speed',
                    'label' => 'سرعت',
                    'unit'  => null,
                ],
                [
                    'key'   => 'instrument_number',
                    'label' => 'شماره (دستگاه)',
                    'unit'  => null,
                ],
            ],
            'result_label' => 'ویسکوزیته سینماتیک',
            'result_unit'  => 'mm2/s',
        ],

        // 3 — توانائی جداسازی هوا
        3 => [
            'fields' => [
                [
                    'key'   => 'initial_density',
                    'label' => 'دانسیته اولیه',
                    'unit'  => null,
                ],
                [
                    'key'   => 'final_density',
                    'label' => 'دانسیته نهایی',
                    'unit'  => null,
                ],
            ],
            'result_label' => 'زمان جداسازی',
            'result_unit'  => 'min',
        ],

        // 4 — توانائی جداسازی آب
        4 => [
            'fields' => [
                [
                    'key'   => 'test_method',
                    'label' => 'روش آزمایش',
                    'unit'  => null,
                ],
                [
                    'key'   => 'collected_water_volume',
                    'label' => 'حجم آب جمع‌آوری‌شده',
                    'unit'  => null,
                ],
            ],
            'result_label' => 'زمان جداسازی',
            'result_unit'  => 'Sec',
        ],

        // 5 — اندازه‌گیری کف
        5 => [
            'fields' => [
                [
                    'key'   => 'report_number',
                    'label' => 'شماره گزارش',
                    'unit'  => null,
                ],

                [
                    'key'   => 'foam_volume_s1',
                    'label' => 'حجم کف — نمونه اول در ۲۴°C',
                    'unit'  => 'cm3',
                ],
                [
                    'key'   => 'foam_stability_s1',
                    'label' => 'پایداری کف — نمونه اول در ۲۴°C',
                    'unit'  => null,
                ],
                [
                    'key'   => 'foam_break_time_s1',
                    'label' => 'زمان از بین رفتن کف — نمونه اول در ۲۴°C',
                    'unit'  => null,
                ],
                [
                    'key'   => 'air_volume_s1',
                    'label' => 'حجم هوای مصرفی — نمونه اول در ۲۴°C',
                    'unit'  => null,
                ],

                [
                    'key'   => 'foam_volume_s2',
                    'label' => 'حجم کف — نمونه دوم در ۹۳.۵°C',
                    'unit'  => 'cm3',
                ],
                [
                    'key'   => 'foam_stability_s2',
                    'label' => 'پایداری کف — نمونه دوم در ۹۳.۵°C',
                    'unit'  => null,
                ],
                [
                    'key'   => 'foam_break_time_s2',
                    'label' => 'زمان از بین رفتن کف — نمونه دوم در ۹۳.۵°C',
                    'unit'  => null,
                ],
                [
                    'key'   => 'air_volume_s2',
                    'label' => 'حجم هوای مصرفی — نمونه دوم در ۹۳.۵°C',
                    'unit'  => null,
                ],

                [
                    'key'   => 'foam_volume_s3',
                    'label' => 'حجم کف — نمونه دوم در ۲۴°C',
                    'unit'  => 'cm3',
                ],
                [
                    'key'   => 'foam_stability_s3',
                    'label' => 'پایداری کف — نمونه دوم در ۲۴°C',
                    'unit'  => null,
                ],
                [
                    'key'   => 'foam_break_time_s3',
                    'label' => 'زمان از بین رفتن کف — نمونه دوم در ۲۴°C',
                    'unit'  => null,
                ],
                [
                    'key'   => 'air_volume_s3',
                    'label' => 'حجم هوای مصرفی — نمونه دوم در ۲۴°C',
                    'unit'  => null,
                ],
            ],
            'result_label' => 'نتیجه‌ی کلی (اختیاری)',
            'result_unit'  => null,
        ],

        // 6 — مقدار آب
        6 => [
            'fields' => [
                [
                    'key'   => 'sample_weight',
                    'label' => 'وزن نمونه',
                    'unit'  => 'g',
                ],
            ],
            'result_label' => 'مقدار آب',
            'result_unit'  => '%wt',
        ],

        // 7 — عدد خنثی‌سازی
        7 => [
            'fields' => [
                [
                    'key'   => 'sample_weight',
                    'label' => 'وزن نمونه',
                    'unit'  => null,
                ],
                [
                    'key'   => 'sample_titration',
                    'label' => 'تیتراسیون نمونه KOH/HCl',
                    'unit'  => null,
                ],
                [
                    'key'   => 'blank_titration',
                    'label' => 'تیتراسیون صفر KOH/HCl',
                    'unit'  => null,
                ],
            ],
            'result_label' => 'عدد خنثی‌سازی',
            'result_unit'  => 'mgKOH/g',
        ],

        // 8 — نقطه ابری شدن
        8 => [
            'fields' => [
                [
                    'key'   => 'bath_temperature',
                    'label' => 'درجه حرارت حمام',
                    'unit'  => '°C',
                ],
            ],
            'result_label' => 'نقطه ابری شدن',
            'result_unit'  => '°C',
        ],

        // 9 — نقطه انجماد
        9 => [
            'fields' => [
                [
                    'key'   => 'bath_temperature',
                    'label' => 'درجه حرارت حمام',
                    'unit'  => '°C',
                ],
            ],
            'result_label' => 'نقطه انجماد',
            'result_unit'  => '°C',
        ],

        // 10 — نقطه ریزش
        10 => [
            'fields' => [
                [
                    'key'   => 'bath_temperature',
                    'label' => 'درجه حرارت حمام',
                    'unit'  => '°C',
                ],
            ],
            'result_label' => 'نقطه ریزش',
            'result_unit'  => '°C',
        ],

        // 11 — خوردگی مس
        11 => [
            'fields' => [
                [
                    'key'   => 'test_duration',
                    'label' => 'مدت آزمایش',
                    'unit'  => null,
                ],
                [
                    'key'   => 'temperature',
                    'label' => 'درجه حرارت',
                    'unit'  => '°C',
                ],
            ],
            'result_label' => 'درجه خوردگی',
            'result_unit'  => null,
        ],

        // 12 — خوردگی فولاد
        12 => [
            'fields' => [
                [
                    'key'   => 'test_duration',
                    'label' => 'مدت آزمایش',
                    'unit'  => null,
                ],
                [
                    'key'   => 'corrosion_status',
                    'label' => 'وضعیت خوردگی',
                    'unit'  => null,
                ],
            ],
            'result_label' => 'درجه خوردگی',
            'result_unit'  => null,
        ],
    ];

    return $defs[$testTypeId] ?? [
        'fields'       => [],
        'result_label' => 'نتیجه',
        'result_unit'  => null,
    ];
}

/**
 * Get or create the single ongoing internal log sheet for a test type.
 */
function lab_get_or_create_internal_sheet(PDO $pdo, int $testTypeId): int
{
    $stmt = $pdo->prepare(
        "SELECT id
         FROM internal_log_sheets
         WHERE test_type_id = :test_type_id
         LIMIT 1"
    );

    $stmt->execute([
        'test_type_id' => $testTypeId
    ]);

    $row = $stmt->fetch();

    if ($row) {
        return (int)$row['id'];
    }

    $typeStmt = $pdo->prepare(
        "SELECT name
         FROM test_types
         WHERE id = :id"
    );

    $typeStmt->execute([
        'id' => $testTypeId
    ]);

    $typeName = $typeStmt->fetchColumn() ?: '';

    $insert = $pdo->prepare(
        "INSERT INTO internal_log_sheets
            (test_type_id, sheet_date, title)
         VALUES
            (:test_type_id, CURDATE(), :title)"
    );

    $insert->execute([
        'test_type_id' => $testTypeId,
        'title'        => 'لاگ‌شیت ' . $typeName,
    ]);

    return (int)$pdo->lastInsertId();
}

/**
 * Samples for internal-sheet result selection.
 */
function lab_get_samples_for_select(PDO $pdo, int $limit = 200): array
{
    $stmt = $pdo->prepare(
        "SELECT s.id,
                s.sample_number,
                s.main_log_sheet_type_id,
                mlt.name_fa AS main_log_sheet_type_name
         FROM samples s
         LEFT JOIN main_log_sheet_types mlt
              ON s.main_log_sheet_type_id = mlt.id
         ORDER BY s.id DESC
         LIMIT :limit"
    );

    $stmt->bindValue(
        ':limit',
        $limit,
        PDO::PARAM_INT
    );

    $stmt->execute();

    return $stmt->fetchAll();
}

/**
 * Results recorded on one internal log sheet.
 *
 * raw_data is decoded into raw_data_decoded for display.
 */
function lab_get_results_for_sheet(PDO $pdo, int $sheetId): array
{
    $stmt = $pdo->prepare(
        "SELECT tr.id,
                tr.result_value,
                tr.raw_data,
                tr.tested_date,
                tr.is_used_in_main_sheet,
                s.sample_number,
                mlt.name_fa AS main_log_sheet_type_name
         FROM test_results tr
         JOIN samples s
              ON tr.sample_id = s.id
         LEFT JOIN main_log_sheet_types mlt
              ON s.main_log_sheet_type_id = mlt.id
         WHERE tr.internal_log_sheet_id = :sheet_id
         ORDER BY tr.id DESC"
    );

    $stmt->execute([
        'sheet_id' => $sheetId
    ]);

    $rows = $stmt->fetchAll();

    foreach ($rows as &$row) {

        $row['tested_date_fa'] =
            lab_gregorian_to_jalali(
                $row['tested_date']
            );

        if (!empty($row['raw_data'])) {
            $decoded = json_decode(
                (string)$row['raw_data'],
                true
            );

            $row['raw_data_decoded'] =
                is_array($decoded)
                    ? $decoded
                    : [];
        } else {
            $row['raw_data_decoded'] = [];
        }
    }

    unset($row);

    return $rows;
}

/**
 * Get or create the main log sheet for a sample.
 */
function lab_get_or_create_main_sheet(PDO $pdo, int $sampleId): int
{
    $stmt = $pdo->prepare(
        "SELECT id
         FROM main_log_sheets
         WHERE sample_id = :sample_id
         LIMIT 1"
    );

    $stmt->execute([
        'sample_id' => $sampleId
    ]);

    $row = $stmt->fetch();

    if ($row) {
        return (int)$row['id'];
    }

    $insert = $pdo->prepare(
        "INSERT INTO main_log_sheets
            (sample_id, compiled_date, status)
         VALUES
            (:sample_id, CURDATE(), 'draft')"
    );

    $insert->execute([
        'sample_id' => $sampleId
    ]);

    return (int)$pdo->lastInsertId();
}

/**
 * Fixed test rows for a sample main log sheet type.
 */
function lab_get_main_sheet_rows(
    PDO $pdo,
    int $mainLogSheetId,
    int $mainLogSheetTypeId
): array {
    $stmt = $pdo->prepare(
        "SELECT d.id AS test_definition_id,
                d.row_order,
                d.test_name,
                d.unit,
                d.method,
                d.limit_new,
                d.limit_used,
                d.test_location,
                r.result_value
         FROM main_log_sheet_test_definitions d
         LEFT JOIN main_log_sheet_results r
                ON r.test_definition_id = d.id
               AND r.main_log_sheet_id = :main_log_sheet_id
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
 * Save a result to a main log sheet.
 */
function lab_save_main_sheet_result(
    PDO $pdo,
    int $mainLogSheetId,
    int $testDefinitionId,
    string $resultValue
): void {
    $stmt = $pdo->prepare(
        "INSERT INTO main_log_sheet_results
            (main_log_sheet_id, test_definition_id, result_value)
         VALUES
            (:main_log_sheet_id, :test_definition_id, :result_value)
         ON DUPLICATE KEY UPDATE
            result_value = VALUES(result_value)"
    );

    $stmt->execute([
        'main_log_sheet_id'  => $mainLogSheetId,
        'test_definition_id' => $testDefinitionId,
        'result_value'       => $resultValue,
    ]);
}

/**
 * All internal test results for one sample.
 */
function lab_get_sample_internal_results(PDO $pdo, int $sampleId): array
{
    $stmt = $pdo->prepare(
        "SELECT tr.id,
                tr.result_value,
                tr.raw_data,
                tr.tested_date,
                tr.is_used_in_main_sheet,
                t.id AS test_type_id,
                t.name AS test_type_name,
                t.unit,
                ils.title AS internal_sheet_title
         FROM test_results tr
         JOIN internal_log_sheets ils
              ON tr.internal_log_sheet_id = ils.id
         JOIN test_types t
              ON ils.test_type_id = t.id
         WHERE tr.sample_id = :sample_id
         ORDER BY tr.id DESC"
    );

    $stmt->execute([
        'sample_id' => $sampleId
    ]);

    $rows = $stmt->fetchAll();

    foreach ($rows as &$row) {

        $row['tested_date_fa'] =
            lab_gregorian_to_jalali(
                $row['tested_date']
            );

        if (!empty($row['raw_data'])) {
            $decoded = json_decode(
                (string)$row['raw_data'],
                true
            );

            $row['raw_data_decoded'] =
                is_array($decoded)
                    ? $decoded
                    : [];
        } else {
            $row['raw_data_decoded'] = [];
        }
    }

    unset($row);

    return $rows;
}

/**
 * Find a unique main-sheet definition matching an internal test type.
 */
function lab_find_matching_test_definition(
    PDO $pdo,
    int $mainLogSheetTypeId,
    int $testTypeId
): ?int {
    $nameStmt = $pdo->prepare(
        "SELECT name
         FROM test_types
         WHERE id = :id"
    );

    $nameStmt->execute([
        'id' => $testTypeId
    ]);

    $testTypeName =
        (string)$nameStmt->fetchColumn();

    if ($testTypeName === '') {
        return null;
    }

    $stmt = $pdo->prepare(
        "SELECT id, test_name
         FROM main_log_sheet_test_definitions
         WHERE main_log_sheet_type_id = :type_id
         ORDER BY row_order"
    );

    $stmt->execute([
        'type_id' => $mainLogSheetTypeId
    ]);

    $matches = [];

    foreach ($stmt->fetchAll() as $d) {

        if (
            str_starts_with(
                (string)$d['test_name'],
                $testTypeName
            )
        ) {
            $matches[] = (int)$d['id'];
        }
    }

    return count($matches) === 1
        ? $matches[0]
        : null;
}

/**
 * Transfer one internal result into a sample main log sheet.
 */
function lab_transfer_internal_result_to_main_sheet(
    PDO $pdo,
    int $sampleId,
    int $testResultId,
    int $testDefinitionId
): bool {
    $stmt = $pdo->prepare(
        "SELECT result_value
         FROM test_results
         WHERE id = :id
           AND sample_id = :sample_id
         LIMIT 1"
    );

    $stmt->execute([
        'id'        => $testResultId,
        'sample_id' => $sampleId,
    ]);

    $value = $stmt->fetchColumn();

    if (
        $value === false ||
        trim((string)$value) === ''
    ) {
        return false;
    }

    $mainLogSheetId =
        lab_get_or_create_main_sheet(
            $pdo,
            $sampleId
        );

    lab_save_main_sheet_result(
        $pdo,
        $mainLogSheetId,
        $testDefinitionId,
        trim((string)$value)
    );

    $upd = $pdo->prepare(
        "UPDATE test_results
         SET is_used_in_main_sheet = 1
         WHERE id = :id"
    );

    $upd->execute([
        'id' => $testResultId
    ]);

    return true;
}

/**
 * Format unit with Unicode superscripts.
 */
function lab_format_unit(?string $unit): string
{
    if ($unit === null || $unit === '') {
        return '';
    }

    $superscripts = [
        '0' => '⁰',
        '1' => '¹',
        '2' => '²',
        '3' => '³',
        '4' => '⁴',
        '5' => '⁵',
        '6' => '⁶',
        '7' => '⁷',
        '8' => '⁸',
        '9' => '⁹',
    ];

    return strtr(
        $unit,
        $superscripts
    );
}

/**
 * Test definitions for one main log sheet type.
 */
function lab_get_test_definitions_for_type(
    PDO $pdo,
    int $mainLogSheetTypeId
): array {
    $stmt = $pdo->prepare(
        "SELECT id,
                row_order,
                test_name,
                unit,
                method,
                test_location,
                limit_new,
                limit_used,
                limit_min,
                limit_max
         FROM main_log_sheet_test_definitions
         WHERE main_log_sheet_type_id = :type_id
         ORDER BY row_order"
    );

    $stmt->execute([
        'type_id' => $mainLogSheetTypeId
    ]);

    return $stmt->fetchAll();
}

/**
 * Range candidates for an internal test type.
 */
function lab_get_range_candidates_for_test_type(
    PDO $pdo,
    int $testTypeId
): array {
    $nameStmt = $pdo->prepare(
        "SELECT name
         FROM test_types
         WHERE id = :id"
    );

    $nameStmt->execute([
        'id' => $testTypeId
    ]);

    $testTypeName =
        (string)$nameStmt->fetchColumn();

    if ($testTypeName === '') {
        return [];
    }

    $stmt = $pdo->prepare(
        "SELECT id,
                main_log_sheet_type_id,
                test_name,
                test_location,
                limit_min,
                limit_max
         FROM main_log_sheet_test_definitions
         ORDER BY main_log_sheet_type_id, row_order"
    );

    $stmt->execute();

    $result = [];

    foreach ($stmt->fetchAll() as $d) {

        if (
            !str_starts_with(
                (string)$d['test_name'],
                $testTypeName
            )
        ) {
            continue;
        }

        $typeId =
            (int)$d['main_log_sheet_type_id'];

        $result[$typeId][] = [
            'name' =>
                (string)$d['test_name'],

            'location' =>
                (string)($d['test_location'] ?? ''),

            'min' =>
                $d['limit_min'] !== null
                    ? (float)$d['limit_min']
                    : null,

            'max' =>
                $d['limit_max'] !== null
                    ? (float)$d['limit_max']
                    : null,
        ];
    }

    return $result;
}
