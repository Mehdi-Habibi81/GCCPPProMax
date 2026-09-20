<?php
declare(strict_types=1);

require __DIR__ . '/../auth/config.php';
require __DIR__ . '/Functions.php';

lab_require_admin($pdo);

$pageTitle = 'لاگ کاربران — ادمین';
$activeNav = 'admin';

$loginLogs = lab_get_login_logs($pdo);
$activityLogs = lab_get_activity_logs($pdo);

$actionLabels = [
    'sample_created'           => 'ثبت نمونه‌ی جدید',
    'sample_edited'            => 'ویرایش نمونه',
    'internal_result_recorded' => 'ثبت نتیجه در لاگ‌شیت داخلی',
    'internal_result_transferred' => 'انتقال نتیجه به لاگ‌شیت اصلی',
    'main_sheet_results_saved' => 'ذخیره‌ی نتایج لاگ‌شیت اصلی',
    'main_sheet_finalized'     => 'نهایی کردن لاگ‌شیت اصلی',
    'sent_to_cmms'             => 'ثبت ارسال به CMMS',
    'limits_updated'           => 'به‌روزرسانی محدوده‌های مجاز',
];

require __DIR__ . '/_header.php';
?>

<div class="card">
    <a class="back-link" href="/dashboard">← بازگشت به داشبورد</a>
    <h1>لاگ ورود کاربران</h1>
    <div class="tablewrap">
    <table class="data">
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
                    <td><?= htmlspecialchars(lab_gregorian_datetime_to_jalali($l['created_at'])) ?></td>
                    <td><?= htmlspecialchars($l['username'] ?? '—') ?></td>
                    <td>
                        <span class="badge <?= $l['success'] ? 'badge-ok' : 'badge-fail' ?>">
                            <?= $l['success'] ? 'موفق' : 'ناموفق' ?>
                        </span>
                    </td>
                    <td><?= htmlspecialchars($l['ip_address'] ?? '—') ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$loginLogs): ?>
                <tr><td colspan="4" style="text-align:center;color:#9ca3af;">هنوز لاگی ثبت نشده است.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
    </div>
</div>

<div class="card">
    <h1>لاگ فعالیت کاربران</h1>
    <div class="tablewrap">
    <table class="data">
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
                    <td><?= htmlspecialchars(lab_gregorian_datetime_to_jalali($a['created_at'])) ?></td>
                    <td><?= htmlspecialchars($a['username'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($actionLabels[$a['action']] ?? $a['action']) ?></td>
                    <td><?= htmlspecialchars($a['details'] ?? '—') ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$activityLogs): ?>
                <tr><td colspan="4" style="text-align:center;color:#9ca3af;">هنوز فعالیتی ثبت نشده است.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
    </div>
</div>

<?php require __DIR__ . '/_footer.php'; ?>