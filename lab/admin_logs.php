<?php
declare(strict_types=1);

require __DIR__ . '/../auth/config.php';
require __DIR__ . '/Functions.php';

lab_require_admin($pdo);

$loginLogs = lab_get_login_logs($pdo);
$activityLogs = lab_get_activity_logs($pdo);

$actionLabels = [
    'sample_created'           => 'ثبت نمونه‌ی جدید',
    'sample_edited'            => 'ویرایش نمونه',
    'internal_result_recorded' => 'ثبت نتیجه در لاگ‌شیت داخلی',
    'main_sheet_results_saved' => 'ذخیره‌ی نتایج لاگ‌شیت اصلی',
    'main_sheet_finalized'     => 'نهایی کردن لاگ‌شیت اصلی',
    'sent_to_cmms'             => 'ثبت ارسال به CMMS',
];
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="/assets/fonts.css">
    <title>لاگ کاربران — ادمین</title>
    <style>
        body { font-family: 'IRANSans', Tahoma, sans-serif; background:#f7f7f9; margin:0; padding:24px; }
        .card { background:#fff; border-radius:10px; padding:24px; max-width:1100px; margin:0 auto 24px; box-shadow:0 1px 4px rgba(0,0,0,.08); }
        h1 { font-size:20px; margin-top:0; }
        .back-link { display:inline-block; margin-bottom:16px; color:#2f6fed; text-decoration:none; font-size:14px; }
        table { width:100%; border-collapse:collapse; font-size:13px; }
        th, td { padding:8px; border-bottom:1px solid #eee; text-align:right; }
        th { color:#666; font-weight:normal; }
        .badge { padding:2px 8px; border-radius:10px; font-size:12px; }
        .badge-ok { background:#d4edda; color:#155724; }
        .badge-fail { background:#f8d7da; color:#721c24; }
        .tablewrap { overflow-x:auto; }
    </style>
</head>
<body>

    <div class="card">
        <a class="back-link" href="/dashboard">→ بازگشت به داشبورد</a>
        <h1>لاگ ورود کاربران</h1>
        <div class="tablewrap">
        <table>
            <thead>
                <tr>
                    <th>تاریخ/ساعت</th>
                    <th>نام کاربری</th>
                    <th>نتیجه</th>
                    <th>IP</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($loginLogs as $l): ?>
                    <tr>
                        <td><?= htmlspecialchars($l['created_at']) ?></td>
                        <td><?= htmlspecialchars($l['username']) ?></td>
                        <td>
                            <span class="badge <?= $l['success'] ? 'badge-ok' : 'badge-fail' ?>">
                                <?= $l['success'] ? 'موفق' : 'ناموفق' ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($l['ip_address'] ?? '—') ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$loginLogs): ?>
                    <tr><td colspan="4" style="text-align:center;color:#999;">
                        هنوز لاگی ثبت نشده — احتمالاً login.php هنوز به login_logs وصل نشده (به راهنمای نصب مراجعه کن).
                    </td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>

    <div class="card">
        <h1>لاگ فعالیت کاربران</h1>
        <div class="tablewrap">
        <table>
            <thead>
                <tr>
                    <th>تاریخ/ساعت</th>
                    <th>نام کاربری</th>
                    <th>فعالیت</th>
                    <th>جزئیات</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($activityLogs as $a): ?>
                    <tr>
                        <td><?= htmlspecialchars($a['created_at']) ?></td>
                        <td><?= htmlspecialchars($a['username'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($actionLabels[$a['action']] ?? $a['action']) ?></td>
                        <td><?= htmlspecialchars($a['details'] ?? '—') ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$activityLogs): ?>
                    <tr><td colspan="4" style="text-align:center;color:#999;">هنوز فعالیتی ثبت نشده است.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>

</body>
</html>
