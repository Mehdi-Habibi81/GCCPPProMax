<?php
declare(strict_types=1);

require __DIR__ . '/../auth/config.php'; // provides $pdo, starts session
require __DIR__ . '/Functions.php';

lab_require_login();

$pageTitle = 'ثبت نمونه — دفتر اندیکاتور';
$activeNav = 'indicator';
$containerClass = 'container container-wide';

$quantityUnits = [
    'لیتر', 'میلی‌لیتر', 'متر مکعب', 'سانتی‌متر مکعب',
    'کیلوگرم', 'گرم', 'میلی‌گرم', 'تن',
    'بشکه', 'قوطی', 'کارتن', 'کیسه', 'عدد',
];

$errors = [];
$success = null;
$sampleTypes = lab_get_sample_types($pdo);
$mainLogSheetTypes = lab_get_main_log_sheet_types($pdo);

$form = [
    'sample_type_id'       => 0,
    'main_log_sheet_type_id' => 0,
    'quantity'             => '',
    'quantity_unit'        => '',
    'sampling_date'        => '',
    'delivery_date'        => '',
    'sampling_location'    => '',
    'referrer'             => '',
    'receiver'             => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form = [
        'sample_type_id'       => (int)($_POST['sample_type_id'] ?? 0),
        'main_log_sheet_type_id' => (int)($_POST['main_log_sheet_type_id'] ?? 0),
        'quantity'             => trim($_POST['quantity'] ?? ''),
        'quantity_unit'        => trim($_POST['quantity_unit'] ?? ''),
        'sampling_date'        => trim($_POST['sampling_date'] ?? ''),
        'delivery_date'        => trim($_POST['delivery_date'] ?? ''),
        'sampling_location'    => trim($_POST['sampling_location'] ?? ''),
        'referrer'             => trim($_POST['referrer'] ?? ''),
        'receiver'             => trim($_POST['receiver'] ?? ''),
    ];

    // Only sample_type_id is mandatory — the sample number is built from it,
    // so a sample can't be registered without knowing its type.
    // Every other field below is optional; the record can be completed later.

    $typeCode = null;
    foreach ($sampleTypes as $t) {
        if ((int)$t['id'] === $form['sample_type_id']) {
            $typeCode = (int)$t['code'];
            break;
        }
    }
    if ($typeCode === null) {
        $errors[] = 'نوع نمونه را انتخاب کنید (این فیلد برای صدور شماره لازم است).';
    }

    $mainLogSheetTypeValid = false;
    foreach ($mainLogSheetTypes as $mlt) {
        if ((int)$mlt['id'] === $form['main_log_sheet_type_id']) {
            $mainLogSheetTypeValid = true;
            break;
        }
    }
    if (!$mainLogSheetTypeValid) {
        $errors[] = 'نوع لاگ‌شیت اصلی را انتخاب کنید.';
    }

    // Optional fields: validate only if the user actually filled them in
    if ($form['quantity'] !== '' && !is_numeric($form['quantity'])) {
        $errors[] = 'مقدار نمونه باید عدد باشد.';
    }

    $samplingDateG = null;
    if ($form['sampling_date'] !== '') {
        $samplingDateG = lab_jalali_to_gregorian($form['sampling_date']);
        if ($samplingDateG === null) {
            $errors[] = 'تاریخ نمونه‌گیری معتبر نیست — از تقویم انتخاب کنید.';
        }
    }

    $deliveryDateG = null;
    if ($form['delivery_date'] !== '') {
        $deliveryDateG = lab_jalali_to_gregorian($form['delivery_date']);
        if ($deliveryDateG === null) {
            $errors[] = 'تاریخ تحویل نمونه معتبر نیست — از تقویم انتخاب کنید.';
        }
    }

    if (!$errors) {
        $jalaliYear = lab_current_jalali_year();
        $sampleNumber = lab_generate_sample_number($pdo, $typeCode, $jalaliYear);

        $stmt = $pdo->prepare(
            "INSERT INTO samples
                (sample_number, sample_type_id, main_log_sheet_type_id, jalali_year, quantity, quantity_unit,
                 sampling_date, delivery_date, sampling_location, referrer, receiver, status)
             VALUES
                (:sample_number, :sample_type_id, :main_log_sheet_type_id, :jalali_year, :quantity, :quantity_unit,
                 :sampling_date, :delivery_date, :sampling_location, :referrer, :receiver, 'in_progress')"
        );
        $stmt->execute([
            'sample_number'          => $sampleNumber,
            'sample_type_id'         => $form['sample_type_id'],
            'main_log_sheet_type_id' => $form['main_log_sheet_type_id'],
            'jalali_year'            => $jalaliYear,
            'quantity'               => $form['quantity'] !== '' ? $form['quantity'] : null,
            'quantity_unit'          => $form['quantity_unit'] !== '' ? $form['quantity_unit'] : null,
            'sampling_date'          => $samplingDateG,
            'delivery_date'          => $deliveryDateG,
            'sampling_location'      => $form['sampling_location'] !== '' ? $form['sampling_location'] : null,
            'referrer'               => $form['referrer'] !== '' ? $form['referrer'] : null,
            'receiver'               => $form['receiver'] !== '' ? $form['receiver'] : null,
        ]);

        $success = "نمونه با شماره {$sampleNumber} با موفقیت ثبت شد. می‌توانید بقیه‌ی اطلاعات را بعداً تکمیل کنید.";
        lab_log_activity($pdo, 'sample_created', 'شماره نمونه: ' . $sampleNumber);

        $form = [
            'sample_type_id'       => 0,
            'main_log_sheet_type_id' => 0,
            'quantity'             => '',
            'quantity_unit'        => '',
            'sampling_date'        => '',
            'delivery_date'        => '',
            'sampling_location'    => '',
            'referrer'             => '',
            'receiver'             => '',
        ];
    }
}

