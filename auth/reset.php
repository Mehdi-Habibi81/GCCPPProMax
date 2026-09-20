<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lang.php';
require_once __DIR__ . '/security.php';

$error = '';
$success = '';
$token = trim((string)($_POST['token'] ?? $_GET['token'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        csrf_validate();

        if (!rate_limit_check('reset_password', 10, 900)) {
            throw new RuntimeException(
                is_persian()
                    ? 'تعداد درخواست‌ها زیاد است. لطفاً بعداً دوباره تلاش کنید.'
                    : 'Too many attempts. Please try again later.'
            );
        }

        $password = (string)($_POST['password'] ?? '');
        $passwordConfirm = (string)($_POST['password_confirm'] ?? '');

        if ($token === '' || !preg_match('/^[a-f0-9]{64}$/i', $token)) {
            $error = is_persian() ? 'توکن بازنشانی نامعتبر یا ناقص است.' : 'Invalid or malformed reset token.';
        } else {
            $storedToken = hash('sha256', $token);
            $stmt = $pdo->prepare(
                'SELECT id, username
                 FROM users
                 WHERE reset_token = ?
                   AND reset_expires > NOW()
                 LIMIT 1'
            );
            $stmt->execute([$storedToken]);
            $user = $stmt->fetch();

            if (!$user) {
                $error = is_persian()
                    ? 'لینک بازنشانی نامعتبر یا منقضی شده است.'
                    : 'Invalid or expired reset token.';
            } elseif ($password === '') {
                $error = t('password_required');
            } elseif ($passwordConfirm === '') {
                $error = t('confirm_required');
            } elseif (strlen($password) < 8) {
                $error = t('password_length');
            } elseif (!preg_match('/[A-Z]/', $password)) {
                $error = t('uppercase_required');
            } elseif (!preg_match('/[a-z]/', $password)) {
                $error = t('lowercase_required');
            } elseif (!preg_match('/[0-9]/', $password)) {
                $error = t('number_required');
            } elseif (!preg_match('/[^A-Za-z0-9]/', $password)) {
                $error = t('special_required');
            } elseif ($password !== $passwordConfirm) {
                $error = t('password_mismatch');
            } else {
                $hash = account_password_hash($password, (string)$user['username']);

                $upd = $pdo->prepare(
                    'UPDATE users
                     SET password = ?,
                         reset_token = NULL,
                         reset_expires = NULL
                     WHERE id = ?'
                );
                $upd->execute([$hash, $user['id']]);

                rate_limit_clear('reset_password');
                $success = is_persian()
                    ? 'رمز عبور با موفقیت به‌روزرسانی شد!'
                    : 'Password updated successfully!';
            }
        }
    } catch (Throwable $e) {
        error_log('Reset password error: ' . $e->getMessage());
        if ($error === '') {
            $error = is_persian()
                ? 'انجام عملیات ممکن نشد. لطفاً دوباره تلاش کنید.'
                : 'The operation could not be completed. Please try again.';
        }
    }
} elseif ($token === '') {
    $error = is_persian() ? 'توکن بازنشانی ارائه نشده است.' : 'No reset token provided.';
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo t('reset_title'); ?></title>
<link rel="stylesheet" href="style.css?v=20260920">
</head>
<body dir="<?php echo is_persian() ? 'rtl' : 'ltr'; ?>" lang="<?php echo is_persian() ? 'fa' : 'en'; ?>">
<div class="auth-container">
<div class="auth-card">
<div class="logo">✓</div>
<div class="language-switch">
<a href="<?php echo htmlspecialchars(language_url($currentLanguage === 'fa' ? 'en' : 'fa'), ENT_QUOTES, 'UTF-8'); ?>"><?php echo t('language'); ?></a>
</div>
<h1><?php echo t('reset_heading'); ?></h1>
<p class="subtitle"><?php echo t('reset_subtitle'); ?></p>

<?php if ($error !== ''): ?>
<div class="error-message"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
<?php endif; ?>

<?php if ($success !== ''): ?>
<div class="success-message"><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div>
<div class="auth-links"><p><a href="login"><?php echo t('login_button'); ?></a></p></div>
<?php elseif ($token !== ''): ?>
<form method="POST" autocomplete="off">
<?php echo csrf_field(); ?>
<input type="hidden" name="token" value="<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>">
<div class="form-group">
<label for="password"><?php echo t('new_password'); ?></label>
<input id="password" type="password" name="password" autocomplete="new-password" placeholder="<?php echo is_persian() ? 'رمز عبور جدید را وارد کنید' : 'Enter your new password'; ?>" required>
</div>
<div class="form-group">
<label for="password_confirm"><?php echo t('confirm_new_password'); ?></label>
<input id="password_confirm" type="password" name="password_confirm" autocomplete="new-password" placeholder="<?php echo is_persian() ? 'رمز عبور جدید را تأیید کنید' : 'Confirm your new password'; ?>" required>
</div>
<button type="submit"><?php echo t('set_password'); ?></button>
</form>
<div class="auth-links"><p><a href="login"><?php echo t('back_login'); ?></a></p></div>
<?php else: ?>
<div class="auth-links"><p><a href="forgot"><?php echo t('request_reset'); ?></a></p></div>
<?php endif; ?>
</div>
</div>
<div class="site-signature">Developed by Mehdi Habibi</div>

</body>
</html>
