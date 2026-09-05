<?php
require 'config.php';
require_once 'security.php';

$error = '';
$success = '';
$username = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';

    // Validate inputs
    if (empty($username)) {
        $error = "Username is required!";
    } elseif (empty($email)) {
        $error = "Email is required!";
    } elseif (empty($password)) {
        $error = "Password is required!";
    } elseif (empty($password_confirm)) {
        $error = "Please confirm your password!";
    } elseif (strlen($username) < 3) {
        $error = "Username must be at least 3 characters long!";
    } elseif (strlen($username) > 50) {
        $error = "Username must not exceed 50 characters!";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address!";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters long!";
    } elseif (!preg_match('/[A-Z]/', $password)) {
        $error = "Password must contain at least one uppercase letter!";
    } elseif (!preg_match('/[a-z]/', $password)) {
        $error = "Password must contain at least one lowercase letter!";
    } elseif (!preg_match('/[0-9]/', $password)) {
        $error = "Password must contain at least one number!";
    } elseif (!preg_match('/[^A-Za-z0-9]/', $password)) {
        $error = "Password must contain at least one special character!";
    } elseif ($password !== $password_confirm) {
        $error = "Passwords do not match!";
    } else {
        try {
            // Check if username already exists
            $stmt = $pdo->prepare(
                "SELECT id FROM users WHERE username = ? LIMIT 1"
            );
            $stmt->execute([$username]);
            $existing_user = $stmt->fetch();

            if ($existing_user) {
                $error = "Username already taken! Please choose another username.";
            } else {

                // Check if email already exists
                $stmt = $pdo->prepare(
                    "SELECT id FROM users WHERE email = ? LIMIT 1"
                );
                $stmt->execute([$email]);
                $existing_email = $stmt->fetch();

                if ($existing_email) {
                    $error = "Email already registered! Please use a different email.";
                } else {

                    // Hash password
                    $hash = account_password_hash($password, $username);

                    // Insert new user
                    $stmt = $pdo->prepare(
                        "INSERT INTO users (username, email, password)
                         VALUES (?, ?, ?)"
                    );

                    $stmt->execute([
                        $username,
                        $email,
                        $hash
                    ]);

                    $success = "User registered successfully! Redirecting to login...";

                    $username = '';
                    $email = '';

                    // Redirect after 2 seconds
                    header("refresh:2;url=login");
                }
            }

        } catch (PDOException $e) {
            $error = "An error occurred during registration. Please try again later.";
            error_log("Registration error: " . $e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html>

<head>

    <title>Create Account</title>

    <link rel="stylesheet" href="style.css">

    <style>

        .password-strength {
            margin-top: 10px;
        }

        .strength-bar {
            width: 100%;
            height: 8px;
            background: #e5e7eb;
            border-radius: 5px;
            overflow: hidden;
        }

        .strength-fill {
            width: 0%;
            height: 100%;
            background: #e5e7eb;
            transition: width 0.3s ease, background 0.3s ease;
        }

        .strength-text {
            margin-top: 6px;
            font-size: 13px;
            font-weight: 600;
        }

        .password-requirements {
            list-style: none;
            padding: 0;
            margin: 10px 0 0;
            font-size: 13px;
        }

        .password-requirements li {
            margin: 4px 0;
            color: #777;
        }

        .password-requirements li.valid {
            color: #22c55e;
        }

        .password-requirements li.invalid {
            color: #ef4444;
        }

        button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

    </style>

</head>

<body>

<div class="auth-container">

    <div class="auth-card">

        <div class="logo">
            A
        </div>

        <h1>Create Account</h1>

        <p class="subtitle">
            Create your new account
        </p>

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

        <form method="post" id="registerForm" novalidate>

            <div class="form-group">

                <label>Username</label>

                <input
                    type="text"
                    name="username"
                    placeholder="Choose a username (3-50 characters)"
                    value="<?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?>"
                    minlength="3"
                    maxlength="50"
                    required
                >

            </div>

            <div class="form-group">

                <label>Email</label>

                <input
                    type="email"
                    name="email"
                    placeholder="Enter your email"
                    value="<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>"
                    required
                >

            </div>

            <div class="form-group">

                <label>Password</label>

                <input
                    type="password"
                    name="password"
                    id="password"
                    placeholder="Create a strong password"
                    required
                >

                <div class="password-strength">

                    <div class="strength-bar">
                        <div
                            class="strength-fill"
                            id="strengthFill"
                        ></div>
                    </div>

                    <div
                        class="strength-text"
                        id="strengthText"
                    >
                        Password strength
                    </div>

                </div>

                <ul class="password-requirements">

                    <li id="length">
                         At least 8 characters
                    </li>

                    <li id="uppercase">
                         At least one uppercase letter
                    </li>

                    <li id="lowercase">
                         At least one lowercase letter
                    </li>

                    <li id="number">
                         At least one number
                    </li>

                    <li id="special">
                         At least one special character
                    </li>

                </ul>

            </div>

            <div class="form-group">

                <label>Confirm Password</label>

                <input
                    type="password"
                    name="password_confirm"
                    id="passwordConfirm"
                    placeholder="Confirm your password"
                    required
                >

                <div
                    id="passwordMatch"
                    style="margin-top: 6px; font-size: 13px;"
                ></div>

            </div>

            <button
                type="submit"
                id="submitButton"
                disabled
            >
                Create account
            </button>

        </form>

        <div class="auth-links">

            <p>
                Already have an account?
                <a href="login">
                    Log in
                </a>
            </p>

        </div>

    </div>

</div>

<script>

const password = document.getElementById('password');
const passwordConfirm = document.getElementById('passwordConfirm');

const strengthFill = document.getElementById('strengthFill');
const strengthText = document.getElementById('strengthText');

const submitButton = document.getElementById('submitButton');

const lengthRequirement = document.getElementById('length');
const uppercaseRequirement = document.getElementById('uppercase');
const lowercaseRequirement = document.getElementById('lowercase');
const numberRequirement = document.getElementById('number');
const specialRequirement = document.getElementById('special');

const passwordMatch = document.getElementById('passwordMatch');


function updateRequirement(element, valid) {

    const text = element.textContent.substring(2);

    if (valid) {
        element.classList.add('valid');
        element.classList.remove('invalid');
        element.textContent = '✓ ' + text;
    } else {
        element.classList.add('invalid');
        element.classList.remove('valid');
        element.textContent = '✗ ' + text;
    }

}


function checkPassword() {

    const value = password.value;

    const hasLength = value.length >= 8;
    const hasUppercase = /[A-Z]/.test(value);
    const hasLowercase = /[a-z]/.test(value);
    const hasNumber = /[0-9]/.test(value);
    const hasSpecial = /[^A-Za-z0-9]/.test(value);

    updateRequirement(lengthRequirement, hasLength);
    updateRequirement(uppercaseRequirement, hasUppercase);
    updateRequirement(lowercaseRequirement, hasLowercase);
    updateRequirement(numberRequirement, hasNumber);
    updateRequirement(specialRequirement, hasSpecial);

    const score =
        Number(hasLength) +
        Number(hasUppercase) +
        Number(hasLowercase) +
        Number(hasNumber) +
        Number(hasSpecial);

    // Update strength bar
    if (score === 0) {

        strengthFill.style.width = '0%';
        strengthFill.style.background = '#e5e7eb';
        strengthText.textContent = 'Password strength';

    } else if (score <= 2) {

        strengthFill.style.width = '30%';
        strengthFill.style.background = '#ef4444';
        strengthText.textContent = 'Weak';

    } else if (score === 3) {

        strengthFill.style.width = '55%';
        strengthFill.style.background = '#f59e0b';
        strengthText.textContent = 'Medium';

    } else if (score === 4) {

        strengthFill.style.width = '80%';
        strengthFill.style.background = '#eab308';
        strengthText.textContent = 'Good';

    } else {

        strengthFill.style.width = '100%';
        strengthFill.style.background = '#22c55e';
        strengthText.textContent = 'Strong';

    }

    checkForm();

}


function checkPasswordMatch() {

    if (passwordConfirm.value === '') {

        passwordMatch.textContent = '';

    } else if (password.value === passwordConfirm.value) {

        passwordMatch.textContent = '✓ Passwords match';
        passwordMatch.style.color = '#22c55e';

    } else {

        passwordMatch.textContent = '✗ Passwords do not match';
        passwordMatch.style.color = '#ef4444';

    }

    checkForm();

}


function checkForm() {

    const value = password.value;

    const strongPassword =
        value.length >= 8 &&
        /[A-Z]/.test(value) &&
        /[a-z]/.test(value) &&
        /[0-9]/.test(value) &&
        /[^A-Za-z0-9]/.test(value);

    const passwordsMatch =
        password.value !== '' &&
        password.value === passwordConfirm.value;

    submitButton.disabled = !(strongPassword && passwordsMatch);

}


password.addEventListener('input', checkPassword);
passwordConfirm.addEventListener('input', checkPasswordMatch);

</script>

</body>

</html>