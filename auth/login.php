<?php
require 'config.php';
require_once 'security.php';

$error = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username)) {

        $error = "Username is required!";

    } elseif (empty($password)) {

        $error = "Password is required!";

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
                    session_regenerate_id(true);

                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];

                    $_SESSION['flash_message'] =
                        "Welcome back, " . $user['username'] . "!";

                    header("Location: ../dashboard");
                    exit();
                }

            } else {

                $error = "Invalid username or password!";
            }

        } catch (PDOException $e) {

            $error = "An error occurred. Please try again later.";

            error_log(
                "Login error: " . $e->getMessage()
            );
        }
    }
}
?>

<!DOCTYPE html>
<html>

<head>

    <title>Login</title>

    <link rel="stylesheet" href="style.css">

</head>

<body>

<div class="auth-container">

    <div class="auth-card">

        <div class="logo">
            A
        </div>

        <h1>Login</h1>

        <p class="subtitle">
            Log in to your account
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

            <div class="form-group">

                <label>Username</label>

                <input
                    type="text"
                    name="username"
                    placeholder="Enter your username"
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

                <label>Password</label>

                <input
                    type="password"
                    name="password"
                    placeholder="Enter your password"
                    required
                >

            </div>

            <button type="submit">
                Log in
            </button>

        </form>

        <div class="auth-links">

            <p>
                <a href="forgot">
                    Forgot password?
                </a>
            </p>

            <p>
                Don't have an account?
                <a href="register">
                    Create account
                </a>
            </p>

        </div>

    </div>

</div>

</body>

</html>