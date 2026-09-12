<?php
declare(strict_types=1);

require __DIR__ . '/../auth/config.php'; // provides $pdo, starts session
require __DIR__ . '/Functions.php';

lab_require_login();

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) {
    header('Location: /lab/indicator.php');
    exit;
}

$errors = [];
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $quantity         = trim($_POST['quantity'] ?? '');
    $quantityUnit     = trim($_POST['quantity_unit'] ?? '');
    $samplingDateJ    = trim($_POST['sampling_date'] ?? '');
    $deliveryDateJ    = trim($_POST['delivery_date'] ?? '');
    $samplingLocation = trim($_POST['sampling_location'] ?? '');
    $referrer         = trim($_POST['referrer'] ?? '');
    $receiver         = trim($_POST['receiver'] ?? '');
    $status           = trim($_POST['status'] ?? 'in_progress');

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
            'quantity'          => $quantity !== '' ? $quantity : null,
            'quantity_unit'     => $quantityUnit !== '' ? $quantityUnit : null,
            'sampling_date'     => $samplingDateG,
            'delivery_date'     => $deliveryDateG,
            'sampling_location' => $samplingLocation !== '' ? $samplingLocation : null,
            'referrer'          => $referrer !== '' ? $referrer : null,
            'receiver'          => $receiver !== '' ? $receiver : null,
            'status'            => $status,
            'id'                => $id,
        ]);

        $success = 'تغییرات با موفقیت ذخیره شد.';
    }
}

$sample = lab_get_sample_by_id($pdo, $id);
if (!$sample) {
    header('Location: /lab/indicator.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>ویرایش نمونه — <?= htmlspecialchars($sample['sample_number']) ?></title>
    <style>
        body { font-family: Tahoma, sans-serif; background:#f7f7f9; margin:0; padding:24px; }
        .card { background:#fff; border-radius:10px; padding:24px; max-width:820px; margin:0 auto 24px; box-shadow:0 1px 4px rgba(0,0,0,.08); }
        h1 { font-size:20px; margin-top:0; }
        .subtitle { color:#777; font-size:13px; margin-top:-8px; margin-bottom:16px; }
        .grid { display:grid; grid-template-columns:1fr 1fr; gap:0 20px; }
        label { display:block; margin:12px 0 4px; font-size:14px; color:#333; }
        label .optional { color:#999; font-weight:normal; font-size:12px; }
        input, select { width:100%; padding:8px; border:1px solid #ccc; border-radius:6px; font-size:14px; box-sizing:border-box; }
        input[disabled] { background:#f2f2f2; color:#777; }
        button { margin-top:18px; padding:10px 20px; background:#2f6fed; color:#fff; border:none; border-radius:6px; cursor:pointer; font-size:14px; }
        button:hover { background:#255ac2; }
        .msg-error { background:#fdecea; color:#b71c1c; padding:10px 14px; border-radius:6px; margin-bottom:12px; }
        .msg-success { background:#e6f4ea; color:#1e7e34; padding:10px 14px; border-radius:6px; margin-bottom:12px; }
        .back-link { display:inline-block; margin-bottom:16px; color:#2f6fed; text-decoration:none; font-size:14px; }
    </style>
</head>
<body>

    <div class="card">
        <a href="/lab/indicator.php" class="back-link">← بازگشت به لیست نمونه‌ها</a>
        <h1>ویرایش نمونه <?= htmlspecialchars($sample['sample_number']) ?></h1>
        <div class="subtitle">
            نوع نمونه: <?= sprintf('%02d', $sample['type_code']) ?> — <?= htmlspecialchars($sample['type_name']) ?>
            (نوع نمونه بعد از ثبت قابل تغییر نیست، چون در شماره‌ی نمونه استفاده شده)
        </div>

        <?php foreach ($errors as $e): ?>
            <div class="msg-error"><?= htmlspecialchars($e) ?></div>
        <?php endforeach; ?>

        <?php if ($success): ?>
            <div class="msg-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <form method="post">
            <input type="hidden" name="id" value="<?= (int)$sample['id'] ?>">

            <div class="grid">
                <div>
                    <label>نوع لاگ‌شیت اصلی</label>
                    <input type="text" value="<?= htmlspecialchars($sample['main_log_sheet_type_name'] ?? '') ?>" readonly>
                </div>

                <div>
                    <label>وضعیت</label>
                    <select name="status">
                        <option value="in_progress" <?= $sample['status'] === 'in_progress' ? 'selected' : '' ?>>در حال انجام</option>
                        <option value="completed" <?= $sample['status'] === 'completed' ? 'selected' : '' ?>>تکمیل‌شده</option>
                        <option value="sent" <?= $sample['status'] === 'sent' ? 'selected' : '' ?>>ارسال‌شده</option>
                    </select>
                </div>

                <div>
                    <label>مقدار نمونه <span class="optional">(اختیاری)</span></label>
                    <input type="text" name="quantity" value="<?= htmlspecialchars($sample['quantity'] ?? '') ?>" inputmode="decimal">
                </div>

                <div>
                    <label>واحد اندازه‌گیری <span class="optional">(اختیاری)</span></label>
                    <input type="text" name="quantity_unit" value="<?= htmlspecialchars($sample['quantity_unit'] ?? '') ?>">
                </div>

                <div>
                    <label>تاریخ نمونه‌گیری (شمسی) <span class="optional">(اختیاری)</span></label>
                    <input type="text" name="sampling_date" value="<?= htmlspecialchars($sample['sampling_date_fa']) ?>" placeholder="۱۴۰۴/۰۶/۱۶">
                </div>

                <div>
                    <label>تاریخ تحویل نمونه (شمسی) <span class="optional">(اختیاری)</span></label>
                    <input type="text" name="delivery_date" value="<?= htmlspecialchars($sample['delivery_date_fa']) ?>" placeholder="۱۴۰۴/۰۶/۱۶">
                </div>

                <div>
                    <label>محل نمونه‌گیری <span class="optional">(اختیاری)</span></label>
                    <input type="text" name="sampling_location" value="<?= htmlspecialchars($sample['sampling_location'] ?? '') ?>">
                </div>

                <div></div>

                <div>
                    <label>ارجاع‌کننده <span class="optional">(اختیاری)</span></label>
                    <input type="text" name="referrer" value="<?= htmlspecialchars($sample['referrer'] ?? '') ?>">
                </div>

                <div>
                    <label>تحویل‌گیرنده <span class="optional">(اختیاری)</span></label>
                    <input type="text" name="receiver" value="<?= htmlspecialchars($sample['receiver'] ?? '') ?>">
                </div>
            </div>

            <button type="submit">ذخیره‌ی تغییرات</button>
        </form>
    </div>

</body>
</html>س