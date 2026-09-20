<?php
declare(strict_types=1);

require __DIR__ . '/../auth/config.php'; // provides $pdo, starts session
require __DIR__ . '/Functions.php';

lab_require_login();

$pageTitle = 'لاگ‌شیت‌های داخلی';
$activeNav = 'internal';

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
    $samples = lab_get_samples_for_select($pdo);
    $rangeCandidates = lab_get_range_candidates_for_test_type($pdo, $testTypeId);

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
                $errors[] = 'تاریخ آزمایش معتبر نیست — از تقویم انتخاب کنید.';
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

            $testResultId = (int)$pdo->lastInsertId();

            $transferredNote = '';
            $sampleRow = lab_get_sample_by_id($pdo, $sampleId);
            if ($sampleRow && !empty($sampleRow['main_log_sheet_type_id'])) {
                $matchingDefId = lab_find_matching_test_definition(
                    $pdo,
                    (int)$sampleRow['main_log_sheet_type_id'],
                    $testTypeId
                );
                if ($matchingDefId !== null
                    && lab_transfer_internal_result_to_main_sheet(
                        $pdo,
                        $sampleId,
                        $testResultId,
                        $matchingDefId
                    )
                ) {
                    $transferredNote = ' — نتیجه به لاگ‌شیت اصلی این نمونه منتقل شد.';
                }
            }

            $success = 'نتیجه با موفقیت ثبت شد.' . $transferredNote;
            lab_log_activity($pdo, 'internal_result_recorded', 'نمونه #' . $sampleId . ' — لاگ‌شیت داخلی #' . $sheetId);
        }
    }

    $results = $sheetId ? lab_get_results_for_sheet($pdo, $sheetId) : [];

    $rangesJson = json_encode($rangeCandidates, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    if ($rangesJson === false) { $rangesJson = '{}'; }

    $samplesMap = [];
    foreach ($samples as $s) {
        $samplesMap[(int)$s['id']] = $s['main_log_sheet_type_id'] !== null ? (int)$s['main_log_sheet_type_id'] : null;
    }
    $samplesJson = json_encode($samplesMap, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    if ($samplesJson === false) { $samplesJson = '{}'; }
}

require __DIR__ . '/_header.php';
?>

<div class="card">
    <h1>لاگ‌شیت‌های داخلی</h1>
    <div class="type-list" style="display:flex;flex-wrap:wrap;gap:10px;">
        <?php foreach ($testTypes as $t): ?>
            <a class="type-pill" style="display:inline-block;padding:10px 18px;border-radius:20px;background:#eef2ff;color:#2563eb;text-decoration:none;font-size:14px;border:1px solid #dbe4ff;<?= $testTypeId === (int)$t['id'] ? 'background:#2563eb;color:#fff;' : '' ?>"
               href="internal_sheet?test_type_id=<?= (int)$t['id'] ?>">
                <?= htmlspecialchars($t['name']) ?><?= $t['unit'] ? ' (' . htmlspecialchars(lab_format_unit($t['unit'])) . ')' : '' ?>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<?php if ($selectedType): ?>
    <div class="card">
        <a class="back-link" href="indicator">← بازگشت به دفتر اندیکاتور</a>
        <h1>ثبت نتیجه — <?= htmlspecialchars($selectedType['name']) ?></h1>

        <?php foreach ($errors as $e): ?>
            <div class="msg-error"><?= htmlspecialchars($e) ?></div>
        <?php endforeach; ?>

        <?php if ($success): ?>
            <div class="msg-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <form method="post">
            <div class="grid-2">
                <div>
                    <label>نمونه</label>
                    <select name="sample_id" id="is-sample-id" required>
                        <option value="">— انتخاب کنید —</option>
                        <?php foreach ($samples as $s): ?>
                            <option value="<?= (int)$s['id'] ?>">
                                <?= htmlspecialchars($s['sample_number']) ?><?= $s['main_log_sheet_type_name'] ? ' — ' . htmlspecialchars($s['main_log_sheet_type_name']) : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label>مقدار نتیجه<?= $selectedType['unit'] ? ' (' . htmlspecialchars(lab_format_unit($selectedType['unit'])) . ')' : '' ?></label>
                    <input type="text" id="is-result" name="result_value" required>
                    <div class="range-hint" id="range-hint"></div>
                </div>

                <div>
                    <label>تاریخ آزمایش (شمسی) <span class="optional">(اختیاری — پیش‌فرض امروز)</span></label>
                    <input type="text" class="jalali-input" name="tested_date" id="is-tested-date">
                </div>
            </div>

            <button type="submit" class="btn">ثبت نتیجه</button>
        </form>
    </div>

    <div class="card">
        <h1>نتایج ثبت‌شده در این لاگ‌شیت</h1>
        <div class="tablewrap">
        <table class="data">
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
                        <td><?= $r['main_log_sheet_type_name'] ? htmlspecialchars($r['main_log_sheet_type_name']) : '<span class="empty-cell">—</span>' ?></td>
                        <td><?= htmlspecialchars($r['result_value']) ?></td>
                        <td><?= htmlspecialchars($r['tested_date_fa']) ?></td>
                        <td><?= $r['is_used_in_main_sheet'] ? '<span class="badge badge-ok">منتقل‌شده به لاگ‌شیت اصلی</span>' : '<span class="badge badge-warn">در انتظار انتقال</span>' ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$results): ?>
                    <tr><td colspan="5" style="text-align:center;color:#9ca3af;">هنوز نتیجه‌ای ثبت نشده است.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>

    <script>
        (function () {
            var RANGES = <?= $rangesJson ?>;
            var SAMPLES = <?= $samplesJson ?>;

            var sampleSel = document.getElementById('is-sample-id');
            var resultInput = document.getElementById('is-result');
            var hint = document.getElementById('range-hint');

            function faNum(str) {
                return String(str).replace(/\d/g, function (d) {
                    return '۰۱۲۳۴۵۶۷۸۹'.charAt(+d);
                });
            }

            function fmtNum(v) {
                var s = String(Number(v));
                return /\./.test(s) ? s.replace(/\.?0+$/, '') : s;
            }

            function evaluate() {
                resultInput.classList.remove('result-invalid', 'result-ok');
                hint.textContent = '';
                hint.className = 'range-hint';

                var raw = resultInput.value.trim().replace(/[۰-۹]/g, function (c) {
                    return '۰۱۲۳۴۵۶۷۸۹'.indexOf(c);
                });
                if (raw === '' || isNaN(Number(raw))) return;

                var value = Number(raw);
                var typeId = SAMPLES[sampleSel.value] || null;
                var candidates = [];
                if (typeId !== null && RANGES[typeId]) {
                    candidates = RANGES[typeId];
                }

                if (!candidates.length) {
                    hint.textContent = 'مطابقتی در لاگ‌شیت اصلی این نوع نمونه یافت نشد.';
                    return;
                }

                if (candidates.length > 1) {
                    // Several definitions match (e.g. viscosity at 40/100 deg).
                    // No red flag here — just show the possible ranges.
                    var parts = candidates.map(function (c) {
                        var r = describe(c);
                        return r ? c.name + ' (' + r + ')' : c.name;
                    });
                    hint.textContent = 'چند تعریف در لاگ‌شیت اصلی: ' + parts.join('، ');
                    return;
                }

                var cand = candidates[0];
                var hasAny = cand.min !== null || cand.max !== null;
                if (!hasAny) return;

                var bad = (cand.min !== null && value < cand.min) || (cand.max !== null && value > cand.max);
                if (bad) {
                    resultInput.classList.add('result-invalid');
                    hint.className = 'range-hint out';
                    hint.textContent = 'خارج از محدوده‌ی مجاز — محدوده: ' + describe(cand) + ' (طبق لاگ‌شیت «' + cand.name + '»)';
                } else {
                    resultInput.classList.add('result-ok');
                    hint.textContent = 'داخل محدوده‌ی مجاز (' + describe(cand) + ')';
                }
            }

            function describe(c) {
                var hasMin = c.min !== null;
                var hasMax = c.max !== null;
                if (hasMin && hasMax) return 'از ' + faNum(fmtNum(c.min)) + ' تا ' + faNum(fmtNum(c.max));
                if (hasMin) return 'حداقل ' + faNum(fmtNum(c.min));
                if (hasMax) return 'حداکثر ' + faNum(fmtNum(c.max));
                return '';
            }

            sampleSel.addEventListener('change', evaluate);
            resultInput.addEventListener('input', evaluate);
        })();
    </script>
<?php endif; ?>

<?php require __DIR__ . '/_footer.php'; ?>