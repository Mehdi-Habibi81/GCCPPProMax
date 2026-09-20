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
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
        '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
        '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
    ]);
}

// ------------------------------------------------------------
// Helper: convert a Jalali date entered by the user to
// Gregorian YYYY-MM-DD for SQL. Accepts 1404/01/01, 1404-01-01,
// ۱۴۰۴/۰۱/۰۱, ۱۴۰۴-۰۱-۰۱ and the datepicker output.
// ------------------------------------------------------------
function jalali_input_to_gregorian(string $value): ?string
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }

    $value = normalize_jalali_digits($value);
    $value = str_replace('-', '/', $value);

    if (!preg_match('/^(\d{4})\/(\d{1,2})\/(\d{1,2})$/', $value, $matches)) {
        return null;
    }

    $year  = (int)$matches[1];
    $month = (int)$matches[2];
    $day   = (int)$matches[3];

    if (!CalendarUtils::checkDate($year, $month, $day, true)) {
        return null;
    }

    try {
        $jalali = Jalalian::fromFormat(
            'Y/m/d',
            sprintf('%04d/%02d/%02d', $year, $month, $day)
        );

        return $jalali->toCarbon()->format('Y-m-d');
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
        return sprintf('%04d/%02d/%02d', (int)$m[1], (int)$m[2], (int)$m[3]);
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
$dateFromJalali = normalize_jalali_display((string)($_GET['date_from'] ?? ''));
$dateToJalali = normalize_jalali_display((string)($_GET['date_to'] ?? ''));

$dateFromGregorian = null;
$dateToGregorian = null;
$dateError = '';

if ($dateFromJalali !== '') {
    $dateFromGregorian = jalali_input_to_gregorian($dateFromJalali);
    if ($dateFromGregorian === null) {
        $dateError = 'تاریخ شروع معتبر نیست. از تقویم انتخاب کنید.';
    }
}

if ($dateError === '' && $dateToJalali !== '') {
    $dateToGregorian = jalali_input_to_gregorian($dateToJalali);
    if ($dateToGregorian === null) {
        $dateError = 'تاریخ پایان معتبر نیست. از تقویم انتخاب کنید.';
    }
}

if ($dateError === ''
    && $dateFromGregorian !== null
    && $dateToGregorian !== null
    && $dateFromGregorian > $dateToGregorian
) {
    $dateError = 'تاریخ شروع نمی‌تواند بعد از تاریخ پایان باشد.';
}

// ------------------------------------------------------------
// Load test definitions for selected product
// ------------------------------------------------------------
$testDefinitions = [];

if ($selectedTypeId) {
    $stmt = $pdo->prepare(
        "SELECT id, test_name, unit
         FROM main_log_sheet_test_definitions
         WHERE main_log_sheet_type_id = :type_id
         ORDER BY row_order"
    );
    $stmt->execute(['type_id' => $selectedTypeId]);
    $testDefinitions = $stmt->fetchAll();
}

// ------------------------------------------------------------
// Chart data
// ------------------------------------------------------------
$chartLabels = [];
$chartValues = [];
$chartUnit = '';
$skippedNonNumeric = 0;

if ($selectedTestDefId && $dateError === '') {
    $sql = "
        SELECT
            s.sample_number,
            s.sampling_date,
            s.created_at,
            r.result_value,
            d.unit,
            d.test_name
        FROM main_log_sheet_results r
        JOIN main_log_sheets m ON r.main_log_sheet_id = m.id
        JOIN samples s ON m.sample_id = s.id
        JOIN main_log_sheet_test_definitions d ON r.test_definition_id = d.id
        WHERE r.test_definition_id = :test_definition_id
          AND r.result_value IS NOT NULL
          AND r.result_value != ''
    ";

    $params = ['test_definition_id' => $selectedTestDefId];

    if ($dateFromGregorian !== null) {
        $sql .= " AND COALESCE(s.sampling_date, DATE(s.created_at)) >= :date_from";
        $params['date_from'] = $dateFromGregorian;
    }

    if ($dateToGregorian !== null) {
        $sql .= " AND COALESCE(s.sampling_date, DATE(s.created_at)) <= :date_to";
        $params['date_to'] = $dateToGregorian;
    }

    $sql .= " ORDER BY COALESCE(s.sampling_date, DATE(s.created_at)), s.id";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $dataRows = $stmt->fetchAll();

    foreach ($dataRows as $d) {
        if (is_numeric($d['result_value'])) {
            if (!empty($d['sampling_date'])) {
                $dateLabel = lab_gregorian_to_jalali($d['sampling_date']);
            } elseif (!empty($d['created_at'])) {
                $dateLabel = lab_gregorian_to_jalali(substr((string)$d['created_at'], 0, 10));
            } else {
                $dateLabel = $d['sample_number'];
            }

            $chartLabels[] = $dateLabel . ' (' . $d['sample_number'] . ')';
            $chartValues[] = (float)$d['result_value'];
            $chartUnit = $d['unit'] ?? '';
        } else {
            $skippedNonNumeric++;
        }
    }
}

$pageTitle = 'آنالیز داده‌ها';
$activeNav = 'analytics';

require __DIR__ . '/_header.php';
?>

<div class="card">
    <h1>آنالیز داده‌ها — روند تغییرات یک آزمایش در طول زمان</h1>

    <form method="get">
        <div class="grid-2">
            <div>
                <label for="main_log_sheet_type_id">نوع لاگ‌شیت اصلی (محصول)</label>
                <select id="main_log_sheet_type_id" name="main_log_sheet_type_id" onchange="this.form.submit()">
                    <option value="">— انتخاب کنید —</option>
                    <?php foreach ($mainLogSheetTypes as $t): ?>
                        <option value="<?= (int)$t['id'] ?>" <?= $selectedTypeId === (int)$t['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($t['name_fa']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <?php if ($selectedTypeId): ?>
                <div>
                    <label for="test_definition_id">آزمایش</label>
                    <select id="test_definition_id" name="test_definition_id" onchange="this.form.submit()">
                        <option value="">— انتخاب کنید —</option>
                        <?php foreach ($testDefinitions as $td): ?>
                            <option value="<?= (int)$td['id'] ?>" <?= $selectedTestDefId === (int)$td['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars(str_replace(' — NEEDS VERIFICATION', '', $td['test_name'])) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
        </div>

        <div class="card" style="margin:20px 0 0;">
            <div style="font-weight:600;margin-bottom:4px;">فیلتر بازه تاریخی</div>
            <div class="muted small" style="margin-bottom:10px;">تاریخ را از تقویم انتخاب کنید؛ مثال: ۱۴۰۴/۰۱/۰۱</div>

            <div class="grid-2">
                <div>
                    <label for="date_from">از تاریخ</label>
                    <input type="text" id="date_from" name="date_from" class="jalali-input"
                           inputmode="numeric" autocomplete="off" maxlength="10"
                           value="<?= htmlspecialchars($dateFromJalali) ?>">
                </div>
                <div>
                    <label for="date_to">تا تاریخ</label>
                    <input type="text" id="date_to" name="date_to" class="jalali-input"
                           inputmode="numeric" autocomplete="off" maxlength="10"
                           value="<?= htmlspecialchars($dateToJalali) ?>">
                </div>
            </div>

            <?php if ($dateError !== ''): ?>
                <div class="msg-error" style="margin-top:12px;"><?= htmlspecialchars($dateError) ?></div>
            <?php endif; ?>

            <div style="display:flex;align-items:center;gap:10px;margin-top:18px;">
                <button type="submit" class="btn">اعمال فیلتر</button>
                <?php if ($dateFromJalali !== '' || $dateToJalali !== ''): ?>
                    <a class="btn btn-gray" href="?main_log_sheet_type_id=<?= (int)$selectedTypeId ?>&test_definition_id=<?= (int)$selectedTestDefId ?>">
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
            <div class="empty-cell" style="text-align:center;padding:24px;">ابتدا خطای تاریخ را اصلاح کنید.</div>

        <?php elseif ($chartLabels): ?>
            <canvas id="trendChart" height="90"></canvas>

            <?php if ($dateFromJalali !== '' || $dateToJalali !== ''): ?>
                <div class="filter-summary muted small" style="margin-top:12px;">
                    بازه انتخاب‌شده:
                    <?php if ($dateFromJalali !== ''): ?> از <strong><?= htmlspecialchars($dateFromJalali) ?></strong><?php endif; ?>
                    <?php if ($dateFromJalali !== '' && $dateToJalali !== ''): ?> تا <?php endif; ?>
                    <?php if ($dateToJalali !== ''): ?><strong><?= htmlspecialchars($dateToJalali) ?></strong><?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($skippedNonNumeric > 0): ?>
                <div class="msg-info" style="margin-top:12px;">
                    <?= (int)$skippedNonNumeric ?> نتیجه غیرعددی (مثل "Trace" یا "Negative") در نمودار نمایش داده نشد، چون قابل رسم روی نمودار خطی نیست.
                </div>
            <?php endif; ?>

            <script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
            <script>
                new Chart(
                    document.getElementById('trendChart'),
                    {
                        type: 'line',
                        data: {
                            labels: <?= json_encode($chartLabels, JSON_UNESCAPED_UNICODE) ?>,
                            datasets: [{
                                label: <?= json_encode('نتیجه' . ($chartUnit ? ' (' . lab_format_unit($chartUnit) . ')' : ''), JSON_UNESCAPED_UNICODE) ?>,
                                data: <?= json_encode($chartValues) ?>,
                                borderColor: '#2f6fed',
                                backgroundColor: 'rgba(47,111,237,0.1)',
                                tension: 0.2,
                                fill: true
                            }]
                        },
                        options: {
                            responsive: true,
                            plugins: { legend: { display: true } },
                            scales: { y: { beginAtZero: false } }
                        }
                    }
                );
            </script>

        <?php else: ?>
            <div class="empty-cell" style="text-align:center;padding:24px;">
                <?php if ($dateFromJalali !== '' || $dateToJalali !== ''): ?>
                    در بازهٔ تاریخی انتخاب‌شده، نتیجهٔ عددی برای این آزمایش پیدا نشد.
                <?php else: ?>
                    هنوز نتیجهٔ عددی برای این آزمایش ثبت نشده است.
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/_footer.php'; ?>