<?php

declare(strict_types=1);

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

$originalSecret = null;

/*
 * If 2FA is already enabled, nothing needs to be generated
 * or decrypted for the setup page.
 */
if ((int)$user['two_factor_enabled'] === 1) {

    $success = t('already_enabled');

} else {

    /*
     * Generate a new secret if the user doesn't have one.
     */
    if (empty($user['two_factor_secret'])) {

        $originalSecret = $google2fa->generateSecretKey();

        $encryptedSecret = encrypt_totp_secret($originalSecret);

        $stmt = $pdo->prepare(
            "UPDATE users
             SET two_factor_secret = ?
             WHERE id = ?"
        );

        $stmt->execute([
            $encryptedSecret,
            $userId
        ]);

        /*
         * Keep the encrypted value in the local user array
         * so the current request remains consistent.
         */
        $user['two_factor_secret'] = $encryptedSecret;

    } else {

        /*
         * An existing secret is already stored.
         * Decrypt it and use the decrypted value for
         * the QR code and verification.
         */
        $originalSecret = decrypt_totp_secret(
            (string)$user['two_factor_secret']
        );
    }

    /*
     * Verify the first authenticator code.
     */
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        $code = trim($_POST['code'] ?? '');

        if (!preg_match('/^[0-9]{6}$/', $code)) {

            $error = is_persian()
                ? 'لطفاً کد ۶ رقمی Google Authenticator را وارد کنید.'
                : 'Please enter the 6-digit code from Google Authenticator.';

        } else {

            $valid = $google2fa->verifyKey(
                $originalSecret,
                $code
            );

            if ($valid) {

                /*
                 * The secret is already encrypted and stored.
                 * We only need to enable 2FA.
                 */
                $stmt = $pdo->prepare(
                    "UPDATE users
                     SET two_factor_enabled = 1
                     WHERE id = ?"
                );

                $stmt->execute([$userId]);

                $user['two_factor_enabled'] = 1;

                $success = t('enabled_success');

            } else {

                $error = is_persian()
                    ? 'کد تأیید نادرست است. دوباره تلاش کنید.'
                    : 'Invalid verification code. Please try again.';
            }
        }
    }
}

/*
 * Generate the Google Authenticator URI.
 *
 * Only generate the QR code while 2FA is not enabled.
 */
$qrSecret = null;
$qrUrl = null;

if ((int)$user['two_factor_enabled'] === 0 && $originalSecret !== null) {

    $companyName = 'My Website';

    $qrSecret = $originalSecret;

    $qrUrl = $google2fa->getQRCodeUrl(
        $companyName,
        $user['email'],
        $qrSecret
    );
}

?>

<!DOCTYPE html>
<html>

<head>

    <title><?php echo htmlspecialchars(t('setup_twofa'), ENT_QUOTES, 'UTF-8'); ?></title>

    <link rel="stylesheet" href="style.css?v=20260920">

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

<body
    dir="<?php echo is_persian() ? 'rtl' : 'ltr'; ?>"
    lang="<?php echo is_persian() ? 'fa' : 'en'; ?>"
>

<div class="auth-container">

    <div class="auth-card">

        <div class="logo">
            🔐
        </div>

        <div class="language-switch">
            <a href="<?php echo htmlspecialchars(
                language_url($currentLanguage === 'fa' ? 'en' : 'fa'),
                ENT_QUOTES,
                'UTF-8'
            ); ?>">
                <?php echo t('language'); ?>
            </a>
        </div>

        <h1>
            <?php echo htmlspecialchars(t('setup_twofa'), ENT_QUOTES, 'UTF-8'); ?>
        </h1>

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

        <?php if ((int)$user['two_factor_enabled'] === 0): ?>

            <?php if ($qrUrl !== null && $qrSecret !== null): ?>

                <div class="twofa-box">

                    <p>
                        <?php echo t('scan_qr'); ?>
                    </p>

                    <img
                        class="qr-code"
                        src="https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=<?php echo urlencode($qrUrl); ?>"
                        alt="<?php echo is_persian()
                            ? 'کد QR Google Authenticator'
                            : 'Google Authenticator QR Code'; ?>"
                    >

                    <p>
                        <?php echo t('manual_secret'); ?>
                    </p>

                    <div class="secret">
                        <?php echo htmlspecialchars(
                            $qrSecret,
                            ENT_QUOTES,
                            'UTF-8'
                        ); ?>
                    </div>

                    <div class="twofa-instructions">

                        <ol>

                            <li>
                                <?php echo is_persian()
                                    ? 'Google Authenticator را نصب کنید.'
                                    : 'Install Google Authenticator.'; ?>
                            </li>

                            <li>
                                <?php echo is_persian()
                                    ? 'کد QR بالا را اسکن کنید.'
                                    : 'Scan the QR code above.'; ?>
                            </li>

                            <li>
                                <?php echo is_persian()
                                    ? 'کد ۶ رقمی برنامه را وارد کنید.'
                                    : 'Enter the 6-digit code shown in the app.'; ?>
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
                                placeholder="<?php echo is_persian()
                                    ? 'کد ۶ رقمی را وارد کنید'
                                    : 'Enter 6-digit code'; ?>"
                                required
                            >

                        </div>

                        <button type="submit">
                            <?php echo t('enable_twofa'); ?>
                        </button>

                    </form>

                </div>

            <?php endif; ?>

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

<div class="site-signature">Developed by Mehdi Habibi</div>

</body>

</html>
