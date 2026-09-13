<?php
declare(strict_types=1);

require __DIR__ . '/../auth/config.php'; // provides $pdo, starts session
require __DIR__ . '/functions.php';

lab_require_login();

$sampleId = (int)($_GET['sample_id'] ?? $_POST['sample_id'] ?? 0);
$sample = $sampleId ? lab_get_sample_by_id($pdo, $sampleId) : null;

if (!$sample) {
    http_response_code(404);
    echo 'نمونه‌ی مورد نظر پیدا نشد. <a href="indicator.php">بازگشت</a>';
    exit;
}

if (empty($sample['main_log_sheet_type_id'])) {
    echo 'این نمونه نوع لاگ‌شیت اصلی مشخصی ندارد. <a href="edit_sample.php?id=' . (int)$sample['id'] . '">ویرایش نمونه</a>';
    exit;
}

$mainLogSheetId = lab_get_or_create_main_sheet($pdo, $sampleId);
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $results = $_POST['results'] ?? [];

    foreach ($results as $testDefinitionId => $value) {
        lab_save_main_sheet_result($pdo, $mainLogSheetId, (int)$testDefinitionId, trim((string)$value));
    }

    if (isset($_POST['finalize'])) {
        $stmt = $pdo->prepare(
            "UPDATE main_log_sheets SET status = 'final', compiled_date = CURDATE() WHERE id = :id"
        );
        $stmt->execute(['id' => $mainLogSheetId]);

        $stmt = $pdo->prepare("UPDATE samples SET status = 'completed' WHERE id = :id");
        $stmt->execute(['id' => $sampleId]);
    }

    $success = 'ذخیره شد.';
}

$rows = lab_get_main_sheet_rows($pdo, $mainLogSheetId, (int)$sample['main_log_sheet_type_id']);

$sheetStmt = $pdo->prepare("SELECT status, compiled_date FROM main_log_sheets WHERE id = :id");
$sheetStmt->execute(['id' => $mainLogSheetId]);
$sheetInfo = $sheetStmt->fetch();
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>لاگ‌شیت اصلی — <?= htmlspecialchars($sample['sample_number']) ?></title>
    <style>
        body { font-family: Tahoma, sans-serif; background:#f7f7f9; margin:0; padding:24px; }
        .card { background:#fff; border-radius:10px; padding:24px; max-width:960px; margin:0 auto 24px; box-shadow:0 1px 4px rgba(0,0,0,.08); }
        h1 { font-size:20px; margin-top:0; }
        .back-link { display:inline-block; margin-bottom:16px; color:#2f6fed; text-decoration:none; font-size:14px; }
        .meta { font-size:14px; color:#555; margin-bottom:16px; }
        .meta b { color:#222; }
        .status-badge { display:inline-block; padding:3px 10px; border-radius:12px; font-size:12px; }
        .status-draft { background:#fff3cd; color:#856404; }
        .status-final { background:#d4edda; color:#155724; }
        table { width:100%; border-collapse:collapse; font-size:13px; }
        th, td { padding:8px; border-bottom:1px solid #eee; text-align:right; vertical-align:top; }
        th { color:#666; font-weight:normal; }
        input { width:100%; padding:6px; border:1px solid #ccc; border-radius:6px; font-size:13px; box-sizing:border-box; }
        .limits { font-size:12px; color:#555; }
        .location-badge { font-size:11px; color:#888; }
        .msg-success { background:#e6f4ea; color:#1e7e34; padding:10px 14px; border-radius:6px; margin-bottom:12px; }
        .actions { margin-top:18px; display:flex; gap:10px; }
        button { padding:10px 20px; border:none; border-radius:6px; cursor:pointer; font-size:14px; }
        .btn-save { background:#2f6fed; color:#fff; }
        .btn-save:hover { background:#255ac2; }
        .btn-finalize { background:#1e7e34; color:#fff; }
        .btn-finalize:hover { background:#166028; }
        .needs-verification { color:#b45309; }
    </style>
</head>
<body>

    <div class="card">
        <a class="back-link" href="indicator.php">→ بازگشت به دفتر اندیکاتور</a>
        <h1>لاگ‌شیت اصلی — <?= htmlspecialchars($sample['main_log_sheet_type_name']) ?></h1>

        <div class="meta">
            شماره نمونه: <b><?= htmlspecialchars($sample['sample_number']) ?></b> —
            وضعیت لاگ‌شیت:
            <span class="status-badge <?= $sheetInfo['status'] === 'final' ? 'status-final' : 'status-draft' ?>">
                <?= $sheetInfo['status'] === 'final' ? 'نهایی‌شده' : 'در حال تکمیل (پیش‌نویس)' ?>
            </span>
        </div>

        <?php if ($success): ?>
            <div class="msg-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <form method="post">
            <input type="hidden" name="sample_id" value="<?= (int)$sampleId ?>">

            <table>
                <thead>
                    <tr>
                        <th>ردیف</th>
                        <th>آزمایش</th>
                        <th>واحد</th>
                        <th>روش</th>
                        <th>مقادیر مجاز</th>
                        <th>نتیجه</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <td><?= (int)$r['row_order'] ?></td>
                            <td>
                                <?= htmlspecialchars(str_replace(' — NEEDS VERIFICATION', '', $r['test_name'])) ?>
                                <?php if (strpos($r['test_name'], 'NEEDS VERIFICATION') !== false): ?>
                                    <div class="needs-verification">⚠ نیاز به تأیید مقدار مجاز</div>
                                <?php endif; ?>
                                <div class="location-badge"><?= htmlspecialchars($r['test_location']) ?></div>
                            </td>
                            <td><?= htmlspecialchars($r['unit'] ?? '—') ?></td>
                            <td><?= htmlspecialchars($r['method'] ?? '—') ?></td>
                            <td class="limits">
                                <?php if ($r['limit_used'] !== null && $r['limit_used'] !== '---' ): ?>
                                    کارکرده: <?= htmlspecialchars($r['limit_used']) ?><br>
                                <?php endif; ?>
                                <?php if ($r['limit_new'] !== null): ?>
                                    نو: <?= htmlspecialchars($r['limit_new']) ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <input type="text" name="results[<?= (int)$r['test_definition_id'] ?>]"
                                       value="<?= htmlspecialchars($r['result_value'] ?? '') ?>">
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$rows): ?>
                        <tr><td colspan="6" style="text-align:center;color:#999;">برای این نوع لاگ‌شیت هنوز آزمایشی تعریف نشده است.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <div class="actions">
                <button type="submit" class="btn-save">ذخیره‌ی نتایج</button>
                <button type="submit" name="finalize" value="1" class="btn-finalize"
                        onclick="return confirm('بعد از نهایی کردن، لاگ‌شیت آماده‌ی ذخیره به‌صورت Word و ارسال به CMMS می‌شود. ادامه می‌دهید؟');">
                    نهایی کردن لاگ‌شیت
                </button>
            </div>
        </form>
    </div>

</body>
</html>
