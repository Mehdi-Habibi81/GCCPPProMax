<?php
require 'config.php';
require_once 'lang.php';
require_once 'security.php';

$error = '';
$success = '';
$token = $_POST['token'] ?? $_GET['token'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = trim($token);
    $password = $_POST['password'] ?? '';
    $passwordConfirm = $_POST['password_confirm'] ?? '';

    $stmt = $pdo->prepare(
        "SELECT id, username FROM users
         WHERE reset_token = ? AND reset_expires > NOW()
         LIMIT 1"
    );
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if (!$user) {
        $error = 'Invalid or expired reset token.';
    } elseif (empty($password)) {
        $error = t('password_required');
    } elseif (empty($passwordConfirm)) {
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
        $hash = account_password_hash($password, $user['username']);
        $upd = $pdo->prepare(
            "UPDATE users
             SET password = ?, reset_token = NULL, reset_expires = NULL
             WHERE id = ?"
        );
        $upd->execute([$hash, $user['id']]);
        $success = is_persian() ? 'رمز عبور با موفقیت به‌روزرسانی شد!' : 'Password updated successfully!';
    }
} elseif ($token === '') {
    $error = is_persian() ? 'توکن بازنشانی ارائه نشده است.' : 'No reset token provided.';
}
?>
<!DOCTYPE html>
<html>
<head>
    <title><?php echo t('reset_title'); ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body dir="<?php echo is_persian() ? 'rtl' : 'ltr'; ?>" lang="<?php echo is_persian() ? 'fa' : 'en'; ?>">
    <div class="auth-container">
        <div class="auth-card">
            <div class="logo">✓</div>
            <div class="language-switch"><a href="<?php echo htmlspecialchars(language_url($currentLanguage === 'fa' ? 'en' : 'fa'), ENT_QUOTES, 'UTF-8'); ?>"><?php echo t('language'); ?></a></div>
            <h1><?php echo t('reset_heading'); ?></h1>
            <p class="subtitle"><?php echo t('reset_subtitle'); ?></p>

            <?php if ($error !== ''): ?>
                <div class="error-message">
                    <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <?php if ($success !== ''): ?>
                <div class="success-message">
                    <?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?>
                </div>
                <div class="auth-links">
                    <p><a href="login"><?php echo t('login_button'); ?></a></p>
                </div>
            <?php elseif ($token !== ''): ?>
                <form method="POST">
                    <input
                        type="hidden"
                        name="token"
                        value="<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>"
                    >

                    <div class="form-group">
                        <label for="password"><?php echo t('new_password'); ?></label>
                        <input
                            id="password"
                            type="password"
                            name="password"
                            placeholder="<?php echo is_persian() ? 'رمز عبور جدید را وارد کنید' : 'Enter your new password'; ?>"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="password_confirm"><?php echo t('confirm_new_password'); ?></label>
                        <input
                            id="password_confirm"
                            type="password"
                            name="password_confirm"
                            placeholder="<?php echo is_persian() ? 'رمز عبور جدید را تأیید کنید' : 'Confirm your new password'; ?>"
                            required
                        >
                    </div>

                    <button type="submit"><?php echo t('set_password'); ?></button>
                </form>

                <div class="auth-links">
                    <p><a href="login"><?php echo t('back_login'); ?></a></p>
                </div>
            <?php else: ?>
                <div class="auth-links">
                    <p><a href="forgot"><?php echo t('request_reset'); ?></a></p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>