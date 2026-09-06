<?php

require 'config.php';
require_once 'security.php';
require_once 'lang.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PragmaRX\Google2FA\Google2FA;

if (!isset($_SESSION['user_id'])) {
    header('Location: login');
    exit();
}


$google2fa = new Google2FA();

$userId = $_SESSION['user_id'];

$stmt = $pdo->prepare(
    "SELECT id, username, email, two_factor_secret, two_factor_enabled
     FROM users
     WHERE id = ?
     LIMIT 1"
);
$stmt->execute([$userId]);

$user = $stmt->fetch();

if (!$user) {
    session_destroy();
    header('Location: login');
    exit();
}

$error = '';
$success = '';

/*
 * If 2FA is already enabled, don't generate a new secret.
 */
if ($user['two_factor_enabled']) {
    $success = t('already_enabled');
}

/*
 * Generate a new secret if the user doesn't have one.
 */
if (!$user['two_factor_enabled'] && empty($user['two_factor_secret'])) {

    $secret = $google2fa->generateSecretKey();

    $originalSecret = $secret;
    //reverse

    $encryptedSecret = encrypt_totp_secret($secret);

    $stmt = $pdo->prepare(
        "UPDATE users
         SET two_factor_secret = ?
         WHERE id = ?"
    );

    $stmt->execute([$encryptedSecret, $userId]);

    $user['two_factor_secret'] = $encryptedSecret;
}

/*
 * Verify the first authenticator code.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$user['two_factor_enabled']) {

    $code = trim($_POST['code'] ?? '');

    if (!preg_match('/^[0-9]{6}$/', $code)) {

        $error = is_persian()
            ? 'لطفاً کد ۶ رقمی Google Authenticator را وارد کنید.'
            : 'Please enter the 6-digit code from Google Authenticator.';

    } else {

        $originalSecret = decrypt_totp_secret($user['two_factor_secret']);

        $valid = $google2fa->verifyKey(
            $originalSecret,
            $code
        );

        if ($valid) {

            $stmt = $pdo->prepare(
                "UPDATE users
                 SET two_factor_enabled = 1,
                     two_factor_secret = ?
                 WHERE id = ?"
            );

            $stmt->execute([
                encrypt_totp_secret($originalSecret),
                $userId
            ]);

            $user['two_factor_enabled'] = 1;

            $success = t('enabled_success');

        } else {

            $error = is_persian()
                ? 'کد تأیید نادرست است. دوباره تلاش کنید.'
                : 'Invalid verification code. Please try again.';

        }
    }
}

/*
 * Generate the Google Authenticator URI.
 */
$companyName = 'My Website';

$qrSecret = $originalSecret ?? decrypt_totp_secret($user['two_factor_secret']);

$qrUrl = $google2fa->getQRCodeUrl(
    $companyName,
    $user['email'],
    $qrSecret
);
?>

<!DOCTYPE html>
<html>s

<head>

    <title><?php echo t('setup_twofa'); ?></title>

    <link rel="stylesheet" href="style.css">

    <style>

        .twofa-box {
            text-align: center;
            margin-top: 20px;
        }

        .qr-code {
            width: 220px;
            height: 220px;
            margin: 20px auto;
            display: block;
        }

        .secret {
            background: #f3f4f6;
            padding: 12px;
            border-radius: 6px;
            font-family: monospace;
            font-size: 18px;
            letter-spacing: 2px;
            word-break: break-all;
        }

        .twofa-instructions {
            text-align: left;
            margin: 20px 0;
        }

        .success-message {
            margin-bottom: 20px;
        }

        .error-message {
            margin-bottom: 20px;
        }

    </style>

</head>

<body dir="<?php echo is_persian() ? 'rtl' : 'ltr'; ?>" lang="<?php echo is_persian() ? 'fa' : 'en'; ?>">

<div class="auth-container">

    <div class="auth-card">

        <div class="logo">
            🔐
        </div>

        <div class="language-switch"><a href="<?php echo htmlspecialchars(language_url($currentLanguage === 'fa' ? 'en' : 'fa'), ENT_QUOTES, 'UTF-8'); ?>"><?php echo t('language'); ?></a></div>
        <h1><?php echo t('setup_twofa'); ?></h1>

        <?php if (!empty($error)): ?>

            <div class="error-message">
                <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
            </div>

        <?php endif; ?>

        <?php if (!empty($success)): ?>

            <div class="success-message">
                <?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?>
            </div>

        <?php endif; ?>

        <?php if (!$user['two_factor_enabled']): ?>

            <div class="twofa-box">

                <p>
                    <?php echo t('scan_qr'); ?>
                </p>

                <img
                    class="qr-code"
                    src="https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=<?php echo urlencode($qrUrl); ?>"
                    alt="<?php echo is_persian() ? 'کد QR Google Authenticator' : 'Google Authenticator QR Code'; ?>"
                >

                <p>
                    <?php echo t('manual_secret'); ?>
                </p>

                <div class="secret">
                    <?php echo htmlspecialchars($qrSecret, ENT_QUOTES, 'UTF-8'); ?>
                </div>

                <div class="twofa-instructions">

                    <ol>

                        <li>
                            <?php echo is_persian() ? 'Google Authenticator را نصب کنید.' : 'Install Google Authenticator.'; ?>
                        </li>

                        <li>
                            <?php echo is_persian() ? 'کد QR بالا را اسکن کنید.' : 'Scan the QR code above.'; ?>
                        </li>

                        <li>
                            <?php echo is_persian() ? 'کد ۶ رقمی برنامه را وارد کنید.' : 'Enter the 6-digit code shown in the app.'; ?>
                        </li>

                    </ol>

                </div>

                <form method="POST">

                    <div class="form-group">

                        <label>
                            <?php echo t('auth_code'); ?>
                        </label>

                        <input
                            type="text"
                            name="code"
                            inputmode="numeric"
                            autocomplete="one-time-code"
                            maxlength="6"
                            pattern="[0-9]{6}"
                            placeholder="<?php echo is_persian() ? 'کد ۶ رقمی را وارد کنید' : 'Enter 6-digit code'; ?>"
                            required
                        >

                    </div>

                    <button type="submit">
                        <?php echo t('enable_twofa'); ?>
                    </button>

                </form>

            </div>

        <?php else: ?>

            <p>
                <?php echo t('protected_twofa'); ?>
            </p>

            <div class="auth-links">

                <p>
                    <a href="../dashboard">
                        <?php echo t('back_dashboard'); ?>
                    </a>
                </p>

            </div>

        <?php endif; ?>

    </div>

</div>

</body>

</html>