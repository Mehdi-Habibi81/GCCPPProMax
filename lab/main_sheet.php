<?php
declare(strict_types=1);

require __DIR__ . '/../auth/config.php'; // provides $pdo, starts session
require __DIR__ . '/Functions.php';

lab_require_login();

$sampleId = (int)($_GET['sample_id'] ?? $_POST['sample_id'] ?? 0);
$sample = $sampleId ? lab_get_sample_by_id($pdo, $sampleId) : null;

if (!$sample) {
    http_response_code(404);
    $pageTitle = 'خطا';
    $navHidden = true;
    require __DIR__ . '/_header.php';
    echo '<div class="card">نمونه‌ی مورد نظر پیدا نشد. <a href="indicator">بازگشت</a></div>';
    require __DIR__ . '/_footer.php';
    exit;
}

if (empty($sample['main_log_sheet_type_id'])) {
    $pageTitle = 'خطا';
    $navHidden = true;
    require __DIR__ . '/_header.php';
    echo '<div class="card">این نمونه نوع لاگ‌شیت اصلی مشخصی ندارد. <a href="edit_sample?id=' . (int)$sample['id'] . '">ویرایش نمونه</a></div>';
    require __DIR__ . '/_footer.php';
    exit;
}

$pageTitle = 'لاگ‌شیت اصلی — ' . $sample['sample_number'];
$activeNav = 'main';

$mainLogSheetId = lab_get_or_create_main_sheet($pdo, $sampleId);
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['transfer_internal'])) {
        $testResultId    = (int)($_POST['test_result_id'] ?? 0);
        $testDefinitionId = (int)($_POST['test_definition_id'] ?? 0);

        if ($testResultId > 0 && $testDefinitionId > 0
            && lab_transfer_internal_result_to_main_sheet($pdo, $sampleId, $testResultId, $testDefinitionId)
        ) {
            $success = 'نتیجه به این لاگ‌شیت اصلی منتقل شد.';
            lab_log_activity($pdo, 'internal_result_transferred', 'نمونه: ' . $sample['sample_number']);
        } else {
            $success = 'انتقال انجام نشد؛ مجدد تلاش کنید.';
        }
    } else {
        $results = $_POST['results'] ?? [];

        foreach ($results as $testDefinitionId => $value) {
            lab_save_main_sheet_result($pdo, $mainLogSheetId, (int)$testDefinitionId, trim((string)$value));
        }
        lab_log_activity($pdo, 'main_sheet_results_saved', 'نمونه: ' . $sample['sample_number']);

        if (isset($_POST['finalize'])) {
            $stmt = $pdo->prepare(
                "UPDATE main_log_sheets SET status = 'final', compiled_date = CURDATE() WHERE id = :id"
            );
            $stmt->execute(['id' => $mainLogSheetId]);

            $stmt = $pdo->prepare("UPDATE samples SET status = 'completed' WHERE id = :id");
            $stmt->execute(['id' => $sampleId]);

            lab_log_activity($pdo, 'main_sheet_finalized', 'نمونه: ' . $sample['sample_number']);
        }

        if (isset($_POST['mark_sent'])) {
            $cmmsRef = trim($_POST['cmms_reference_no'] ?? '');
            $stmt = $pdo->prepare(
                "UPDATE main_log_sheets
                 SET status = 'sent', sent_to_cmms_date = CURDATE(), cmms_reference_no = :ref
                 WHERE id = :id"
            );
            $stmt->execute([
                'ref' => $cmmsRef !== '' ? $cmmsRef : null,
                'id'  => $mainLogSheetId,
            ]);

            $stmt = $pdo->prepare("UPDATE samples SET status = 'sent' WHERE id = :id");
            $stmt->execute(['id' => $sampleId]);

            lab_log_activity($pdo, 'sent_to_cmms', 'نمونه: ' . $sample['sample_number'] . ' — پیگیری: ' . ($cmmsRef !== '' ? $cmmsRef : '—'));
        }

        $success = 'ذخیره شد.';
    }
}

$rows = lab_get_main_sheet_rows($pdo, $mainLogSheetId, (int)$sample['main_log_sheet_type_id']);

