<?php
declare(strict_types=1);

// Shared header for all lab pages.
// Expected before include: require config.php + Functions.php, lab_require_login().
// Optional variables: $pageTitle (string), $activeNav (string), $navHidden (bool).

$pageTitle = $pageTitle ?? 'آزمایشگاه سوخت و روغن';
$activeNav = $activeNav ?? '';
$navHidden = $navHidden ?? false;
$containerClass = $containerClass ?? 'container';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="/assets/fonts.css?v=20260920">
    <link rel="stylesheet" href="/assets/lab.css?v=20260920">
    <script src="/assets/jalali-datepicker.js?v=20260921" defer></script>
</head>
<body>

<?php if (!$navHidden): ?>
<nav class="topnav">
    <div class="topnav-inner">
        <span class="brand">آزمایشگاه سوخت و روغن</span>
        <a href="/dashboard" class="<?= $activeNav === 'dashboard' ? 'active' : '' ?>">داشبورد</a>
        <a href="indicator" class="<?= $activeNav === 'indicator' ? 'active' : '' ?>">دفتر اندیکاتور</a>
        <a href="internal_sheet" class="<?= $activeNav === 'internal' ? 'active' : '' ?>">لاگ‌شیت‌های داخلی</a>
        <a href="analytics" class="<?= $activeNav === 'analytics' ? 'active' : '' ?>">آنالیز</a>
        <a href="limits" class="<?= $activeNav === 'limits' ? 'active' : '' ?>">محدوده‌های مجاز</a>
        <?php if (lab_is_admin($pdo)): ?>
            <a href="admin_logs" class="<?= $activeNav === 'admin' ? 'active' : '' ?>">لاگ ادمین</a>
        <?php endif; ?>
        <span class="spacer"></span>
        <span class="user-chip"><?= htmlspecialchars((string)($_SESSION['username'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
        <a href="/auth/logout" class="logout">خروج</a>
    </div>
</nav>
<?php endif; ?>

<div class="<?= $containerClass ?>">