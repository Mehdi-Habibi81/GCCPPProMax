<?php
declare(strict_types=1);

require __DIR__ . '/../auth/config.php'; // provides $pdo, starts session
require __DIR__ . '/Functions.php';

lab_require_login();

$testTypes = lab_get_test_types($pdo);
$testTypeId = (int)($_GET['test_type_id'] ?? 0);

$errors = [];
$success = null;
$sheetId = null;
$selectedType = null;

if ($testTypeId) {
    foreach ($testTypes as $t) {
        if ((int)$t['id'] === $testTypeId) {
            $selectedType = $t;
            break;
        }
    }
}

if ($selectedType) {
    $sheetId = lab_get_or_create_internal_sheet($pdo, $testTypeId);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $sampleId    = (int)($_POST['sample_id'] ?? 0);
        $resultValue = trim($_POST['result_value'] ?? '');
        $testedDateJ = trim($_POST['tested_date'] ?? '');

        if ($sampleId <= 0) {
            $errors[] = 'نمونه را انتخاب کنید.';
        }
        if ($resultValue === '') {
            $errors[] = 'مقدار نتیجه را وارد کنید.';
        }

        $testedDateG = null;
        if ($testedDateJ !== '') {
            $testedDateG = lab_jalali_to_gregorian($testedDateJ);
            if ($testedDateG === null) {
                $errors[] = 'تاریخ آزمایش معتبر نیست (فرمت: ۱۴۰۴/۰۶/۱۶).';
            }
        } else {
            $testedDateG = date('Y-m-d'); // default to today if left blank
        }

        if (!$errors) {
            $stmt = $pdo->prepare(
                "INSERT INTO test_results
                    (sample_id, internal_log_sheet_id, result_value, tested_date)
                 VALUES
                    (:sample_id, :internal_log_sheet_id, :result_value, :tested_date)"
            );
            $stmt->execute([
                'sample_id'             => $sampleId,
                'internal_log_sheet_id' => $sheetId,
                'result_value'          => $resultValue,
                'tested_date'           => $testedDateG,
            ]);

            $success = 'نتیجه با موفقیت ثبت شد.';
        }
    }
}

$samples = $selectedType ? lab_get_samples_for_select($pdo) : [];
$results = $sheetId ? lab_get_results_for_sheet($pdo, $sheetId) : [];
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>لاگ‌شیت داخلی<?= $selectedType ? ' — ' . htmlspecialchars($selectedType['name']) : '' ?></title>
    <style>
        body { font-family: Tahoma, sans-serif; background:#f7f7f9; margin:0; padding:24px; }
        .card { background:#fff; border-radius:10px; padding:24px; max-width:820px; margin:0 auto 24px; box-shadow:0 1px 4px rgba(0,0,0,.08); }
        h1 { font-size:20px; margin-top:0; }
        .type-list { display:flex; flex-wrap:wrap; gap:10px; }
        .type-pill { display:inline-block; padding:10px 18px; border-radius:20px; background:#eef2ff; color:#2f6fed; text-decoration:none; font-size:14px; border:1px solid #dbe4ff; }
        .type-pill.active { background:#2f6fed; color:#fff; }
        .grid { display:grid; grid-template-columns:1fr 1fr; gap:0 20px; }
        label { display:block; margin:12px 0 4px; font-size:14px; color:#333; }
        label .optional { color:#999; font-weight:normal; font-size:12px; }
        input, select { width:100%; padding:8px; border:1px solid #ccc; border-radius:6px; font-size:14px; box-sizing:border-box; }
        button { margin-top:18px; padding:10px 20px; background:#2f6fed; color:#fff; border:none; border-radius:6px; cursor:pointer; font-size:14px; }
        button:hover { background:#255ac2; }
        .msg-error { background:#fdecea; color:#b71c1c; padding:10px 14px; border-radius:6px; margin-bottom:12px; }
        .msg-success { background:#e6f4ea; color:#1e7e34; padding:10px 14px; border-radius:6px; margin-bottom:12px; }
        table { width:100%; border-collapse:collapse; font-size:13px; }
        th, td { padding:8px; border-bottom:1px solid #eee; text-align:right; }
        th { color:#666; font-weight:normal; }
        .back-link { display:inline-block; margin-bottom:16px; color:#2f6fed; text-decoration:none; font-size:14px; }
    </style>
</head>
<body>

    <div class="card">
        <h1>لاگ‌شیت‌های داخلی</h1>
        <div class="type-list">
            <?php foreach ($testTypes as $t): ?>
                <a class="type-pill <?= $testTypeId === (int)$t['id'] ? 'active' : '' ?>"
                   href="internal_sheet.php?test_type_id=<?= (int)$t['id'] ?>">
                    <?= htmlspecialchars($t['name']) ?><?= $t['unit'] ? ' (' . htmlspecialchars($t['unit']) . ')' : '' ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if ($selectedType): ?>
        <div class="card">
            <a class="back-link" href="indicator.php">→ بازگشت به دفتر اندیکاتور</a>
            <h1>ثبت نتیجه — <?= htmlspecialchars($selectedType['name']) ?></h1>

            <?php foreach ($errors as $e): ?>
                <div class="msg-error"><?= htmlspecialchars($e) ?></div>
            <?php endforeach; ?>

            <?php if ($success): ?>
                <div class="msg-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <form method="post">
                <div class="grid">
                    <div>
                        <label>نمونه</label>
                        <select name="sample_id" required>
                            <option value="">— انتخاب کنید —</option>
                            <?php foreach ($samples as $s): ?>
                                <option value="<?= (int)$s['id'] ?>">
                                    <?= htmlspecialchars($s['sample_number']) ?><?= $s['main_log_sheet_type_name'] ? ' — ' . htmlspecialchars($s['main_log_sheet_type_name']) : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label>مقدار نتیجه<?= $selectedType['unit'] ? ' (' . htmlspecialchars($selectedType['unit']) . ')' : '' ?></label>
                        <input type="text" name="result_value" required>
                    </div>

                    <div>
                        <label>تاریخ آزمایش (شمسی) <span class="optional">(اختیاری — پیش‌فرض امروز)</span></label>
                        <input type="text" name="tested_date" placeholder="۱۴۰۴/۰۶/۱۶">
                    </div>
                </div>

                <button type="submit">ثبت نتیجه</button>
            </form>
        </div>

        <div class="card">
            <h1>نتایج ثبت‌شده در این لاگ‌شیت</h1>
            <table>
                <thead>
                    <tr>
                        <th>شماره نمونه</th>
                        <th>نوع لاگ‌شیت اصلی</th>
                        <th>نتیجه</th>
                        <th>تاریخ آزمایش</th>
                        <th>وضعیت انتقال</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($results as $r): ?>
                        <tr>
                            <td><?= htmlspecialchars($r['sample_number']) ?></td>
                            <td><?= $r['main_log_sheet_type_name'] ? htmlspecialchars($r['main_log_sheet_type_name']) : '—' ?></td>
                            <td><?= htmlspecialchars($r['result_value']) ?></td>
                            <td><?= htmlspecialchars($r['tested_date_fa']) ?></td>
                            <td><?= $r['is_used_in_main_sheet'] ? 'منتقل‌شده به لاگ‌شیت اصلی' : 'در انتظار انتقال' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$results): ?>
                        <tr><td colspan="5" style="text-align:center;color:#999;">هنوز نتیجه‌ای ثبت نشده است.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

</body>
</html>