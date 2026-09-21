<?php
declare(strict_types=1);

$pageTitle = 'ایمپورت/اکسپورت Excel نمونه‌ها';
$activeNav = 'excel';
$containerClass = 'container container-wide';

require __DIR__ . '/../auth/config.php';
require __DIR__ . '/Functions.php';

lab_require_login();

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

$results = [];
$importedCount = 0;

/**
 * Normalize Persian/Arabic text.
 */
function import_normalize_text(string $value): string
{
    $value = trim($value);

    $value = str_replace(
        [
            'ي',
            'ى',
            'ك',
            'ـ',
            "\xC2\xA0",
        ],
        [
            'ی',
            'ی',
            'ک',
            '',
            ' ',
        ],
        $value
    );

    $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

    return trim($value);
}

/**
 * Convert Persian/Arabic digits to English digits.
 */
function import_normalize_digits(string $value): string
{
    return strtr($value, [
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
}

/**
 * Normalize a header before comparison.
 */
function import_header_key(string $value): string
{
    $value = import_normalize_text($value);

    $value = str_replace(
        [
            '(',
            ')',
            '—',
            '–',
            '-',
            '_',
            ':',
        ],
        ' ',
        $value
    );

    $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

    return trim($value);
}

/**
 * Find a column by header text.
 */
function import_find_header_column(array $headers, string $needle): ?int
{
    $needleKey = import_header_key($needle);

    foreach ($headers as $column => $header) {
        $headerKey = import_header_key((string)$header);

        if ($headerKey === $needleKey) {
            return (int)$column;
        }
    }

    // Second pass: partial match.
    foreach ($headers as $column => $header) {
        $headerKey = import_header_key((string)$header);

        if (
            $headerKey !== ''
            && $needleKey !== ''
            && str_contains($headerKey, $needleKey)
        ) {
            return (int)$column;
        }
    }

    return null;
}

/**
 * Read a cell as formatted/displayed text.
 */
function import_cell_text(
    \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet,
    int $column,
    int $row
): string {
    return trim(
        (string)$sheet
            ->getCellByColumnAndRow($column, $row)
            ->getFormattedValue()
    );
}

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_FILES['excel_file'])
) {
    $file = $_FILES['excel_file'];

    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {

        $results[] = [
            'row' => '-',
            'status' => 'error',
            'message' => 'آپلود فایل ناموفق بود.',
        ];

    } else {

        try {

            /*
             * Basic file validation.
             */
            $originalName = (string)($file['name'] ?? '');
            $extension = strtolower(
                pathinfo($originalName, PATHINFO_EXTENSION)
            );

            if (!in_array($extension, ['xlsx', 'xls'], true)) {

                $results[] = [
                    'row' => '-',
                    'status' => 'error',
                    'message' => 'فقط فایل‌های Excel با پسوند XLSX یا XLS قابل قبول هستند.',
                ];

            } else {

                $spreadsheet = IOFactory::load($file['tmp_name']);

                /*
                 * Prefer the template sheet named "نمونه‌ها".
                 */
                $sheet =
                    $spreadsheet->getSheetByName('نمونه‌ها')
                    ?? $spreadsheet->getActiveSheet();

                $highestRow = $sheet->getHighestDataRow();
                $highestColumnLetter = $sheet->getHighestDataColumn();

                $highestColumn =
                    Coordinate::columnIndexFromString(
                        $highestColumnLetter
                    );

                /*
                 * ---------------------------------------------------------
                 * Read first-row headers.
                 * ---------------------------------------------------------
                 */
                $headers = [];

                for ($col = 1; $col <= $highestColumn; $col++) {
                    $headers[$col] = import_cell_text(
                        $sheet,
                        $col,
                        1
                    );
                }

                /*
                 * Locate Import columns by their actual header names.
                 */
                $sampleTypeCol =
                    import_find_header_column(
                        $headers,
                        'نوع نمونه'
                    );

                $mainLogTypeCol =
                    import_find_header_column(
                        $headers,
                        'نوع لاگ‌شیت اصلی'
                    );

                $quantityCol =
                    import_find_header_column(
                        $headers,
                        'مقدار'
                    );

                $quantityUnitCol =
                    import_find_header_column(
                        $headers,
                        'واحد اندازه‌گیری'
                    );

                $samplingDateCol =
                    import_find_header_column(
                        $headers,
                        'تاریخ نمونه‌گیری'
                    );

                $deliveryDateCol =
                    import_find_header_column(
                        $headers,
                        'تاریخ تحویل'
                    );

                $samplingLocationCol =
                    import_find_header_column(
                        $headers,
                        'محل نمونه‌گیری'
                    );

                $referrerCol =
                    import_find_header_column(
                        $headers,
                        'ارجاع‌کننده'
                    );

                $receiverCol =
                    import_find_header_column(
                        $headers,
                        'تحویل‌گیرنده'
                    );

                /*
                 * Detect export file.
                 *
                 * Export has "شماره نمونه" and "وضعیت".
                 */
                $sampleNumberCol =
                    import_find_header_column(
                        $headers,
                        'شماره نمونه'
                    );

                $statusCol =
                    import_find_header_column(
                        $headers,
                        'وضعیت'
                    );

                if (
                    $sampleNumberCol !== null
                    && $statusCol !== null
                ) {

                    $results[] = [
                        'row' => '-',
                        'status' => 'error',
                        'message' =>
                            'این فایل «خروجی نمونه‌ها» است و برای Import طراحی نشده است. ' .
                            'لطفاً ابتدا «دانلود قالب اکسل» را انتخاب کنید و همان قالب را تکمیل و آپلود نمایید.',
                    ];

                } elseif (
                    $sampleTypeCol === null
                    || $mainLogTypeCol === null
                ) {

                    /*
                     * Backward-compatible A:I fallback.
                     *
                     * This prevents older templates from breaking.
                     */
                    $sampleTypeCol = 1;
                    $mainLogTypeCol = 2;
                    $quantityCol = 3;
                    $quantityUnitCol = 4;
                    $samplingDateCol = 5;
                    $deliveryDateCol = 6;
                    $samplingLocationCol = 7;
                    $referrerCol = 8;
                    $receiverCol = 9;

                }

                /*
                 * Process rows.
                 */
                if (
                    $sampleTypeCol !== null
                    && $mainLogTypeCol !== null
                ) {

                    for (
                        $rowNum = 2;
                        $rowNum <= $highestRow;
                        $rowNum++
                    ) {

                        $sampleTypeName =
                            import_normalize_text(
                                import_cell_text(
                                    $sheet,
                                    $sampleTypeCol,
                                    $rowNum
                                )
                            );

                        $mainLogTypeName =
                            import_normalize_text(
                                import_cell_text(
                                    $sheet,
                                    $mainLogTypeCol,
                                    $rowNum
                                )
                            );

                        $quantity =
                            $quantityCol !== null
                                ? import_normalize_text(
                                    import_cell_text(
                                        $sheet,
                                        $quantityCol,
                                        $rowNum
                                    )
                                )
                                : '';

                        $quantityUnit =
                            $quantityUnitCol !== null
                                ? import_normalize_text(
                                    import_cell_text(
                                        $sheet,
                                        $quantityUnitCol,
                                        $rowNum
                                    )
                                )
                                : '';

                        $samplingDateJ =
                            $samplingDateCol !== null
                                ? import_normalize_text(
                                    import_cell_text(
                                        $sheet,
                                        $samplingDateCol,
                                        $rowNum
                                    )
                                )
                                : '';

                        $deliveryDateJ =
                            $deliveryDateCol !== null
                                ? import_normalize_text(
                                    import_cell_text(
                                        $sheet,
                                        $deliveryDateCol,
                                        $rowNum
                                    )
                                )
                                : '';

                        $samplingLocation =
                            $samplingLocationCol !== null
                                ? import_normalize_text(
                                    import_cell_text(
                                        $sheet,
                                        $samplingLocationCol,
                                        $rowNum
                                    )
                                )
                                : '';

                        $referrer =
                            $referrerCol !== null
                                ? import_normalize_text(
                                    import_cell_text(
                                        $sheet,
                                        $referrerCol,
                                        $rowNum
                                    )
                                )
                                : '';

                        $receiver =
                            $receiverCol !== null
                                ? import_normalize_text(
                                    import_cell_text(
                                        $sheet,
                                        $receiverCol,
                                        $rowNum
                                    )
                                )
                                : '';

                        /*
                         * Skip fully empty rows.
                         */
                        if (
                            $sampleTypeName === ''
                            && $mainLogTypeName === ''
                            && $quantity === ''
                            && $quantityUnit === ''
                            && $samplingDateJ === ''
                            && $deliveryDateJ === ''
                            && $samplingLocation === ''
                            && $referrer === ''
                            && $receiver === ''
                        ) {
                            continue;
                        }

                        $rowErrors = [];

                        /*
                         * -------------------------------------------------
                         * Sample type
                         * -------------------------------------------------
                         */
                        $sampleTypeId =
                            $sampleTypeName !== ''
                                ? lab_get_sample_type_id_by_name(
                                    $pdo,
                                    $sampleTypeName
                                )
                                : null;

                        if (
                            $sampleTypeName === ''
                            || $sampleTypeId === null
                        ) {
                            $rowErrors[] =
                                'نوع نمونه "' .
                                $sampleTypeName .
                                '" شناخته‌شده نیست. ' .
                                'نام دقیق را از شیت «نام‌های مجاز» استفاده کنید.';
                        }

                        /*
                         * -------------------------------------------------
                         * Main log sheet type
                         * -------------------------------------------------
                         */
                        $mainLogTypeId =
                            $mainLogTypeName !== ''
                                ? lab_get_main_log_sheet_type_id_by_name(
                                    $pdo,
                                    $mainLogTypeName
                                )
                                : null;

                        if (
                            $mainLogTypeName === ''
                            || $mainLogTypeId === null
                        ) {
                            $rowErrors[] =
                                'نوع لاگ‌شیت اصلی "' .
                                $mainLogTypeName .
                                '" شناخته‌شده نیست.';
                        }

                        /*
                         * -------------------------------------------------
                         * Quantity
                         * -------------------------------------------------
                         */
                        $quantityNormalized =
                            import_normalize_digits(
                                $quantity
                            );

                        if (
                            $quantityNormalized !== ''
                            && !is_numeric($quantityNormalized)
                        ) {
                            $rowErrors[] =
                                'مقدار نمونه باید عدد باشد: ' .
                                $quantity;
                        }

                        /*
                         * -------------------------------------------------
                         * Sampling date
                         * -------------------------------------------------
                         */
                        $samplingDateG = null;

                        if ($samplingDateJ !== '') {

                            $samplingDateG =
                                lab_jalali_to_gregorian(
                                    import_normalize_digits(
                                        $samplingDateJ
                                    )
                                );

                            if ($samplingDateG === null) {
                                $rowErrors[] =
                                    'تاریخ نمونه‌گیری معتبر نیست: ' .
                                    $samplingDateJ .
                                    ' — فرمت صحیح: ۱۴۰۴/۰۶/۱۶';
                            }
                        }

                        /*
                         * -------------------------------------------------
                         * Delivery date
                         * -------------------------------------------------
                         */
                        $deliveryDateG = null;

                        if ($deliveryDateJ !== '') {

                            $deliveryDateG =
                                lab_jalali_to_gregorian(
                                    import_normalize_digits(
                                        $deliveryDateJ
                                    )
                                );

                            if ($deliveryDateG === null) {
                                $rowErrors[] =
                                    'تاریخ تحویل معتبر نیست: ' .
                                    $deliveryDateJ .
                                    ' — فرمت صحیح: ۱۴۰۴/۰۶/۱۶';
                            }
                        }

                        /*
                         * Stop this row if validation failed.
                         */
                        if ($rowErrors) {

                            $results[] = [
                                'row' => $rowNum,
                                'status' => 'error',
                                'message' => implode(
                                    ' / ',
                                    $rowErrors
                                ),
                            ];

                            continue;
                        }

                        /*
                         * Current Jalali year.
                         */
                        $jalaliYear =
                            lab_current_jalali_year();

                        /*
                         * Get sample type code for number generation.
                         */
                        $typeCodeStmt = $pdo->prepare(
                            "SELECT code
                             FROM sample_types
                             WHERE id = :id
                             LIMIT 1"
                        );

                        $typeCodeStmt->execute([
                            'id' => $sampleTypeId,
                        ]);

                        $typeCodeValue =
                            $typeCodeStmt->fetchColumn();

                        if ($typeCodeValue === false) {

                            $results[] = [
                                'row' => $rowNum,
                                'status' => 'error',
                                'message' =>
                                    'کد نوع نمونه پیدا نشد.',
                            ];

                            continue;
                        }

                        $typeCode =
                            (int)$typeCodeValue;

                        /*
                         * Generate unique sample number.
                         */
                        $sampleNumber =
                            lab_generate_sample_number(
                                $pdo,
                                $typeCode,
                                $jalaliYear
                            );

                        /*
                         * Insert sample.
                         */
                        $stmt = $pdo->prepare(
                            "INSERT INTO samples
                                (
                                    sample_number,
                                    sample_type_id,
                                    main_log_sheet_type_id,
                                    jalali_year,
                                    quantity,
                                    quantity_unit,
                                    sampling_date,
                                    delivery_date,
                                    sampling_location,
                                    referrer,
                                    receiver,
                                    status
                                )
                             VALUES
                                (
                                    :sample_number,
                                    :sample_type_id,
                                    :main_log_sheet_type_id,
                                    :jalali_year,
                                    :quantity,
                                    :quantity_unit,
                                    :sampling_date,
                                    :delivery_date,
                                    :sampling_location,
                                    :referrer,
                                    :receiver,
                                    'in_progress'
                                )"
                        );

                        $stmt->execute([
                            'sample_number' =>
                                $sampleNumber,

                            'sample_type_id' =>
                                $sampleTypeId,

                            'main_log_sheet_type_id' =>
                                $mainLogTypeId,

                            'jalali_year' =>
                                $jalaliYear,

                            'quantity' =>
                                $quantityNormalized !== ''
                                    ? $quantityNormalized
                                    : null,

                            'quantity_unit' =>
                                $quantityUnit !== ''
                                    ? $quantityUnit
                                    : null,

                            'sampling_date' =>
                                $samplingDateG,

                            'delivery_date' =>
                                $deliveryDateG,

                            'sampling_location' =>
                                $samplingLocation !== ''
                                    ? $samplingLocation
                                    : null,

                            'referrer' =>
                                $referrer !== ''
                                    ? $referrer
                                    : null,

                            'receiver' =>
                                $receiver !== ''
                                    ? $receiver
                                    : null,
                        ]);

                        $importedCount++;

                        $results[] = [
                            'row' => $rowNum,
                            'status' => 'ok',
                            'message' =>
                                'ثبت شد با شماره ' .
                                $sampleNumber,
                        ];
                    }
                }

                /*
                 * Log successful batch import.
                 */
                if ($importedCount > 0) {

                    lab_log_activity(
                        $pdo,
                        'samples_imported',
                        $importedCount .
                        ' نمونه از طریق اکسل'
                    );
                }
            }

        } catch (\Throwable $e) {

            error_log(
                'Excel import error: ' .
                $e->getMessage()
            );

            $results[] = [
                'row' => '-',
                'status' => 'error',
                'message' =>
                    'خطا در خواندن یا پردازش فایل اکسل. ' .
                    'فرمت فایل و قالب دانلودشده را بررسی کنید.',
            ];
        }
    }
}

