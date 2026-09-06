<?php
require 'config.php';
require_once 'lang.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = $_POST['email'] ?? '';

    if (empty($email)) {
        echo htmlspecialchars(is_persian() ? 'لطفاً ایمیل خود را وارد کنید.' : 'Please enter your email.', ENT_QUOTES, 'UTF-8');
    } else {

        // Generate a secure random token
        $token = bin2hex(random_bytes(32));

        // Let MySQL calculate the expiration time.
        // This avoids PHP/MySQL timezone differences.
        $stmt = $pdo->prepare(
            "UPDATE users
             SET reset_token = ?,
                 reset_expires = DATE_ADD(NOW(), INTERVAL 1 HOUR)
             WHERE email = ?"
        );

        $stmt->execute([$token, $email]);

        echo htmlspecialchars(t('reset_sent'), ENT_QUOTES, 'UTF-8');

        // TESTING ONLY
        // Remove this when you implement email sending.
        echo "<br><br>";
        echo '<br><br>' . htmlspecialchars(t('test_link'), ENT_QUOTES, 'UTF-8') . ' ';
        echo "<a href='http://localhost/auth/reset?token="
            . urlencode($token)
            . "'>" . htmlspecialchars(t('reset_password_link'), ENT_QUOTES, 'UTF-8') . '</a>';
    }
}
?>

<!DOCTYPE html>
<html>

<head>

    <title><?php echo t('forgot_title'); ?></title>

    <link rel="stylesheet" href="style.css">

</head>

<body dir="<?php echo is_persian() ? 'rtl' : 'ltr'; ?>" lang="<?php echo is_persian() ? 'fa' : 'en'; ?>">

<div class="auth-container">

    <div class="auth-card">

        <div class="logo">
            ?
        </div>

        <div class="language-switch"><a href="<?php echo htmlspecialchars(language_url($currentLanguage === 'fa' ? 'en' : 'fa'), ENT_QUOTES, 'UTF-8'); ?>"><?php echo t('language'); ?></a></div>
        <h1><?php echo t('forgot_heading'); ?></h1>

        <p class="subtitle">
            <?php echo t('forgot_subtitle'); ?>
        </p>

        <form method="POST">

            <div class="form-group">

                <label><?php echo t('email'); ?></label>

                <input
                    type="email"
                    name="email"
                    placeholder="<?php echo is_persian() ? 'ایمیل خود را وارد کنید' : 'Enter your email'; ?>"
                    required
                >

            </div>

            <button type="submit">
                <?php echo t('send_reset'); ?>
            </button>

        </form>

        <div class="auth-links">

            <p>
                <?php echo t('remember_password'); ?>
                <a href="login">
                    <?php echo t('login_button'); ?>
                </a>
            </p>

        </div>

    </div>

</div>

</body>

</html>