<?php
require 'auth/config.php';

// If user is not logged in, redirect to login
if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login");
    exit();
}

// Get flash message if it exists, then delete it
$flash_message = $_SESSION['flash_message'] ?? '';
unset($_SESSION['flash_message']);

$userId = $_SESSION['user_id'];

// Get current user's 2FA status
$stmt = $pdo->prepare(
    "SELECT username, two_factor_enabled
     FROM users
     WHERE id = ?
     LIMIT 1"
);

$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    session_destroy();
    header("Location: auth/login");
    exit();
}

$username = htmlspecialchars(
    $user['username'],
    ENT_QUOTES,
    'UTF-8'
);

$twoFactorEnabled = (int)$user['two_factor_enabled'] === 1;
?>

<!DOCTYPE html>
<html>

<head>

    <meta charset="UTF-8">

    <title>Dashboard</title>

    <link rel="stylesheet" href="auth/style.css">

    <style>

        body {
            background: #f1f5f9;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            font-family: Arial, sans-serif;
            margin: 0;
        }

        .dashboard-card {
            background: white;
            padding: 40px;
            border-radius: 24px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            max-width: 500px;
            width: 100%;
            text-align: center;
        }

        .dashboard-card h1 {
            margin-top: 0;
        }

        .security-card {
            margin-top: 30px;
            padding: 20px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            text-align: left;
        }

        .security-card h2 {
            margin-top: 0;
            font-size: 20px;
        }

        .security-status {
            margin: 15px 0;
            padding: 12px;
            border-radius: 10px;
            font-size: 14px;
        }

        .enabled {
            background: #dcfce7;
            color: #166534;
        }

        .disabled {
            background: #fef3c7;
            color: #92400e;
        }

        .security-btn {
            display: inline-block;
            margin-top: 10px;
            padding: 11px 20px;
            background: #2563eb;
            color: white;
            text-decoration: none;
            border-radius: 10px;
            font-weight: bold;
        }

        .security-btn:hover {
            background: #1d4ed8;
        }

        .logout-btn {
            display: inline-block;
            margin-top: 25px;
            padding: 10px 30px;
            background: #dc2626;
            color: white;
            text-decoration: none;
            border-radius: 10px;
        }

        .logout-btn:hover {
            background: #b91c1c;
        }

    </style>

</head>

<body>

<div class="dashboard-card">

    <?php if ($flash_message): ?>

        <div class="success-message" style="margin-bottom:20px;">

            <?= htmlspecialchars(
                $flash_message,
                ENT_QUOTES,
                'UTF-8'
            ) ?>

        </div>

    <?php endif; ?>


    <h1>Dashboard</h1>

    <p>
        Hello, <strong><?= $username ?></strong>!
        You are now logged in.
    </p>

    <p>
        This is your secure dashboard area.
    </p>


    <!-- Security / 2FA Section -->

    <div class="security-card">

        <h2>🔐 Account Security</h2>

        <?php if ($twoFactorEnabled): ?>

            <div class="security-status enabled">

                <strong>✓ Two-Factor Authentication Enabled</strong>

                <br>

                Your account is protected with
                Google Authenticator.

            </div>

            <a
                href="auth/2fa_setup"
                class="security-btn"
            >
                Manage 2FA
            </a>

        <?php else: ?>

            <div class="security-status disabled">

                <strong>⚠ Two-Factor Authentication Disabled</strong>

                <br>

                Add Google Authenticator to make your
                account more secure.

            </div>

            <a
                href="auth/2fa_setup"
                class="security-btn"
            >
                Enable Google Authenticator
            </a>

        <?php endif; ?>

    </div>


    <a
        href="auth/logout"
        class="logout-btn"
    >
        Logout
    </a>

</div>

</body>

</html>