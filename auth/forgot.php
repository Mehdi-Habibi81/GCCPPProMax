<?php
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = $_POST['email'] ?? '';

    if (empty($email)) {
        echo "Please enter your email.";
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

        echo "If that email is registered, a reset link has been sent.";

        // TESTING ONLY
        // Remove this when you implement email sending.
        echo "<br><br>";
        echo "Test link: ";
        echo "<a href='http://localhost/auth/reset?token="
            . urlencode($token)
            . "'>Reset password</a>";
    }
}
?>

<!DOCTYPE html>
<html>

<head>

    <title>Forgot Password</title>

    <link rel="stylesheet" href="style.css">

</head>

<body>

<div class="auth-container">

    <div class="auth-card">

        <div class="logo">
            ?
        </div>

        <h1>Forgot Password?</h1>

        <p class="subtitle">
            Enter your email and we'll help you reset your password.
        </p>

        <form method="POST">

            <div class="form-group">

                <label>Email</label>

                <input
                    type="email"
                    name="email"
                    placeholder="Enter your email"
                    required
                >

            </div>

            <button type="submit">
                Send reset link
            </button>

        </form>

        <div class="auth-links">

            <p>
                Remember your password?
                <a href="login">
                    Log in
                </a>
            </p>

        </div>

    </div>

</div>

</body>

</html>