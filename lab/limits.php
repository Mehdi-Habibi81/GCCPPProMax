<?php
declare(strict_types=1);

require __DIR__ . '/../auth/config.php'; // provides $pdo, starts session
require __DIR__ . '/Functions.php';

lab_require_login();

$pageTitle = 'محدوده‌های مجاز آزمایش‌ها';
$activeNav = 'limits';

$mainLogSheetTypes = lab_get_main_log_sheet_types($pdo);
$selectedTypeId = (int)($_GET['main_log_sheet_type_id'] ?? 0);

$selectedType = null;
foreach ($mainLogSheetTypes as $t) {
    if ((int)$t['id'] === $selectedTypeId) {
        $selectedType = $t;
        break;
    }
}

$errors = [];
$success = null;
$rows = [];

if ($selectedType) {
    $rows = lab_get_test_definitions_for_type($pdo, (int)$selectedType['id']);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $minInputs = $_POST['limit_min'] ?? [];
        $maxInputs = $_POST['limit_max'] ?? [];

        $update = $pdo->prepare(
            "UPDATE main_log_sheet_test_definitions
             SET limit_min = :min, limit_max = :max
             WHERE id = :id"
        );

        foreach ($minInputs as $defId => $minRaw) {
            $defId = (int)$defId;
            if ($defId <= 0) continue;

            $minRaw = trim((string)$minRaw);
            $maxRaw = trim((string)($maxInputs[$defId] ?? ''));

            $minVal = null;
            $maxVal = null;

            if ($minRaw !== '' && !is_numeric($minRaw)) {
                $errors[] = 'مقدار حداقل برای ردیف #' . $defId . ' عددی نیست.';
                continue;
            }
            if ($maxRaw !== '' && !is_numeric($maxRaw)) {
                $errors[] = 'مقدار حداکثر برای ردیف #' . $defId . ' عددی نیست.';
                continue;
            }

            if ($minRaw !== '') $minVal = (float)$minRaw;
            if ($maxRaw !== '') $maxVal = (float)$maxRaw;

            if ($minVal !== null && $maxVal !== null && $minVal > $maxVal) {
                $errors[] = 'در ردیف #' . $defId . ' حداقل از حداکثر بزرگتر است.';
                continue;
            }

            $update->execute(['min' => $minVal, 'max' => $maxVal, 'id' => $defId]);
        }

        if (!$errors) {
            $success = 'محدوده‌های مجاز ذخیره شد.';
            lab_log_activity($pdo, 'limits_updated', 'نوع لاگ‌شیت: ' . $selectedType['code']);
        }

        $rows = lab_get_test_definitions_for_type($pdo, (int)$selectedType['id']);
    }
}

require __DIR__ . '/_header.php';
?>

<div class="card">
    <h1>محدوده‌های مجاز آزمایش‌ها</h1>
    <p class="muted small" style="margin-bottom:14px;">
        برای هر آزمایش عدد حداقل و حداکثر مجاز را تعیین کنید. این مقادیر هنگام ثبت نتیجه در
        لاگ‌شیت داخلی به‌صورت زنده بررسی می‌شوند (اعداد خارج از محدوده قرمز نمایش داده می‌شوند).
        خالی گذاشتن یعنی محدودیتی تعریف نشده.
    </p>

    <form method="get" style="max-width:480px;">
        <label>نوع لاگ‌شیت اصلی (محصول)</label>
        <select name="main_log_sheet_type_id" onchange="this.form.submit()">
            <option value="">— انتخاب کنید —</option>
            <?php foreach ($mainLogSheetTypes as $t): ?>
                <option value="<?= (int)$t['id'] ?>" <?= $selectedTypeId === (int)$t['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($t['code'] . ' — ' . $t['name_fa']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </form>
</div>

<?php if ($selectedType): ?>
    <div class="card">
        <h2>محدوده عددی — <?= htmlspecialchars($selectedType['code'] . ' — ' . $selectedType['name_fa']) ?></h2>

        <?php foreach ($errors as $e): ?>
            <div class="msg-error"><?= htmlspecialchars($e) ?></div>
        <?php endforeach; ?>

        <?php if ($success): ?>
            <div class="msg-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <?php if (!$rows): ?>
            <p class="empty-row">برای این نوع لاگ‌شیت هنوز آزمایشی تعریف نشده است.</p>
        <?php else: ?>
            <form method="post">
                <div class="tablewrap">
                <table class="data">
                    <thead>
                        <tr>
                            <th>ردیف</th>
                            <th>آزمایش</th>
                            <th>واحد</th>
                            <th>روش</th>
                            <th>محدوده تعریف‌شده در سند</th>
                            <th>حداقل مجاز</th>
                            <th>حداکثر مجاز</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $r): ?>
                            <tr>
                                <td><?= (int)$r['row_order'] ?></td>
                                <td style="white-space:normal;">
                                    <?= htmlspecialchars(str_replace(' — NEEDS VERIFICATION', '', $r['test_name'])) ?>
                                    <div class="small muted"><?= htmlspecialchars($r['test_location']) ?></div>
                                </td>
                                <td><?= htmlspecialchars(lab_format_unit($r['unit'] ?? '—')) ?></td>
                                <td><?= htmlspecialchars($r['method'] ?? '—') ?></td>
                                <td class="small">
                                    <?php
                                        $docLimits = [];
                                        if ($r['limit_new'] !== null && $r['limit_new'] !== '---' && $r['limit_new'] !== '') {
                                            $docLimits[] = 'نو: ' . $r['limit_new'];
                                        }
                                        if ($r['limit_used'] !== null && $r['limit_used'] !== '---' && $r['limit_used'] !== '') {
                                            $docLimits[] = 'کارکرده: ' . $r['limit_used'];
                                        }
                                        echo htmlspecialchars($docLimits ? implode('، ', $docLimits) : '—');
                                    ?>
                                </td>
                                <td style="min-width:120px;">
                                    <input type="number" step="any" name="limit_min[<?= (int)$r['id'] ?>]"
                                           value="<?= $r['limit_min'] !== null ? htmlspecialchars((string)$r['limit_min']) : '' ?>"
                                           placeholder="مثلاً 100">
                                </td>
                                <td style="min-width:120px;">
                                    <input type="number" step="any" name="limit_max[<?= (int)$r['id'] ?>]"
                                           value="<?= $r['limit_max'] !== null ? htmlspecialchars((string)$r['limit_max']) : '' ?>"
                                           placeholder="مثلاً 185">
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>

                <button type="submit" class="btn btn-green">ذخیره‌ی محدوده‌ها</button>
            </form>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/_footer.php'; ?>