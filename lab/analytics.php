<?php
declare(strict_types=1);

require __DIR__ . '/../auth/config.php';
require __DIR__ . '/Functions.php';

lab_require_login();

$mainLogSheetTypes = lab_get_main_log_sheet_types($pdo);
$selectedTypeId = (int)($_GET['main_log_sheet_type_id'] ?? 0);
$selectedTestDefId = (int)($_GET['test_definition_id'] ?? 0);

$testDefinitions = [];
if ($selectedTypeId) {
    $stmt = $pdo->prepare(
        "SELECT id, test_name, unit FROM main_log_sheet_test_definitions
         WHERE main_log_sheet_type_id = :type_id ORDER BY row_order"
    );
    $stmt->execute(['type_id' => $selectedTypeId]);
    $testDefinitions = $stmt->fetchAll();
}

$chartLabels = [];
$chartValues = [];
$chartUnit = '';
$skippedNonNumeric = 0;

if ($selectedTestDefId) {
    $stmt = $pdo->prepare(
        "SELECT s.sample_number, s.sampling_date, r.result_value, d.unit, d.test_name
         FROM main_log_sheet_results r
         JOIN main_log_sheets m ON r.main_log_sheet_id = m.id
         JOIN samples s ON m.sample_id = s.id
         JOIN main_log_sheet_test_definitions d ON r.test_definition_id = d.id
         WHERE r.test_definition_id = :test_definition_id
           AND r.result_value IS NOT NULL AND r.result_value != ''
         ORDER BY COALESCE(s.sampling_date, s.created_at)"
    );
    $stmt->execute(['test_definition_id' => $selectedTestDefId]);
    $dataRows = $stmt->fetchAll();

    foreach ($dataRows as $d) {
        if (is_numeric($d['result_value'])) {
            $dateLabel = $d['sampling_date'] ? lab_gregorian_to_jalali($d['sampling_date']) : $d['sample_number'];
            $chartLabels[] = $dateLabel . ' (' . $d['sample_number'] . ')';
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
    <link rel="stylesheet" href="/assets/fonts.css">
    <title>آنالیز داده‌ها</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
    <style>
        body { font-family: 'IRANSans', Tahoma, sans-serif; background:#f7f7f9; margin:0; padding:24px; }
        .card { background:#fff; border-radius:10px; padding:24px; max-width:960px; margin:0 auto 24px; box-shadow:0 1px 4px rgba(0,0,0,.08); }
        h1 { font-size:20px; margin-top:0; }
        .back-link { display:inline-block; margin-bottom:16px; color:#2f6fed; text-decoration:none; font-size:14px; }
        .grid { display:grid; grid-template-columns:1fr 1fr; gap:0 20px; align-items:end; }
        label { display:block; margin:12px 0 4px; font-size:14px; color:#333; }
        select { width:100%; padding:8px; border:1px solid #ccc; border-radius:6px; font-size:14px; box-sizing:border-box; }
        button { margin-top:18px; padding:10px 20px; background:#2f6fed; color:#fff; border:none; border-radius:6px; cursor:pointer; font-size:14px; }
        .note { color:#856404; background:#fff3cd; padding:8px 12px; border-radius:6px; font-size:13px; margin-top:12px; }
        .empty { text-align:center; color:#999; padding:24px; }
    </style>
</head>
<body>

    <div class="card">
        <a class="back-link" href="indicator.php">→ بازگشت به دفتر اندیکاتور</a>
        <h1>آنالیز داده‌ها — روند تغییرات یک آزمایش در طول زمان</h1>

        <form method="get">
            <div class="grid">
                <div>
                    <label>نوع لاگ‌شیت اصلی (محصول)</label>
                    <select name="main_log_sheet_type_id" onchange="this.form.submit()">
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
                    <label>آزمایش</label>
                    <select name="test_definition_id" onchange="this.form.submit()">
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
        </form>
    </div>

    <?php if ($selectedTestDefId): ?>
        <div class="card">
            <?php if ($chartLabels): ?>
                <canvas id="trendChart" height="90"></canvas>
                <?php if ($skippedNonNumeric > 0): ?>
                    <div class="note">
                        <?= (int)$skippedNonNumeric ?> نتیجه‌ی غیرعددی (مثل "Trace" یا "Negative") در نمودار نشون داده نشد، چون قابل رسم روی نمودار خطی نیست.
                    </div>
                <?php endif; ?>
                <script>
                    new Chart(document.getElementById('trendChart'), {
                        type: 'line',
                        data: {
                            labels: <?= json_encode($chartLabels, JSON_UNESCAPED_UNICODE) ?>,
                            datasets: [{
                                label: <?= json_encode('نتیجه' . ($chartUnit ? ' (' . $chartUnit . ')' : ''), JSON_UNESCAPED_UNICODE) ?>,
                                data: <?= json_encode($chartValues) ?>,
                                borderColor: '#2f6fed',
                                backgroundColor: 'rgba(47,111,237,0.1)',
                                tension: 0.2,
                                fill: true,
                            }]
                        },
                        options: {
                            responsive: true,
                            plugins: { legend: { display: true } },
                            scales: { y: { beginAtZero: false } }
                        }
                    });
                </script>
            <?php else: ?>
                <div class="empty">هنوز نتیجه‌ی عددی برای این آزمایش ثبت نشده است.</div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

</body>
</html>
