<?php
declare(strict_types=1);

require __DIR__ . '/../auth/config.php';
require __DIR__ . '/Functions.php';

lab_require_login();

use Morilog\Jalali\CalendarUtils;
use Morilog\Jalali\Jalalian;

// ------------------------------------------------------------
// Helper: convert Persian/Arabic digits to English digits
// ------------------------------------------------------------
function normalize_jalali_digits(string $value): string
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

// ------------------------------------------------------------
// Helper: convert a Jalali date entered by the user to
// Gregorian YYYY-MM-DD for SQL.
//
// Accepted examples:
// 1404/01/01
// 1404-01-01
// ۱۴۰۴/۰۱/۰۱
// ۱۴۰۴-۰۱-۰۱
// ------------------------------------------------------------
function jalali_input_to_gregorian(string $value): ?string
{
    $value = trim($value);

    if ($value === '') {
        return null;
    }

    // Normalize Persian/Arabic digits
    $value = normalize_jalali_digits($value);

    // Normalize separators
    $value = str_replace('-', '/', $value);

    // Must be exactly YYYY/MM/DD
    if (!preg_match('/^(\d{4})\/(\d{1,2})\/(\d{1,2})$/', $value, $matches)) {
        return null;
    }

    $year  = (int)$matches[1];
    $month = (int)$matches[2];
    $day   = (int)$matches[3];

    // Validate actual Jalali date
    if (!CalendarUtils::checkDate($year, $month, $day, true)) {
        return null;
    }

    try {
        $jalali = Jalalian::fromFormat(
            'Y/m/d',
            sprintf('%04d/%02d/%02d', $year, $month, $day)
        );

        return $jalali
            ->toCarbon()
            ->format('Y-m-d');

    } catch (Throwable) {
        return null;
    }
}

// ------------------------------------------------------------
// Helper: normalize a valid Jalali value for displaying it
// back in the form.
// ------------------------------------------------------------
function normalize_jalali_display(string $value): string
{
    $value = trim($value);

    if ($value === '') {
        return '';
    }

    $value = normalize_jalali_digits($value);
    $value = str_replace('-', '/', $value);

    if (preg_match('/^(\d{4})\/(\d{1,2})\/(\d{1,2})$/', $value, $m)) {
        return sprintf(
            '%04d/%02d/%02d',
            (int)$m[1],
            (int)$m[2],
            (int)$m[3]
        );
    }

    return $value;
}

// ------------------------------------------------------------
// Get main log sheet types
// ------------------------------------------------------------
$mainLogSheetTypes = lab_get_main_log_sheet_types($pdo);

$selectedTypeId = (int)($_GET['main_log_sheet_type_id'] ?? 0);
$selectedTestDefId = (int)($_GET['test_definition_id'] ?? 0);

// ------------------------------------------------------------
// Jalali date filters entered by the user
// ------------------------------------------------------------
$dateFromJalali = normalize_jalali_display(
    (string)($_GET['date_from'] ?? '')
);

$dateToJalali = normalize_jalali_display(
    (string)($_GET['date_to'] ?? '')
);

$dateFromGregorian = null;
$dateToGregorian = null;

$dateError = '';

// Convert start date
if ($dateFromJalali !== '') {
    $dateFromGregorian = jalali_input_to_gregorian(
        $dateFromJalali
    );

    if ($dateFromGregorian === null) {
        $dateError = 'تاریخ شروع معتبر نیست. فرمت صحیح: 1404/01/01';
    }
}

// Convert end date
if (
    $dateError === '' &&
    $dateToJalali !== ''
) {
    $dateToGregorian = jalali_input_to_gregorian(
        $dateToJalali
    );

    if ($dateToGregorian === null) {
        $dateError = 'تاریخ پایان معتبر نیست. فرمت صحیح: 1404/01/01';
    }
}

// Validate date range
if (
    $dateError === '' &&
    $dateFromGregorian !== null &&
    $dateToGregorian !== null &&
    $dateFromGregorian > $dateToGregorian
) {
    $dateError = 'تاریخ شروع نمی‌تواند بعد از تاریخ پایان باشد.';
}

// ------------------------------------------------------------
// Load test definitions for selected product
// ------------------------------------------------------------
$testDefinitions = [];

if ($selectedTypeId) {

    $stmt = $pdo->prepare(
        "SELECT
            id,
            test_name,
            unit
         FROM main_log_sheet_test_definitions
         WHERE main_log_sheet_type_id = :type_id
         ORDER BY row_order"
    );

    $stmt->execute([
        'type_id' => $selectedTypeId
    ]);

    $testDefinitions = $stmt->fetchAll();
}

