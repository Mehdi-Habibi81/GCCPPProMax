<?php
require 'config.php';
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
        $error = 'Password is required!';
    } elseif (empty($passwordConfirm)) {
        $error = 'Please confirm your password!';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long!';
    } elseif (!preg_match('/[A-Z]/', $password)) {
        $error = 'Password must contain at least one uppercase letter!';
    } elseif (!preg_match('/[a-z]/', $password)) {
        $error = 'Password must contain at least one lowercase letter!';
    } elseif (!preg_match('/[0-9]/', $password)) {
        $error = 'Password must contain at least one number!';
    } elseif (!preg_match('/[^A-Za-z0-9]/', $password)) {
        $error = 'Password must contain at least one special character!';
    } elseif ($password !== $passwordConfirm) {
        $error = 'Passwords do not match!';
    } else {
        $hash = account_password_hash($password, $user['username']);
        $upd = $pdo->prepare(
            "UPDATE users
             SET password = ?, reset_token = NULL, reset_expires = NULL
             WHERE id = ?"
        );
        $upd->execute([$hash, $user['id']]);
        $success = 'Password updated successfully!';
    }
} elseif ($token === '') {
    $error = 'No reset token provided.';
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Reset Password</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="logo">✓</div>
            <h1>Reset Password</h1>
            <p class="subtitle">Enter and confirm your new password below.</p>

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
                    <p><a href="login">Log in</a></p>
                </div>
            <?php elseif ($token !== ''): ?>
                <form method="POST">
                    <input
                        type="hidden"
                        name="token"
                        value="<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>"
                    >

                    <div class="form-group">
                        <label for="password">New Password</label>
                        <input
                            id="password"
                            type="password"
                            name="password"
                            placeholder="Enter your new password"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="password_confirm">Confirm New Password</label>
                        <input
                            id="password_confirm"
                            type="password"
                            name="password_confirm"
                            placeholder="Confirm your new password"
                            required
                        >
                    </div>

                    <button type="submit">Set new password</button>
                </form>

                <div class="auth-links">
                    <p><a href="login">Back to login</a></p>
                </div>
            <?php else: ?>
                <div class="auth-links">
                    <p><a href="forgot">Request a new reset link</a></p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>