$recentSamples = lab_get_recent_samples($pdo);

require __DIR__ . '/_header.php';
?>

<div class="card" style="max-width:900px;margin:0 auto 24px;">
    <h1>ثبت نمونه‌ی جدید (دفتر اندیکاتور)</h1>

    <?php foreach ($errors as $e): ?>
        <div class="msg-error"><?= htmlspecialchars($e) ?></div>
    <?php endforeach; ?>

    <?php if ($success): ?>
        <div class="msg-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <form method="post">
        <div class="grid-2">
            <div>
                <label>نوع نمونه</label>
                <select name="sample_type_id" required>
                    <option value="">— انتخاب کنید —</option>
                    <?php foreach ($sampleTypes as $t): ?>
                        <option value="<?= (int)$t['id'] ?>" <?= $form['sample_type_id'] === (int)$t['id'] ? 'selected' : '' ?>>
                            <?= sprintf('%02d', $t['code']) ?> — <?= htmlspecialchars($t['name_fa']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label>نوع لاگ‌شیت اصلی</label>
                <select name="main_log_sheet_type_id" required>
                    <option value="">— انتخاب کنید —</option>
                    <?php foreach ($mainLogSheetTypes as $mlt): ?>
                        <option value="<?= (int)$mlt['id'] ?>" <?= $form['main_log_sheet_type_id'] === (int)$mlt['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($mlt['name_fa']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label>مقدار نمونه <span class="optional">(اختیاری)</span></label>
                <input type="text" name="quantity" value="<?= htmlspecialchars($form['quantity']) ?>" placeholder="مثلاً 5" inputmode="decimal">
            </div>

            <div>
                <label>واحد اندازه‌گیری <span class="optional">(اختیاری)</span></label>
                <select name="quantity_unit">
                    <option value="">— انتخاب کنید —</option>
                    <?php foreach ($quantityUnits as $unit): ?>
                        <option value="<?= htmlspecialchars($unit) ?>" <?= $form['quantity_unit'] === $unit ? 'selected' : '' ?>>
                            <?= htmlspecialchars($unit) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label>تاریخ نمونه‌گیری (شمسی) <span class="optional">(اختیاری)</span></label>
                <input type="text" class="jalali-input" name="sampling_date" value="<?= htmlspecialchars($form['sampling_date']) ?>">
            </div>

            <div>
                <label>تاریخ تحویل نمونه (شمسی) <span class="optional">(اختیاری)</span></label>
                <input type="text" class="jalali-input" name="delivery_date" value="<?= htmlspecialchars($form['delivery_date']) ?>">
            </div>

            <div>
                <label>محل نمونه‌گیری <span class="optional">(اختیاری)</span></label>
                <input type="text" name="sampling_location" value="<?= htmlspecialchars($form['sampling_location']) ?>">
            </div>

            <div></div>

            <div>
                <label>ارجاع‌کننده <span class="optional">(اختیاری)</span></label>
                <input type="text" name="referrer" value="<?= htmlspecialchars($form['referrer']) ?>">
            </div>

            <div>
                <label>تحویل‌گیرنده <span class="optional">(اختیاری)</span></label>
                <input type="text" name="receiver" value="<?= htmlspecialchars($form['receiver']) ?>">
            </div>
        </div>

        <button type="submit" class="btn">ثبت نمونه و صدور شماره</button>
    </form>
</div>

<div class="card">
    <h1>نمونه‌های اخیر</h1>
    <div class="tablewrap">
    <table class="data">
        <thead>
            <tr>
                <th>شماره نمونه</th>
                <th>نوع لاگ‌شیت اصلی</th>
                <th>نوع نمونه</th>
                <th>مقدار</th>
                <th>تاریخ نمونه‌گیری</th>
                <th>تاریخ تحویل</th>
                <th>محل نمونه‌گیری</th>
                <th>ارجاع‌کننده</th>
                <th>تحویل‌گیرنده</th>
                <th>وضعیت</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($recentSamples as $s): ?>
                <tr>
                    <td><?= htmlspecialchars($s['sample_number']) ?></td>
                    <td><?= $s['main_log_sheet_type_name'] ? htmlspecialchars($s['main_log_sheet_type_name']) : '<span class="empty-cell">—</span>' ?></td>
                    <td><?= htmlspecialchars($s['type_name']) ?></td>
                    <td><?= $s['quantity'] ? htmlspecialchars($s['quantity'] . ' ' . $s['quantity_unit']) : '<span class="empty-cell">—</span>' ?></td>
                    <td><?= $s['sampling_date_fa'] ?: '<span class="empty-cell">—</span>' ?></td>
                    <td><?= $s['delivery_date_fa'] ?: '<span class="empty-cell">—</span>' ?></td>
                    <td><?= $s['sampling_location'] ? htmlspecialchars($s['sampling_location']) : '<span class="empty-cell">—</span>' ?></td>
                    <td><?= $s['referrer'] ? htmlspecialchars($s['referrer']) : '<span class="empty-cell">—</span>' ?></td>
                    <td><?= $s['receiver'] ? htmlspecialchars($s['receiver']) : '<span class="empty-cell">—</span>' ?></td>
                    <td>
                        <?php
                            $statusBadge = match ($s['status']) {
                                'in_progress' => ['badge-draft', 'در حال تکمیل'],
                                'final'       => ['badge-final', 'ثبت‌شده'],
                                default       => ['badge-warn', htmlspecialchars($s['status'])],
                            };
                        ?>
                        <span class="badge <?= $statusBadge[0] ?>"><?= $statusBadge[1] ?></span>
                    </td>
                    <td class="tbl-links">
                        <a class="tbl-link" href="edit_sample.php?id=<?= (int)$s['id'] ?>">ویرایش</a>
                        <a class="tbl-link-green" href="main_sheet?sample_id=<?= (int)$s['id'] ?>">لاگ‌شیت اصلی</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$recentSamples): ?>
                <tr><td colspan="11" style="text-align:center;color:#9ca3af;">هنوز نمونه‌ای ثبت نشده است.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
    </div>
</div>

<?php require __DIR__ . '/_footer.php'; ?>