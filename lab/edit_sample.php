<?php
declare(strict_types=1);

require __DIR__ . '/../auth/config.php'; // provides $pdo, starts session
require __DIR__ . '/Functions.php';

lab_require_login();

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) {
    header('Location: /lab/indicator');
    exit;
}

$sample = lab_get_sample_by_id($pdo, $id);
if (!$sample) {
    header('Location: /lab/indicator');
    exit;
}

$pageTitle = 'ویرایش نمونه — ' . $sample['sample_number'];
$activeNav = 'indicator';

$quantityUnits = [
    'لیتر', 'میلی‌لیتر', 'متر مکعب', 'سانتی‌متر مکعب',
    'کیلوگرم', 'گرم', 'میلی‌گرم', 'تن',
    'بشکه', 'قوطی', 'کارتن', 'کیسه', 'عدد',
];

$errors = [];
$success = null;
$form = [
    'quantity'          => (string)($sample['quantity'] ?? ''),
    'quantity_unit'     => (string)($sample['quantity_unit'] ?? ''),
    'sampling_date'     => (string)($sample['sampling_date_fa'] ?? ''),
    'delivery_date'     => (string)($sample['delivery_date_fa'] ?? ''),
    'sampling_location' => (string)($sample['sampling_location'] ?? ''),
    'referrer'          => (string)($sample['referrer'] ?? ''),
    'receiver'          => (string)($sample['receiver'] ?? ''),
    'status'            => (string)($sample['status'] ?? 'in_progress'),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form = [
        'quantity'          => trim($_POST['quantity'] ?? ''),
        'quantity_unit'     => trim($_POST['quantity_unit'] ?? ''),
        'sampling_date'     => trim($_POST['sampling_date'] ?? ''),
        'delivery_date'     => trim($_POST['delivery_date'] ?? ''),
        'sampling_location' => trim($_POST['sampling_location'] ?? ''),
        'referrer'          => trim($_POST['referrer'] ?? ''),
        'receiver'          => trim($_POST['receiver'] ?? ''),
        'status'            => trim($_POST['status'] ?? 'in_progress'),
    ];

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
        $stmt = $pdo->prepare(
            "UPDATE samples SET
                quantity = :quantity,
                quantity_unit = :quantity_unit,
                sampling_date = :sampling_date,
                delivery_date = :delivery_date,
                sampling_location = :sampling_location,
                referrer = :referrer,
                receiver = :receiver,
                status = :status
             WHERE id = :id"
        );
        $stmt->execute([
            'quantity'          => $form['quantity'] !== '' ? $form['quantity'] : null,
            'quantity_unit'     => $form['quantity_unit'] !== '' ? $form['quantity_unit'] : null,
            'sampling_date'     => $samplingDateG,
            'delivery_date'     => $deliveryDateG,
            'sampling_location' => $form['sampling_location'] !== '' ? $form['sampling_location'] : null,
            'referrer'          => $form['referrer'] !== '' ? $form['referrer'] : null,
            'receiver'          => $form['receiver'] !== '' ? $form['receiver'] : null,
            'status'            => $form['status'],
            'id'                => $id,
        ]);

        $success = 'تغییرات با موفقیت ذخیره شد.';
        lab_log_activity($pdo, 'sample_edited', 'شماره نمونه: ' . $sample['sample_number']);
    }
}

$storedUnit = $form['quantity_unit'];
$unitKnown = $storedUnit !== '' && in_array($storedUnit, $quantityUnits, true);

require __DIR__ . '/_header.php';
?>

<div class="card">
    <a href="/lab/indicator" class="back-link">← بازگشت به لیست نمونه‌ها</a>
    <h1>ویرایش نمونه <?= htmlspecialchars($sample['sample_number']) ?></h1>
    <p class="muted small">
        نوع نمونه: <?= sprintf('%02d', $sample['type_code']) ?> — <?= htmlspecialchars($sample['type_name']) ?>
        (نوع نمونه بعد از ثبت قابل تغییر نیست، چون در شماره‌ی نمونه استفاده شده)
    </p>

    <?php foreach ($errors as $e): ?>
        <div class="msg-error"><?= htmlspecialchars($e) ?></div>
    <?php endforeach; ?>

    <?php if ($success): ?>
        <div class="msg-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <form method="post">
        <input type="hidden" name="id" value="<?= (int)$sample['id'] ?>">

        <div class="grid-2">
            <div>
                <label>نوع لاگ‌شیت اصلی</label>
                <input type="text" value="<?= htmlspecialchars($sample['main_log_sheet_type_name'] ?? '') ?>" readonly>
            </div>

            <div>
                <label>وضعیت</label>
                <select name="status">
                    <option value="in_progress" <?= $form['status'] === 'in_progress' ? 'selected' : '' ?>>در حال انجام</option>
                    <option value="completed" <?= $form['status'] === 'completed' ? 'selected' : '' ?>>تکمیل‌شده</option>
                    <option value="sent" <?= $form['status'] === 'sent' ? 'selected' : '' ?>>ارسال‌شده</option>
                </select>
            </div>

            <div>
                <label>مقدار نمونه <span class="optional">(اختیاری)</span></label>
                <input type="text" name="quantity" value="<?= htmlspecialchars($form['quantity']) ?>" inputmode="decimal">
            </div>

            <div>
                <label>واحد اندازه‌گیری <span class="optional">(اختیاری)</span></label>
                <select name="quantity_unit">
                    <option value="">— انتخاب کنید —</option>
                    <?php if ($storedUnit !== '' && !$unitKnown): ?>
                        <option value="<?= htmlspecialchars($storedUnit) ?>" selected>
                            <?= htmlspecialchars($storedUnit) ?> (مقدار قبلی)
                        </option>
                    <?php endif; ?>
                    <?php foreach ($quantityUnits as $unit): ?>
                        <option value="<?= htmlspecialchars($unit) ?>" <?= $unitKnown && $storedUnit === $unit ? 'selected' : '' ?>>
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

        <button type="submit" class="btn">ذخیره‌ی تغییرات</button>
    </form>
</div>

<?php require __DIR__ . '/_footer.php'; ?>