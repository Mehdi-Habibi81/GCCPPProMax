<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lang.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/mailer.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        csrf_validate();

        if (!rate_limit_check('forgot_password', 5, 900)) {
            throw new RuntimeException(
                is_persian()
                    ? 'تعداد درخواست‌ها زیاد است. لطفاً بعداً دوباره تلاش کنید.'
                    : 'Too many requests. Please try again later.'
            );
        }

        $email = trim((string)($_POST['email'] ?? ''));

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = is_persian() ? 'لطفاً یک ایمیل معتبر وارد کنید.' : 'Please enter a valid email address.';
        } else {
            $stmt = $pdo->prepare(
                'SELECT id, username, email
                 FROM users
                 WHERE email = ?
                 LIMIT 1'
            );
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            // Always show the same generic response so the page does not reveal
            // whether an email address exists in the users table.
            if ($user) {
                $rawToken = bin2hex(random_bytes(32));
                $storedToken = hash('sha256', $rawToken);

                $upd = $pdo->prepare(
                    'UPDATE users
                     SET reset_token = ?,
                         reset_expires = DATE_ADD(NOW(), INTERVAL 1 HOUR)
                     WHERE id = ?'
                );
                $upd->execute([$storedToken, $user['id']]);

                $smtpBaseUrl = rtrim((string)($smtpConfig['base_url'] ?? ''), '/');
                if ($smtpBaseUrl === '') {
                    throw new RuntimeException('SMTP base_url is not configured.');
                }

                $resetUrl = $smtpBaseUrl . '/reset.php?token=' . rawurlencode($rawToken);

                send_password_reset_email(
                    (string)$user['email'],
                    (string)$user['username'],
                    $resetUrl,
                    is_persian(),
                    $smtpConfig
                );
            }

            rate_limit_clear('forgot_password');
            $success = t('reset_sent');
        }
    } catch (Throwable $e) {
        error_log('Forgot password error: ' . $e->getMessage());
        $success = null;
        if ($error === '') {
            $error = is_persian()
                ? 'ارسال لینک بازنشانی انجام نشد. تنظیمات ایمیل را بررسی کنید.'
                : 'The reset email could not be sent. Please check the email configuration.';
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo t('forgot_title'); ?></title>
<link rel="stylesheet" href="style.css">
</head>
<body dir="<?php echo is_persian() ? 'rtl' : 'ltr'; ?>" lang="<?php echo is_persian() ? 'fa' : 'en'; ?>">
<div class="auth-container">
<div class="auth-card">
<div class="logo">?</div>
<div class="language-switch">
<a href="<?php echo htmlspecialchars(language_url($currentLanguage === 'fa' ? 'en' : 'fa'), ENT_QUOTES, 'UTF-8'); ?>"><?php echo t('language'); ?></a>
</div>
<h1><?php echo t('forgot_heading'); ?></h1>
<p class="subtitle"><?php echo t('forgot_subtitle'); ?></p>

<?php if (!empty($error)): ?>
<div class="error-message"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
<?php endif; ?>

<?php if (!empty($success)): ?>
<div class="success-message"><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div>
<?php endif; ?>

<?php if (empty($success)): ?>
<form method="POST" autocomplete="off">
<?php echo csrf_field(); ?>
<div class="form-group">
<label for="email"><?php echo t('email'); ?></label>
<input id="email" type="email" name="email" autocomplete="email" placeholder="<?php echo is_persian() ? 'ایمیل خود را وارد کنید' : 'Enter your email'; ?>" required>
</div>
<button type="submit"><?php echo t('send_reset'); ?></button>
</form>
<?php endif; ?>

<div class="auth-links">
<p><?php echo t('remember_password'); ?><a href="login"><?php echo t('login_button'); ?></a></p>
</div>
</div>
</div>
</body>
</html>
