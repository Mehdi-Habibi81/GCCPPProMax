<?php
declare(strict_types=1);

require __DIR__ . '/../auth/config.php'; // provides $pdo, starts session
require __DIR__ . '/Functions.php';

lab_require_login();

$errors = [];
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sampleType   = $_POST['sample_type']   ?? '';
    $entryDate    = $_POST['entry_date']    ?? date('Y-m-d');
    $sourceSender = trim($_POST['source_sender'] ?? '');

    if (!in_array($sampleType, ['fuel', 'oil'], true)) {
        $errors[] = 'نوع نمونه را انتخاب کنید (سوخت یا روغن).';
    }
    if ($sourceSender === '') {
        $errors[] = 'منبع/فرستنده‌ی نمونه را وارد کنید.';
    }

    if (!$errors) {
        $sampleNumber = lab_generate_sample_number($pdo);

        $stmt = $pdo->prepare(
            "INSERT INTO samples (sample_number, sample_type, entry_date, source_sender, status)
             VALUES (:sample_number, :sample_type, :entry_date, :source_sender, 'in_progress')"
        );
        $stmt->execute([
            'sample_number' => $sampleNumber,
            'sample_type'   => $sampleType,
            'entry_date'    => $entryDate,
            'source_sender' => $sourceSender,
        ]);

        $success = "نمونه با شماره {$sampleNumber} با موفقیت ثبت شد.";
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
        .card { background:#fff; border-radius:10px; padding:24px; max-width:720px; margin:0 auto 24px; box-shadow:0 1px 4px rgba(0,0,0,.08); }
        h1 { font-size:20px; margin-top:0; }
        label { display:block; margin:12px 0 4px; font-size:14px; color:#333; }
        input, select { width:100%; padding:8px; border:1px solid #ccc; border-radius:6px; font-size:14px; box-sizing:border-box; }
        button { margin-top:18px; padding:10px 20px; background:#2f6fed; color:#fff; border:none; border-radius:6px; cursor:pointer; font-size:14px; }
        button:hover { background:#255ac2; }
        .msg-error { background:#fdecea; color:#b71c1c; padding:10px 14px; border-radius:6px; margin-bottom:12px; }
        .msg-success { background:#e6f4ea; color:#1e7e34; padding:10px 14px; border-radius:6px; margin-bottom:12px; }
        table { width:100%; border-collapse:collapse; font-size:13px; }
        th, td { padding:8px; border-bottom:1px solid #eee; text-align:right; }
        th { color:#666; font-weight:normal; }
        .badge { padding:2px 8px; border-radius:10px; font-size:12px; }
        .badge-fuel { background:#fff3cd; color:#856404; }
        .badge-oil { background:#d4edda; color:#155724; }
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
            <label>نوع نمونه</label>
            <select name="sample_type" required>
                <option value="">— انتخاب کنید —</option>
                <option value="fuel">سوخت</option>
                <option value="oil">روغن</option>
            </select>

            <label>تاریخ ورود نمونه</label>
            <input type="date" name="entry_date" value="<?= htmlspecialchars(date('Y-m-d')) ?>" required>

            <label>منبع / فرستنده‌ی نمونه</label>
            <input type="text" name="source_sender" placeholder="مثلاً: واحد تولید ۲" required>

            <button type="submit">ثبت نمونه و صدور شماره</button>
        </form>
    </div>

    <div class="card">
        <h1>نمونه‌های اخیر</h1>
        <table>
            <thead>
                <tr>
                    <th>شماره نمونه</th>
                    <th>نوع</th>
                    <th>تاریخ ورود</th>
                    <th>منبع</th>
                    <th>وضعیت</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentSamples as $s): ?>
                    <tr>
                        <td><?= htmlspecialchars($s['sample_number']) ?></td>
                        <td>
                            <span class="badge <?= $s['sample_type'] === 'fuel' ? 'badge-fuel' : 'badge-oil' ?>">
                                <?= $s['sample_type'] === 'fuel' ? 'سوخت' : 'روغن' ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($s['entry_date']) ?></td>
                        <td><?= htmlspecialchars($s['source_sender']) ?></td>
                        <td><?= htmlspecialchars($s['status']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$recentSamples): ?>
                    <tr><td colspan="5" style="text-align:center;color:#999;">هنوز نمونه‌ای ثبت نشده است.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</body>
</html>