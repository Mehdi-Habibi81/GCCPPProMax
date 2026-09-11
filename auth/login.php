<?php
require 'config.php';
require_once 'security.php';
require_once 'lang.php';

$error = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    csrf_validate();

    // Rate limit: max 5 attempts per 15 minutes per session
    if (!rate_limit_check('login_attempt', 5, 900)) {
        $error = is_persian()
            ? 'تعداد تلاش‌های ناموفق زیاد است. ۱۵ دقیقه دیگر دوباره تلاش کنید.'
            : 'Too many failed attempts. Please try again in 15 minutes.';
    } else {

        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($username)) {

            $error = t('username') . ' ' . ($currentLanguage === 'fa' ? 'الزامی است!' : 'is required!');

        } elseif (empty($password)) {

            $error = t('password_required');

        } else {

            try {

                $stmt = $pdo->prepare(
                    "SELECT * FROM users
                     WHERE username = ?
                     LIMIT 1"
                );

                $stmt->execute([$username]);
                $user = $stmt->fetch();

                if ($user && account_password_verify(
                    $password,
                    $user['username'],
                    $user['password']
                )) {

                    // Success - clear rate limit counter
                    rate_limit_clear('login_attempt');

                    /*
                     * Password is correct.
                     *
                     * If 2FA is enabled, DON'T log the user in yet.
                     */
                    if ((int)$user['two_factor_enabled'] === 1) {

                        // Store temporary authentication information
                        $_SESSION['2fa_user_id'] = $user['id'];
                        $_SESSION['2fa_username'] = $user['username'];

                        // Redirect to 2FA verification
                        header("Location: 2fa_verify");
                        exit();

                    } else {

                        /*
                         * 2FA isn't enabled.
                         * Log the user in normally.
                         */
                        session_harden();

                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['username'] = $user['username'];

                        $_SESSION['flash_message'] = is_persian()
                            ? 'خوش آمدید، ' . $user['username'] . '!'
                            : 'Welcome back, ' . $user['username'] . '!';

                        header("Location: ../dashboard");
                        exit();
                    }

                } else {

                    $error = is_persian()
                        ? 'نام کاربری یا رمز عبور نادرست است!'
                        : 'Invalid username or password!';
                }

            } catch (PDOException $e) {

                $error = t('generic_error');

                error_log(
                    "Login error: " . $e->getMessage()
                );
            }
        }
    }
}
?>

<!DOCTYPE html>
<html>

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title><?php echo htmlspecialchars(t('login_title'), ENT_QUOTES, 'UTF-8'); ?></title>

    <link rel="stylesheet" href="style.css">

</head>

<body dir="<?php echo is_persian() ? 'rtl' : 'ltr'; ?>" lang="<?php echo is_persian() ? 'fa' : 'en'; ?>">

<div class="auth-container">

    <div class="auth-card">

        <div class="logo">
            A
        </div>

        <div class="language-switch"><a href="<?php echo htmlspecialchars(language_url($currentLanguage === 'fa' ? 'en' : 'fa'), ENT_QUOTES, 'UTF-8'); ?>"><?php echo t('language'); ?></a></div>
        <h1><?php echo t('login_heading'); ?></h1>

        <p class="subtitle">
            <?php echo t('login_subtitle'); ?>
        </p>

        <?php if (!empty($error)): ?>

            <div class="error-message">
                <?php
                echo htmlspecialchars(
                    $error,
                    ENT_QUOTES,
                    'UTF-8'
                );
                ?>
            </div>

        <?php endif; ?>

        <form method="post" novalidate>

            <?php echo csrf_field(); ?>

            <div class="form-group">

                <label><?php echo t('username'); ?></label>

                <input
                    type="text"
                    name="username"
                    placeholder="<?php echo is_persian() ? 'نام کاربری خود را وارد کنید' : 'Enter your username'; ?>"
                    value="<?php
                        echo htmlspecialchars(
                            $username,
                            ENT_QUOTES,
                            'UTF-8'
                        );
                    ?>"
                    required
                >

            </div>

            <div class="form-group">

                <label><?php echo t('password'); ?></label>

                <input
                    type="password"
                    name="password"
                    placeholder="<?php echo is_persian() ? 'رمز عبور خود را وارد کنید' : 'Enter your password'; ?>"
                    required
                >

            </div>

            <button type="submit">
                <?php echo t('login_button'); ?>
            </button>

        </form>

        <div class="auth-links">

            <p>
                <a href="forgot">
                    <?php echo t('forgot_password'); ?>
                </a>
            </p>

            <p>
                <?php echo t('no_account'); ?>
                <a href="register">
                    <?php echo t('create_account'); ?>
                </a>
            </p>

        </div>

    </div>

</div>

</body>

</html>
