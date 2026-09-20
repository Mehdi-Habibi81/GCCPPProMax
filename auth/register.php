<?php
require 'config.php';
require_once 'security.php';
require_once 'lang.php';

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
        $error = is_persian() ? 'وارد کردن نام کاربری الزامی است!' : 'Username is required!';
    } elseif (empty($email)) {
        $error = is_persian() ? 'وارد کردن ایمیل الزامی است!' : 'Email is required!';
    } elseif (empty($password)) {
        $error = t('password_required');
    } elseif (empty($password_confirm)) {
        $error = t('confirm_required');
    } elseif (strlen($username) < 3) {
        $error = t('valid_username');
    } elseif (strlen($username) > 50) {
        $error = t('username_length');
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = t('valid_email');
    } elseif (strlen($password) < 8) {
        $error = t('password_length');
    } elseif (!preg_match('/[A-Z]/', $password)) {
        $error = t('uppercase_required');
    } elseif (!preg_match('/[a-z]/', $password)) {
        $error = t('lowercase_required');
    } elseif (!preg_match('/[0-9]/', $password)) {
        $error = t('number_required');
    } elseif (!preg_match('/[^A-Za-z0-9]/', $password)) {
        $error = t('special_required');
    } elseif ($password !== $password_confirm) {
        $error = t('password_mismatch');
    } else {
        try {
            // Check if username already exists
            $stmt = $pdo->prepare(
                "SELECT id FROM users WHERE username = ? LIMIT 1"
            );
            $stmt->execute([$username]);
            $existing_user = $stmt->fetch();

            if ($existing_user) {
                $error = t('username_taken');
            } else {

                // Check if email already exists
                $stmt = $pdo->prepare(
                    "SELECT id FROM users WHERE email = ? LIMIT 1"
                );
                $stmt->execute([$email]);
                $existing_email = $stmt->fetch();

                if ($existing_email) {
                    $error = t('email_taken');
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

                    $success = is_persian()
                        ? 'ثبت‌نام با موفقیت انجام شد! در حال انتقال به صفحه ورود...'
                        : 'User registered successfully! Redirecting to login...';

                    $username = '';
                    $email = '';

                    // Redirect after 2 seconds
                    header("refresh:2;url=login");
                }
            }

        } catch (PDOException $e) {
            $error = t('generic_error');
            error_log("Registration error: " . $e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html>

<head>

    <title><?php echo t('register_title'); ?></title>

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

<body dir="<?php echo is_persian() ? 'rtl' : 'ltr'; ?>" lang="<?php echo is_persian() ? 'fa' : 'en'; ?>">

<div class="auth-container">

    <div class="auth-card">

        <div class="logo">
            A
        </div>

        <div class="language-switch"><a href="<?php echo htmlspecialchars(language_url($currentLanguage === 'fa' ? 'en' : 'fa'), ENT_QUOTES, 'UTF-8'); ?>"><?php echo t('language'); ?></a></div>
        <h1><?php echo t('register_heading'); ?></h1>

        <p class="subtitle">
            <?php echo t('register_subtitle'); ?>
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

                <label><?php echo t('username'); ?></label>

                <input
                    type="text"
                    name="username"
                    placeholder="<?php echo is_persian() ? 'نام کاربری انتخاب کنید (۳ تا ۵۰ کاراکتر)' : 'Choose a username (3-50 characters)'; ?>"
                    value="<?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?>"
                    minlength="3"
                    maxlength="50"
                    required
                >

            </div>

            <div class="form-group">

                <label><?php echo t('email'); ?></label>

                <input
                    type="email"
                    name="email"
                    placeholder="<?php echo is_persian() ? 'ایمیل خود را وارد کنید' : 'Enter your email'; ?>"
                    value="<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>"
                    required
                >

            </div>

            <div class="form-group">

                <label><?php echo t('password'); ?></label>

                <input
                    type="password"
                    name="password"
                    id="password"
                    placeholder="<?php echo is_persian() ? 'یک رمز عبور قوی بسازید' : 'Create a strong password'; ?>"
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
                        <?php echo t('password_strength'); ?>
                    </div>

                </div>

                <ul class="password-requirements">

                    <li id="length" data-label="<?php echo htmlspecialchars(t('length_requirement'), ENT_QUOTES, 'UTF-8'); ?>">
                        <?php echo t('length_requirement'); ?>
                    </li>

                    <li id="uppercase" data-label="<?php echo htmlspecialchars(t('uppercase_requirement'), ENT_QUOTES, 'UTF-8'); ?>">
                        <?php echo t('uppercase_requirement'); ?>
                    </li>

                    <li id="lowercase" data-label="<?php echo htmlspecialchars(t('lowercase_requirement'), ENT_QUOTES, 'UTF-8'); ?>">
                        <?php echo t('lowercase_requirement'); ?>
                    </li>

                    <li id="number" data-label="<?php echo htmlspecialchars(t('number_requirement'), ENT_QUOTES, 'UTF-8'); ?>">
                        <?php echo t('number_requirement'); ?>
                    </li>

                    <li id="special" data-label="<?php echo htmlspecialchars(t('special_requirement'), ENT_QUOTES, 'UTF-8'); ?>">
                        <?php echo t('special_requirement'); ?>
                    </li>

                </ul>

            </div>

            <div class="form-group">

                <label><?php echo t('confirm_password'); ?></label>

                <input
                    type="password"
                    name="password_confirm"
                    id="passwordConfirm"
                    placeholder="<?php echo is_persian() ? 'رمز عبور خود را تأیید کنید' : 'Confirm your password'; ?>"
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
                <?php echo t('register_button'); ?>
            </button>

        </form>

        <div class="auth-links">

            <p>
                <?php echo t('have_account'); ?>
                <a href="login">
                    <?php echo t('login_button'); ?>
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

    const text = element.dataset.label;

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
        strengthText.textContent = <?php echo json_encode(t('password_strength'), JSON_UNESCAPED_UNICODE); ?>;

    } else if (score <= 2) {

        strengthFill.style.width = '30%';
        strengthFill.style.background = '#ef4444';
        strengthText.textContent = <?php echo json_encode(t('weak'), JSON_UNESCAPED_UNICODE); ?>;

    } else if (score === 3) {

        strengthFill.style.width = '55%';
        strengthFill.style.background = '#f59e0b';
        strengthText.textContent = <?php echo json_encode(t('medium'), JSON_UNESCAPED_UNICODE); ?>;

    } else if (score === 4) {

        strengthFill.style.width = '80%';
        strengthFill.style.background = '#eab308';
        strengthText.textContent = <?php echo json_encode(t('good'), JSON_UNESCAPED_UNICODE); ?>;

    } else {

        strengthFill.style.width = '100%';
        strengthFill.style.background = '#22c55e';
        strengthText.textContent = <?php echo json_encode(t('strong'), JSON_UNESCAPED_UNICODE); ?>;

    }

    checkForm();

}


function checkPasswordMatch() {

    if (passwordConfirm.value === '') {

        passwordMatch.textContent = '';

    } else if (password.value === passwordConfirm.value) {

        passwordMatch.textContent = '✓ ' + <?php echo json_encode(t('passwords_match'), JSON_UNESCAPED_UNICODE); ?>;
        passwordMatch.style.color = '#22c55e';

    } else {

        passwordMatch.textContent = '✗ ' + <?php echo json_encode(t('passwords_do_not_match'), JSON_UNESCAPED_UNICODE); ?>;
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