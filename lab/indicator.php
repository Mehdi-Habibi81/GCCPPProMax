<?php
declare(strict_types=1);

require __DIR__ . '/../auth/config.php'; // provides $pdo, starts session
require __DIR__ . '/Functions.php';

lab_require_login();

$errors = [];
$success = null;
$sampleTypes = lab_get_sample_types($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sampleTypeId     = (int)($_POST['sample_type_id'] ?? 0);
    $sampleName       = trim($_POST['sample_name'] ?? '');
    $quantity         = trim($_POST['quantity'] ?? '');
    $quantityUnit     = trim($_POST['quantity_unit'] ?? '');
    $samplingDateJ    = trim($_POST['sampling_date'] ?? '');
    $deliveryDateJ    = trim($_POST['delivery_date'] ?? '');
    $samplingLocation = trim($_POST['sampling_location'] ?? '');
    $referrer         = trim($_POST['referrer'] ?? '');
    $receiver         = trim($_POST['receiver'] ?? '');

    // Only sample_type_id is mandatory — the sample number is built from it,
    // so a sample can't be registered without knowing its type.
    // Every other field below is optional; the record can be completed later.

    $typeCode = null;
    foreach ($sampleTypes as $t) {
        if ((int)$t['id'] === $sampleTypeId) {
            $typeCode = (int)$t['code'];
            break;
        }
    }
    if ($typeCode === null) {
        $errors[] = 'نوع نمونه را انتخاب کنید (این فیلد برای صدور شماره لازم است).';
    }

    // Optional fields: validate only if the user actually filled them in
    if ($quantity !== '' && !is_numeric($quantity)) {
        $errors[] = 'مقدار نمونه باید عدد باشد.';
    }

    $samplingDateG = null;
    if ($samplingDateJ !== '') {
        $samplingDateG = lab_jalali_to_gregorian($samplingDateJ);
        if ($samplingDateG === null) {
            $errors[] = 'تاریخ نمونه‌گیری معتبر نیست (فرمت: ۱۴۰۴/۰۶/۱۶).';
        }
    }

    $deliveryDateG = null;
    if ($deliveryDateJ !== '') {
        $deliveryDateG = lab_jalali_to_gregorian($deliveryDateJ);
        if ($deliveryDateG === null) {
            $errors[] = 'تاریخ تحویل نمونه معتبر نیست (فرمت: ۱۴۰۴/۰۶/۱۶).';
        }
    }

    if (!$errors) {
        $jalaliYear = lab_current_jalali_year();
        $sampleNumber = lab_generate_sample_number($pdo, $typeCode, $jalaliYear);

        $stmt = $pdo->prepare(
            "INSERT INTO samples
                (sample_number, sample_type_id, sample_name, jalali_year, quantity, quantity_unit,
                 sampling_date, delivery_date, sampling_location, referrer, receiver, status)
             VALUES
                (:sample_number, :sample_type_id, :sample_name, :jalali_year, :quantity, :quantity_unit,
                 :sampling_date, :delivery_date, :sampling_location, :referrer, :receiver, 'in_progress')"
        );
        $stmt->execute([
            'sample_number'     => $sampleNumber,
            'sample_type_id'    => $sampleTypeId,
            'sample_name'       => $sampleName !== '' ? $sampleName : null,
            'jalali_year'       => $jalaliYear,
            'quantity'          => $quantity !== '' ? $quantity : null,
            'quantity_unit'     => $quantityUnit !== '' ? $quantityUnit : null,
            'sampling_date'     => $samplingDateG,
            'delivery_date'     => $deliveryDateG,
            'sampling_location' => $samplingLocation !== '' ? $samplingLocation : null,
            'referrer'          => $referrer !== '' ? $referrer : null,
            'receiver'          => $receiver !== '' ? $receiver : null,
        ]);

        $success = "نمونه با شماره {$sampleNumber} با موفقیت ثبت شد. می‌توانید بقیه‌ی اطلاعات را بعداً تکمیل کنید.";
    }
}