$pendingInternalResults = array_values(array_filter(
    lab_get_sample_internal_results($pdo, $sampleId),
    fn(array $r): bool => !(int)$r['is_used_in_main_sheet']
));

$sheetStmt = $pdo->prepare("SELECT status, compiled_date, sent_to_cmms_date, cmms_reference_no FROM main_log_sheets WHERE id = :id");
$sheetStmt->execute(['id' => $mainLogSheetId]);
$sheetInfo = $sheetStmt->fetch();

$statusLabels = ['draft' => 'در حال تکمیل (پیش‌نویس)', 'final' => 'نهایی‌شده', 'sent' => 'ارسال‌شده به CMMS'];
$statusBadgeClass = match ($sheetInfo['status'] ?? '') {
    'draft' => 'badge-draft',
    'final' => 'badge-final',
    'sent'  => 'badge-sent',
    default => 'badge-warn',
};

require __DIR__ . '/_header.php';
?>

<div class="card" style="max-width:100%;">
    <a class="back-link" href="indicator">← بازگشت به دفتر اندیکاتور</a>
    <h1>لاگ‌شیت اصلی — <?= htmlspecialchars($sample['main_log_sheet_type_name']) ?></h1>

    <p class="muted small">
        شماره نمونه: <b><?= htmlspecialchars($sample['sample_number']) ?></b> —
        وضعیت لاگ‌شیت:
        <span class="badge <?= $statusBadgeClass ?>">
            <?= htmlspecialchars($statusLabels[$sheetInfo['status']] ?? $sheetInfo['status']) ?>
        </span>
        <?php if (!empty($sheetInfo['compiled_date'])): ?>
            — تاریخ نهایی‌شدن: <?= htmlspecialchars(lab_gregorian_to_jalali($sheetInfo['compiled_date'])) ?>
        <?php endif; ?>
    </p>

    <?php if ($success): ?>
        <div class="msg-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <form method="post">
        <input type="hidden" name="sample_id" value="<?= (int)$sampleId ?>">

        <div class="tablewrap">
        <table class="data">
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
                        <td style="white-space:normal;">
                            <?= htmlspecialchars(str_replace(' — NEEDS VERIFICATION', '', $r['test_name'])) ?>
                            <?php if (strpos($r['test_name'], 'NEEDS VERIFICATION') !== false): ?>
                                <div class="small" style="color:#b45309;">⚠ نیاز به تأیید مقدار مجاز</div>
                            <?php endif; ?>
                            <div class="small muted"><?= htmlspecialchars($r['test_location']) ?></div>
                        </td>
                        <td><?= htmlspecialchars(lab_format_unit($r['unit'] ?? '—')) ?></td>
                        <td><?= htmlspecialchars($r['method'] ?? '—') ?></td>
                        <td class="limits small">
                            <?php if ($r['limit_used'] !== null && $r['limit_used'] !== '---'): ?>
                                کارکرده: <?= htmlspecialchars($r['limit_used']) ?><br>
                            <?php endif; ?>
                            <?php if ($r['limit_new'] !== null && $r['limit_new'] !== '---'): ?>
                                نو: <?= htmlspecialchars($r['limit_new']) ?>
                            <?php endif; ?>
                        </td>
                        <td style="min-width:140px;">
                            <input type="text" name="results[<?= (int)$r['test_definition_id'] ?>]"
                                   value="<?= htmlspecialchars($r['result_value'] ?? '') ?>">
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$rows): ?>
                    <tr><td colspan="6" style="text-align:center;color:#9ca3af;">برای این نوع لاگ‌شیت هنوز آزمایشی تعریف نشده است.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        </div>

        <div style="display:flex;gap:10px;margin-top:18px;flex-wrap:wrap;">
            <button type="submit" class="btn">ذخیره‌ی نتایج</button>
            <button type="submit" name="finalize" value="1" class="btn btn-green"
                    onclick="return confirm('بعد از نهایی کردن، لاگ‌شیت آماده‌ی ذخیره به‌صورت Word و ارسال به CMMS می‌شود. ادامه می‌دهید؟');">
                نهایی کردن لاگ‌شیت
            </button>
        </div>
    </form>

    <div style="margin-top:18px;">
        <a href="export_word?sample_id=<?= (int)$sampleId ?>" class="btn btn-gray">دانلود فایل Word</a>
    </div>

    <?php if ($pendingInternalResults): ?>
        <div style="margin-top:24px;padding-top:16px;border-top:1px solid #eef0f3;">
            <h2>انتقال نتایج از لاگ‌شیت‌های داخلی</h2>
            <p class="small muted" style="margin:6px 0 12px;">
                این نتایج برای این نمونه در لاگ‌شیت‌های داخلی ثبت شده و هنوز به این لاگ‌شیت منتقل نشده‌اند.
                برای هر مورد ردیف آزمایش موردنظر را انتخاب و «انتقال» را بزنید.
                ردیف‌هایی که ✓ دارند با نوع آزمایشِ لاگ‌شیت داخلی مطابقت دارند.
            </p>
            <?php if (!$rows): ?>
                <p style="color:#9ca3af;font-size:13px;">برای این نوع لاگ‌شیت هنوز ردیف آزمایشی تعریف نشده است؛ نمی‌توان نتیجه‌ای منتقل کرد.</p>
            <?php else: ?>
            <div class="tablewrap">
            <table class="data">
                <thead>
                    <tr>
                        <th>لاگ‌شیت داخلی</th>
                        <th>نتیجه</th>
                        <th>تاریخ آزمایش</th>
                        <th>انتقال به ردیف آزمایش</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pendingInternalResults as $ir): ?>
                        <tr>
                            <td><?= htmlspecialchars($ir['test_type_name'] . ($ir['unit'] ? ' (' . lab_format_unit($ir['unit']) . ')' : '')) ?></td>
                            <td><?= htmlspecialchars($ir['result_value']) ?></td>
                            <td><?= htmlspecialchars($ir['tested_date_fa']) ?></td>
                            <td>
                                <form method="post" style="display:flex;gap:6px;align-items:center;max-width:560px;">
                                    <input type="hidden" name="sample_id" value="<?= (int)$sampleId ?>">
                                    <input type="hidden" name="test_result_id" value="<?= (int)$ir['id'] ?>">
                                    <select name="test_definition_id" required style="margin:0;">
                                        <option value="">— انتخاب ردیف آزمایش —</option>
                                        <?php foreach ($rows as $r):
                                            $isMatch = str_starts_with((string)$r['test_name'], (string)$ir['test_type_name']);
                                        ?>
                                            <option value="<?= (int)$r['test_definition_id'] ?>">
                                                <?= (int)$r['row_order'] ?> — <?= htmlspecialchars(str_replace(' — NEEDS VERIFICATION', '', $r['test_name'])) ?><?= $isMatch ? ' ✓' : '' ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" name="transfer_internal" value="1" class="btn btn-green btn-sm">انتقال</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if (in_array($sheetInfo['status'], ['final', 'sent'], true)): ?>
        <div style="margin-top:24px;padding-top:16px;border-top:1px solid #eef0f3;">
            <h2>ثبت ارسال به CMMS</h2>
            <?php if (!empty($sheetInfo['sent_to_cmms_date'] ?? null)): ?>
                <p style="color:#1e7e34;font-size:14px;">
                    ✓ در تاریخ <?= htmlspecialchars(lab_gregorian_to_jalali($sheetInfo['sent_to_cmms_date'])) ?>
                    با شماره پیگیری «<?= htmlspecialchars($sheetInfo['cmms_reference_no'] ?? '—') ?>» ارسال شده است.
                </p>
            <?php endif; ?>
            <form method="post" style="max-width:400px;">
                <input type="hidden" name="sample_id" value="<?= (int)$sampleId ?>">
                <label style="display:block;margin-bottom:6px;font-size:14px;">شماره پیگیری CMMS <span class="optional">(اختیاری)</span></label>
                <input type="text" name="cmms_reference_no" value="<?= htmlspecialchars($sheetInfo['cmms_reference_no'] ?? '') ?>">
                <button type="submit" name="mark_sent" value="1" class="btn btn-green">ثبت ارسال به CMMS</button>
            </form>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/_footer.php'; ?>