// ------------------------------------------------------------
// Chart data
// ------------------------------------------------------------
$chartLabels = [];
$chartValues = [];
$chartUnit = '';
$skippedNonNumeric = 0;

if (
    $selectedTestDefId &&
    $dateError === ''
) {

    // --------------------------------------------------------
    // Base query
    // --------------------------------------------------------
    $sql = "
        SELECT
            s.sample_number,
            s.sampling_date,
            s.created_at,
            r.result_value,
            d.unit,
            d.test_name
        FROM main_log_sheet_results r

        JOIN main_log_sheets m
            ON r.main_log_sheet_id = m.id

        JOIN samples s
            ON m.sample_id = s.id

        JOIN main_log_sheet_test_definitions d
            ON r.test_definition_id = d.id

        WHERE r.test_definition_id = :test_definition_id

          AND r.result_value IS NOT NULL

          AND r.result_value != ''
    ";

    $params = [
        'test_definition_id' => $selectedTestDefId
    ];

    // --------------------------------------------------------
    // Date FROM
    // --------------------------------------------------------
    if ($dateFromGregorian !== null) {

        $sql .= "
            AND COALESCE(
                s.sampling_date,
                DATE(s.created_at)
            ) >= :date_from
        ";

        $params['date_from'] = $dateFromGregorian;
    }

    // --------------------------------------------------------
    // Date TO
    // --------------------------------------------------------
    if ($dateToGregorian !== null) {

        $sql .= "
            AND COALESCE(
                s.sampling_date,
                DATE(s.created_at)
            ) <= :date_to
        ";

        $params['date_to'] = $dateToGregorian;
    }

    // --------------------------------------------------------
    // Chronological order
    // --------------------------------------------------------
    $sql .= "
        ORDER BY
            COALESCE(
                s.sampling_date,
                DATE(s.created_at)
            ),
            s.id
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $dataRows = $stmt->fetchAll();

    // --------------------------------------------------------
    // Build chart data
    // --------------------------------------------------------
    foreach ($dataRows as $d) {

        if (is_numeric($d['result_value'])) {

            // Prefer actual sampling date
            if (!empty($d['sampling_date'])) {

                $dateLabel = lab_gregorian_to_jalali(
                    $d['sampling_date']
                );

            } elseif (!empty($d['created_at'])) {

                $createdDate = substr(
                    (string)$d['created_at'],
                    0,
                    10
                );

                $dateLabel = lab_gregorian_to_jalali(
                    $createdDate
                );

            } else {

                $dateLabel = $d['sample_number'];
            }

            $chartLabels[] =
                $dateLabel .
                ' (' .
                $d['sample_number'] .
                ')';

            $chartValues[] = (float)$d['result_value'];

            $chartUnit = $d['unit'] ?? '';

        } else {

            $skippedNonNumeric++;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <link
        rel="stylesheet"
        href="/assets/fonts.css"
    >

    <title>آنالیز داده‌ها</title>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>

    <style>

        body {
            font-family:
                'A Iranian Sans',
                Tahoma,
                sans-serif;

            background: #f7f7f9;

            margin: 0;

            padding: 24px;
        }

        .card {
            background: #fff;

            border-radius: 10px;

            padding: 24px;

            max-width: 960px;

            margin:
                0 auto 24px;

            box-shadow:
                0 1px 4px rgba(0, 0, 0, .08);
        }

        h1 {
            font-size: 20px;

            margin-top: 0;
        }

        .back-link {
            display: inline-block;

            margin-bottom: 16px;

            color: #2f6fed;

            text-decoration: none;

            font-size: 14px;
        }

        .grid {
            display: grid;

            grid-template-columns:
                1fr 1fr;

            gap: 0 20px;

            align-items: end;
        }

        label {
            display: block;

            margin:
                12px 0 4px;

            font-size: 14px;

            color: #333;
        }

        select,
        .jalali-date {
            width: 100%;

            padding: 9px 10px;

            border:
                1px solid #ccc;

            border-radius: 6px;

            font-size: 14px;

            box-sizing: border-box;

            background: #fff;

            font-family:
                inherit;
        }

        select:focus,
        .jalali-date:focus {
            outline: none;

            border-color: #2f6fed;

            box-shadow:
                0 0 0 3px
                rgba(47, 111, 237, .10);
        }

        .date-section {
            margin-top: 20px;

            padding-top: 18px;

            border-top:
                1px solid #eee;
        }

        .date-section-title {
            font-size: 15px;

            font-weight: 600;

            color: #333;

            margin-bottom: 4px;
        }

        .date-description {
            color: #777;

            font-size: 12px;

            margin-bottom: 10px;
        }

        .date-grid {
            display: grid;

            grid-template-columns:
                1fr 1fr;

            gap: 0 20px;
        }

        .filter-actions {
            display: flex;

            align-items: center;

            gap: 10px;

            margin-top: 18px;
        }

        button {
            padding:
                10px 20px;

            background: #2f6fed;

            color: #fff;

            border: none;

            border-radius: 6px;

            cursor: pointer;

            font-size: 14px;

            font-family: inherit;
        }

        button:hover {
            background: #2459bd;
        }

        .clear-filter {
            display: inline-block;

            padding:
                9px 16px;

            color: #555;

            background: #f1f1f1;

            border-radius: 6px;

            text-decoration: none;

            font-size: 14px;
        }

        .clear-filter:hover {
            background: #e5e5e5;
        }

        .note {
            color: #856404;

            background: #fff3cd;

            padding:
                8px 12px;

            border-radius: 6px;

            font-size: 13px;

            margin-top: 12px;
        }

        .error {
            color: #842029;

            background: #f8d7da;

            padding:
                10px 12px;

            border-radius: 6px;

            font-size: 13px;

            margin-top: 12px;
        }

        .filter-summary {
            margin-top: 12px;

            color: #666;

            font-size: 13px;
        }

        .empty {
            text-align: center;

            color: #999;

            padding: 24px;
        }

        @media (max-width: 700px) {

            .grid,
            .date-grid {
                grid-template-columns: 1fr;

                gap: 0;
            }

            body {
                padding: 12px;
            }

            .card {
                padding: 18px;
            }

            .filter-actions {
                flex-direction: column;

                align-items: stretch;
            }

            button,
            .clear-filter {
                text-align: center;
            }
        }

    </style>

</head>

<body>

<div class="card">

    <a
        class="back-link"
        href="indicator.php"
    >
        → بازگشت به دفتر اندیکاتور
    </a>

    <h1>
        آنالیز داده‌ها —
        روند تغییرات یک آزمایش در طول زمان
    </h1>

    <form method="get">

        <!-- ================================================
             Product / Main Log Sheet
             ================================================ -->

        <div class="grid">

            <div>

                <label for="main_log_sheet_type_id">
                    نوع لاگ‌شیت اصلی (محصول)
                </label>

                <select
                    id="main_log_sheet_type_id"
                    name="main_log_sheet_type_id"
                    onchange="this.form.submit()"
                >

                    <option value="">
                        — انتخاب کنید —
                    </option>

                    <?php foreach (
                        $mainLogSheetTypes as $t
                    ): ?>

                        <option
                            value="<?= (int)$t['id'] ?>"
                            <?= (
                                $selectedTypeId ===
                                (int)$t['id']
                            )
                                ? 'selected'
                                : '' ?>
                        >
                            <?= htmlspecialchars(
                                $t['name_fa'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>

            <!-- ============================================
                 Test
                 ============================================ -->

            <?php if ($selectedTypeId): ?>

                <div>

                    <label for="test_definition_id">
                        آزمایش
                    </label>

                    <select
                        id="test_definition_id"
                        name="test_definition_id"
                        onchange="this.form.submit()"
                    >

                        <option value="">
                            — انتخاب کنید —
                        </option>

                        <?php foreach (
                            $testDefinitions as $td
                        ): ?>

                            <option
                                value="<?= (int)$td['id'] ?>"
                                <?= (
                                    $selectedTestDefId ===
                                    (int)$td['id']
                                )
                                    ? 'selected'
                                    : '' ?>
                            >
                                <?= htmlspecialchars(
                                    str_replace(
                                        ' — NEEDS VERIFICATION',
                                        '',
                                        $td['test_name']
                                    ),
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

            <?php endif; ?>

        </div>

        <!-- ================================================
             Jalali Date Range
             ================================================ -->

        <div class="date-section">

            <div class="date-section-title">
                فیلتر بازه تاریخی
            </div>

            <div class="date-description">
                تاریخ را به صورت شمسی وارد کنید؛
                مثال: 1404/01/01
            </div>

            <div class="date-grid">

                <div>

                    <label for="date_from">
                        از تاریخ
                    </label>

                    <input
                        type="text"
                        id="date_from"
                        name="date_from"
                        class="jalali-date"
                        inputmode="numeric"
                        autocomplete="off"
                        placeholder="1404/01/01"
                        maxlength="10"
                        value="<?= htmlspecialchars(
                            $dateFromJalali,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                    >

                </div>

                <div>

                    <label for="date_to">
                        تا تاریخ
                    </label>

                    <input
                        type="text"
                        id="date_to"
                        name="date_to"
                        class="jalali-date"
                        inputmode="numeric"
                        autocomplete="off"
                        placeholder="1404/12/29"
                        maxlength="10"
                        value="<?= htmlspecialchars(
                            $dateToJalali,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                    >

                </div>

            </div>

            <?php if ($dateError !== ''): ?>

                <div class="error">
                    <?= htmlspecialchars(
                        $dateError,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </div>

            <?php endif; ?>

            <div class="filter-actions">

                <button type="submit">
                    اعمال فیلتر
                </button>

                <?php if (
                    $dateFromJalali !== '' ||
                    $dateToJalali !== ''
                ): ?>

                    <a
                        class="clear-filter"
                        href="?main_log_sheet_type_id=<?= (int)$selectedTypeId ?>&test_definition_id=<?= (int)$selectedTestDefId ?>"
                    >
                        حذف فیلتر تاریخ
                    </a>

                <?php endif; ?>

            </div>

        </div>

    </form>

</div>


<?php if ($selectedTestDefId): ?>

    <div class="card">

        <?php if ($dateError !== ''): ?>

            <div class="empty">
                ابتدا خطای تاریخ را اصلاح کنید.
            </div>

        <?php elseif ($chartLabels): ?>

            <canvas
                id="trendChart"
                height="90"
            ></canvas>

            <!-- ============================================
                 Active filter summary
                 ============================================ -->

            <?php if (
                $dateFromJalali !== '' ||
                $dateToJalali !== ''
            ): ?>

                <div class="filter-summary">

                    بازه انتخاب‌شده:

                    <?php if (
                        $dateFromJalali !== ''
                    ): ?>

                        از
                        <strong>
                            <?= htmlspecialchars(
                                $dateFromJalali,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </strong>

                    <?php endif; ?>

                    <?php if (
                        $dateFromJalali !== '' &&
                        $dateToJalali !== ''
                    ): ?>

                        تا

                    <?php endif; ?>

                    <?php if (
                        $dateToJalali !== ''
                    ): ?>

                        <strong>
                            <?= htmlspecialchars(
                                $dateToJalali,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </strong>

                    <?php endif; ?>

                </div>

            <?php endif; ?>


            <!-- ============================================
                 Non-numeric results
                 ============================================ -->

            <?php if (
                $skippedNonNumeric > 0
            ): ?>

                <div class="note">

                    <?= (int)$skippedNonNumeric ?>

                    نتیجه غیرعددی
                    (مثل "Trace" یا "Negative")
                    در نمودار نمایش داده نشد،
                    چون قابل رسم روی نمودار خطی نیست.

                </div>

            <?php endif; ?>


            <!-- ============================================
                 Chart
                 ============================================ -->

            <script>

                new Chart(
                    document.getElementById(
                        'trendChart'
                    ),
                    {
                        type: 'line',

                        data: {

                            labels:
                                <?= json_encode(
                                    $chartLabels,
                                    JSON_UNESCAPED_UNICODE
                                ) ?>,

                            datasets: [

                                {
                                    label:
                                        <?= json_encode(
                                            'نتیجه' .
                                            (
                                                $chartUnit
                                                    ? ' (' .
                                                      $chartUnit .
                                                      ')'
                                                    : ''
                                            ),
                                            JSON_UNESCAPED_UNICODE
                                        ) ?>,

                                    data:
                                        <?= json_encode(
                                            $chartValues
                                        ) ?>,

                                    borderColor:
                                        '#2f6fed',

                                    backgroundColor:
                                        'rgba(47,111,237,0.1)',

                                    tension: 0.2,

                                    fill: true
                                }

                            ]
                        },

                        options: {

                            responsive: true,

                            plugins: {

                                legend: {
                                    display: true
                                }

                            },

                            scales: {

                                y: {
                                    beginAtZero: false
                                }

                            }

                        }

                    }
                );

            </script>

        <?php else: ?>

            <div class="empty">

                <?php if (
                    $dateFromJalali !== '' ||
                    $dateToJalali !== ''
                ): ?>

                    در بازهٔ تاریخی انتخاب‌شده،
                    نتیجهٔ عددی برای این آزمایش پیدا نشد.

                <?php else: ?>

                    هنوز نتیجهٔ عددی برای این آزمایش
                    ثبت نشده است.

                <?php endif; ?>

            </div>

        <?php endif; ?>

    </div>

<?php endif; ?>

</body>
</html>