require __DIR__ . '/_header.php';
?>

<div class="card">

    <a class="back-link" href="indicator">
        ← بازگشت به دفتر اندیکاتور
    </a>

    <h1>ایمپورت گروهی نمونه‌ها از Excel</h1>

    <p class="muted">
        ابتدا قالب Excel را دانلود کنید، اطلاعات را طبق ستون‌های آن
        وارد کنید و سپس همان فایل را برای ثبت گروهی آپلود نمایید.
    </p>

    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:16px;">

        <a
            class="btn btn-gray"
            href="download_import_template"
        >
            دانلود قالب Excel
        </a>

        <a
            class="btn btn-gray"
            href="export_samples"
        >
            خروجی Excel نمونه‌های فعلی
        </a>

    </div>

    <form
        method="post"
        enctype="multipart/form-data"
        style="margin-top:24px;"
    >

        <label for="excel_file">
            فایل Excel
        </label>

        <input
            id="excel_file"
            type="file"
            name="excel_file"
            accept=".xlsx,.xls"
            required
        >

        <button
            type="submit"
            class="btn"
        >
            آپلود و ثبت
        </button>

    </form>

    <?php if ($results): ?>

        <div class="summary" style="margin-top:20px;">

            <b><?= (int)$importedCount ?></b>
            ردیف با موفقیت ثبت شد.

            از مجموع

            <b><?= count($results) ?></b>

            ردیف بررسی‌شده.

        </div>

        <div class="tablewrap" style="margin-top:12px;">

            <table class="data">

                <thead>
                    <tr>
                        <th>ردیف</th>
                        <th>وضعیت</th>
                        <th>پیام</th>
                    </tr>
                </thead>

                <tbody>

                <?php foreach ($results as $r): ?>

                    <tr>

                        <td>
                            <?= htmlspecialchars(
                                (string)$r['row'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </td>

                        <td>

                            <?php if ($r['status'] === 'ok'): ?>

                                <span class="badge badge-ok">
                                    موفق
                                </span>

                            <?php else: ?>

                                <span class="badge badge-warn">
                                    خطا
                                </span>

                            <?php endif; ?>

                        </td>

                        <td>
                            <?= htmlspecialchars(
                                (string)$r['message'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </td>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    <?php endif; ?>

</div>

<?php require __DIR__ . '/_footer.php'; ?>