$recentSamples = lab_get_recent_samples($pdo);
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>ثبت نمونه — دفتر اندیکاتور</title>
    <style>
        body { font-family: Tahoma, sans-serif; background:#f7f7f9; margin:0; padding:24px; }
        .card { background:#fff; border-radius:10px; padding:24px; max-width:820px; margin:0 auto 24px; box-shadow:0 1px 4px rgba(0,0,0,.08); }
        h1 { font-size:20px; margin-top:0; }
        .grid { display:grid; grid-template-columns:1fr 1fr; gap:0 20px; }
        label { display:block; margin:12px 0 4px; font-size:14px; color:#333; }
        label .optional { color:#999; font-weight:normal; font-size:12px; }
        input, select { width:100%; padding:8px; border:1px solid #ccc; border-radius:6px; font-size:14px; box-sizing:border-box; }
        button { margin-top:18px; padding:10px 20px; background:#2f6fed; color:#fff; border:none; border-radius:6px; cursor:pointer; font-size:14px; }
        button:hover { background:#255ac2; }
        .msg-error { background:#fdecea; color:#b71c1c; padding:10px 14px; border-radius:6px; margin-bottom:12px; }
        .msg-success { background:#e6f4ea; color:#1e7e34; padding:10px 14px; border-radius:6px; margin-bottom:12px; }
        table { width:100%; border-collapse:collapse; font-size:13px; }
        th, td { padding:8px; border-bottom:1px solid #eee; text-align:right; white-space:nowrap; }
        th { color:#666; font-weight:normal; }
        .tablewrap { overflow-x:auto; }
        .empty-cell { color:#bbb; }
    </style>
</head>
<body>

    <div class="card">
        <h1>ثبت نمونه‌ی جدید (دفتر اندیکاتور)</h1>

        <?php foreach ($errors as $e): ?>
            <div class="msg-error"><?= htmlspecialchars($e) ?></div>
        <?php endforeach; ?>

        <?php if ($success): ?>
            <div class="msg-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <form method="post">
            <div class="grid">
                <div>
                    <label>نوع نمونه</label>
                    <select name="sample_type_id" required>
                        <option value="">— انتخاب کنید —</option>
                        <?php foreach ($sampleTypes as $t): ?>
                            <option value="<?= (int)$t['id'] ?>">
                                <?= sprintf('%02d', $t['code']) ?> — <?= htmlspecialchars($t['name_fa']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label>نام نمونه <span class="optional">(اختیاری)</span></label>
                    <input type="text" name="sample_name">
                </div>

                <div>
                    <label>مقدار نمونه <span class="optional">(اختیاری)</span></label>
                    <input type="text" name="quantity" placeholder="مثلاً 5" inputmode="decimal">
                </div>

                <div>
                    <label>واحد اندازه‌گیری <span class="optional">(اختیاری)</span></label>
                    <input type="text" name="quantity_unit" placeholder="مثلاً لیتر، کیلوگرم">
                </div>

                <div>
                    <label>تاریخ نمونه‌گیری (شمسی) <span class="optional">(اختیاری)</span></label>
                    <input type="text" name="sampling_date" placeholder="۱۴۰۴/۰۶/۱۶">
                </div>

                <div>
                    <label>تاریخ تحویل نمونه (شمسی) <span class="optional">(اختیاری)</span></label>
                    <input type="text" name="delivery_date" placeholder="۱۴۰۴/۰۶/۱۶">
                </div>

                <div>
                    <label>محل نمونه‌گیری <span class="optional">(اختیاری)</span></label>
                    <input type="text" name="sampling_location">
                </div>

                <div></div>

                <div>
                    <label>ارجاع‌کننده <span class="optional">(اختیاری)</span></label>
                    <input type="text" name="referrer">
                </div>

                <div>
                    <label>تحویل‌گیرنده <span class="optional">(اختیاری)</span></label>
                    <input type="text" name="receiver">
                </div>
            </div>

            <button type="submit">ثبت نمونه و صدور شماره</button>
        </form>
    </div>

    <div class="card">
        <h1>نمونه‌های اخیر</h1>
        <div class="tablewrap">
        <table>
            <thead>
                <tr>
                    <th>شماره نمونه</th>
                    <th>نام نمونه</th>
                    <th>نوع</th>
                    <th>مقدار</th>
                    <th>تاریخ نمونه‌گیری</th>
                    <th>تاریخ تحویل</th>
                    <th>محل نمونه‌گیری</th>
                    <th>ارجاع‌کننده</th>
                    <th>تحویل‌گیرنده</th>
                    <th>وضعیت</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentSamples as $s): ?>
                    <tr>
                        <td><?= htmlspecialchars($s['sample_number']) ?></td>
                        <td><?= $s['sample_name'] ? htmlspecialchars($s['sample_name']) : '<span class="empty-cell">—</span>' ?></td>
                        <td><?= htmlspecialchars($s['type_name']) ?></td>
                        <td><?= $s['quantity'] ? htmlspecialchars($s['quantity'] . ' ' . $s['quantity_unit']) : '<span class="empty-cell">—</span>' ?></td>
                        <td><?= $s['sampling_date_fa'] ?: '<span class="empty-cell">—</span>' ?></td>
                        <td><?= $s['delivery_date_fa'] ?: '<span class="empty-cell">—</span>' ?></td>
                        <td><?= $s['sampling_location'] ? htmlspecialchars($s['sampling_location']) : '<span class="empty-cell">—</span>' ?></td>
                        <td><?= $s['referrer'] ? htmlspecialchars($s['referrer']) : '<span class="empty-cell">—</span>' ?></td>
                        <td><?= $s['receiver'] ? htmlspecialchars($s['receiver']) : '<span class="empty-cell">—</span>' ?></td>
                        <td><?= htmlspecialchars($s['status']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$recentSamples): ?>
                    <tr><td colspan="10" style="text-align:center;color:#999;">هنوز نمونه‌ای ثبت نشده است.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>

</body>
</html>