<?php

require 'config.php';
require_once 'security.php';
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
    $success = "Google Authenticator is already enabled on your account.";
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

        $error = "Please enter the 6-digit code from Google Authenticator.";

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

            $success = "Google Authenticator has been enabled successfully!";

        } else {

            $error = "Invalid verification code. Please try again.";

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
<html>

<head>

    <title>Set Up Two-Factor Authentication</title>

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

<body>

<div class="auth-container">

    <div class="auth-card">

        <div class="logo">
            🔐
        </div>

        <h1>Two-Factor Authentication</h1>

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
                    Open <strong>Google Authenticator</strong>
                    on your phone and scan this QR code.
                </p>

                <img
                    class="qr-code"
                    src="https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=<?php echo urlencode($qrUrl); ?>"
                    alt="Google Authenticator QR Code"
                >

                <p>
                    If you cannot scan the QR code, enter this secret manually:
                </p>

                <div class="secret">
                    <?php echo htmlspecialchars($qrSecret, ENT_QUOTES, 'UTF-8'); ?>
                </div>

                <div class="twofa-instructions">

                    <ol>

                        <li>
                            Install Google Authenticator.
                        </li>

                        <li>
                            Scan the QR code above.
                        </li>

                        <li>
                            Enter the 6-digit code shown in the app.
                        </li>

                    </ol>

                </div>

                <form method="POST">

                    <div class="form-group">

                        <label>
                            Authentication Code
                        </label>

                        <input
                            type="text"
                            name="code"
                            inputmode="numeric"
                            autocomplete="one-time-code"
                            maxlength="6"
                            pattern="[0-9]{6}"
                            placeholder="Enter 6-digit code"
                            required
                        >

                    </div>

                    <button type="submit">
                        Enable 2FA
                    </button>

                </form>

            </div>

        <?php else: ?>

            <p>
                Your account is protected with Google Authenticator.
            </p>

            <div class="auth-links">

                <p>
                    <a href="../dashboard">
                        Back to dashboards
                    </a>
                </p>

            </div>

        <?php endif; ?>

    </div>

</div>

</body>

</html>