<?php

declare(strict_types=1);

$supportedLanguages = ['en', 'fa'];
$requestedLanguage = $_GET['lang'] ?? null;

if (in_array($requestedLanguage, $supportedLanguages, true)) {
    $_SESSION['language'] = $requestedLanguage;
}

$currentLanguage = $_SESSION['language'] ?? 'en';

$translations = [
    'en' => [
        'login_title' => 'Login',
        'login_heading' => 'Login',
        'login_subtitle' => 'Log in to your account',
        'username' => 'Username',
        'password' => 'Password',
        'login_button' => 'Log in',
        'forgot_password' => 'Forgot password?',
        'no_account' => "Don't have an account?",
        'create_account' => 'Create account',
        'register_title' => 'Create Account',
        'register_heading' => 'Create Account',
        'register_subtitle' => 'Create your new account',
        'email' => 'Email',
        'confirm_password' => 'Confirm Password',
        'register_button' => 'Create account',
        'have_account' => 'Already have an account?',
        'forgot_title' => 'Forgot Password',
        'forgot_heading' => 'Forgot Password?',
        'forgot_subtitle' => "Enter your email and we'll help you reset your password.",
        'send_reset' => 'Send reset link',
        'remember_password' => 'Remember your password?',
        'reset_title' => 'Reset Password',
        'reset_heading' => 'Reset Password',
        'reset_subtitle' => 'Enter and confirm your new password below.',
        'new_password' => 'New Password',
        'confirm_new_password' => 'Confirm New Password',
        'set_password' => 'Set new password',
        'back_login' => 'Back to login',
        'request_reset' => 'Request a new reset link',
        'twofa_title' => 'Two-Factor Authentication',
        'twofa_subtitle' => 'Enter the 6-digit code from Google Authenticator.',
        'auth_code' => 'Authentication Code',
        'verify_login' => 'Verify and Log In',
        'cancel' => 'Cancel',
        'twofa_help' => 'Open Google Authenticator on your phone and enter the current 6-digit code.',
        'setup_twofa' => 'Set Up Two-Factor Authentication',
        'already_enabled' => 'Google Authenticator is already enabled on your account.',
        'scan_qr' => 'Open Google Authenticator on your phone and scan this QR code.',
        'manual_secret' => 'If you cannot scan the QR code, enter this secret manually:',
        'enable_twofa' => 'Enable 2FA',
        'enabled_success' => 'Google Authenticator has been enabled successfully!',
        'setup_steps' => 'Install Google Authenticator. Scan the QR code above. Enter the 6-digit code shown in the app.',
        'protected_twofa' => 'Your account is protected with Google Authenticator.',
        'back_dashboard' => 'Back to dashboard',
        'dashboard' => 'Dashboard',
        'hello' => 'Hello,',
        'logged_in' => 'You are now logged in.',
        'secure_area' => 'This is your secure dashboard area.',
        'account_security' => 'Account Security',
        'twofa_enabled' => 'Two-Factor Authentication Enabled',
        'twofa_disabled' => 'Two-Factor Authentication Disabled',
        'manage_twofa' => 'Manage 2FA',
        'add_twofa' => 'Enable Google Authenticator',
        'logout' => 'Logout',
        'language' => 'فارسی',
        'password_required' => 'Password is required!',
        'confirm_required' => 'Please confirm your password!',
        'password_length' => 'Password must be at least 8 characters long!',
        'uppercase_required' => 'Password must contain at least one uppercase letter!',
        'lowercase_required' => 'Password must contain at least one lowercase letter!',
        'number_required' => 'Password must contain at least one number!',
        'special_required' => 'Password must contain at least one special character!',
        'password_mismatch' => 'Passwords do not match!',
        'install_title' => 'Install Authentication System',
        'install_subtitle' => 'Configure your database to get started.',
        'database_host' => 'Database Host',
        'database_name' => 'Database Name',
        'database_username' => 'Database Username',
        'database_password' => 'Database Password',
        'install' => 'Install',
        'go_registration' => 'Go to registration',
        'reset_sent' => 'If that email is registered, a reset link has been sent.',
        'test_link' => 'Test link:',
        'reset_password_link' => 'Reset password',
        'valid_username' => 'Username must be at least 3 characters long!',
        'username_length' => 'Username must not exceed 50 characters!',
        'valid_email' => 'Please enter a valid email address!',
        'username_taken' => 'Username already taken! Please choose another username.',
        'email_taken' => 'Email already registered! Please use a different email.',
        'generic_error' => 'An error occurred. Please try again later.',
        'password_strength' => 'Password strength',
        'weak' => 'Weak',
        'medium' => 'Medium',
        'good' => 'Good',
        'strong' => 'Strong',
        'length_requirement' => 'At least 8 characters',
        'uppercase_requirement' => 'At least one uppercase letter',
        'lowercase_requirement' => 'At least one lowercase letter',
        'number_requirement' => 'At least one number',
        'special_requirement' => 'At least one special character',
        'passwords_match' => 'Passwords match',
        'passwords_do_not_match' => 'Passwords do not match',
    ],
    'fa' => [
        'login_title' => 'ورود', 'login_heading' => 'ورود', 'login_subtitle' => 'وارد حساب کاربری خود شوید',
        'username' => 'نام کاربری', 'password' => 'رمز عبور', 'login_button' => 'ورود',
        'forgot_password' => 'رمز عبور را فراموش کرده‌اید؟', 'no_account' => 'حساب کاربری ندارید؟', 'create_account' => 'ایجاد حساب',
        'register_title' => 'ایجاد حساب', 'register_heading' => 'ایجاد حساب', 'register_subtitle' => 'حساب کاربری جدید خود را بسازید',
        'email' => 'ایمیل', 'confirm_password' => 'تأیید رمز عبور', 'register_button' => 'ایجاد حساب', 'have_account' => 'قبلاً حساب دارید؟',
        'forgot_title' => 'فراموشی رمز عبور', 'forgot_heading' => 'رمز عبور را فراموش کرده‌اید؟', 'forgot_subtitle' => 'ایمیل خود را وارد کنید تا برای بازنشانی رمز عبور راهنمایی شوید.',
        'send_reset' => 'ارسال لینک بازنشانی', 'remember_password' => 'رمز عبور خود را به یاد دارید؟',
        'reset_title' => 'بازنشانی رمز عبور', 'reset_heading' => 'بازنشانی رمز عبور', 'reset_subtitle' => 'رمز عبور جدید را وارد و تأیید کنید.',
        'new_password' => 'رمز عبور جدید', 'confirm_new_password' => 'تأیید رمز عبور جدید', 'set_password' => 'ثبت رمز عبور جدید',
        'back_login' => 'بازگشت به ورود', 'request_reset' => 'درخواست لینک جدید', 'twofa_title' => 'احراز هویت دومرحله‌ای',
        'twofa_subtitle' => 'کد ۶ رقمی Google Authenticator را وارد کنید.', 'auth_code' => 'کد احراز هویت', 'verify_login' => 'تأیید و ورود',
        'cancel' => 'لغو', 'twofa_help' => 'Google Authenticator را در تلفن خود باز کنید و کد ۶ رقمی فعلی را وارد کنید.',
        'setup_twofa' => 'راه‌اندازی احراز هویت دومرحله‌ای', 'already_enabled' => 'Google Authenticator برای حساب شما فعال است.',
        'scan_qr' => 'Google Authenticator را در تلفن خود باز و این کد QR را اسکن کنید.', 'manual_secret' => 'اگر امکان اسکن ندارید، این کلید را دستی وارد کنید:',
        'enable_twofa' => 'فعال‌سازی احراز هویت دومرحله‌ای', 'enabled_success' => 'Google Authenticator با موفقیت فعال شد!',
        'setup_steps' => 'Google Authenticator را نصب کنید. کد QR بالا را اسکن کنید. کد ۶ رقمی برنامه را وارد کنید.',
        'protected_twofa' => 'حساب شما با Google Authenticator محافظت می‌شود.', 'back_dashboard' => 'بازگشت به داشبورد',
        'dashboard' => 'داشبورد', 'hello' => 'سلام،', 'logged_in' => 'شما وارد حساب شده‌اید.', 'secure_area' => 'این بخش امن داشبورد شماست.',
        'account_security' => 'امنیت حساب', 'twofa_enabled' => 'احراز هویت دومرحله‌ای فعال است', 'twofa_disabled' => 'احراز هویت دومرحله‌ای غیرفعال است',
        'manage_twofa' => 'مدیریت احراز هویت دومرحله‌ای', 'add_twofa' => 'فعال‌سازی Google Authenticator', 'logout' => 'خروج', 'language' => 'English',
        'password_required' => 'وارد کردن رمز عبور الزامی است!', 'confirm_required' => 'لطفاً رمز عبور را تأیید کنید!',
        'password_length' => 'رمز عبور باید حداقل ۸ کاراکتر باشد!', 'uppercase_required' => 'رمز عبور باید حداقل یک حرف بزرگ داشته باشد!',
        'lowercase_required' => 'رمز عبور باید حداقل یک حرف کوچک داشته باشد!', 'number_required' => 'رمز عبور باید حداقل یک عدد داشته باشد!',
        'special_required' => 'رمز عبور باید حداقل یک نویسه ویژه داشته باشد!', 'password_mismatch' => 'رمزهای عبور یکسان نیستند!',
        'install_title' => 'نصب سامانه احراز هویت', 'install_subtitle' => 'برای شروع، پایگاه داده خود را تنظیم کنید.',
        'database_host' => 'میزبان پایگاه داده', 'database_name' => 'نام پایگاه داده', 'database_username' => 'نام کاربری پایگاه داده',
        'database_password' => 'رمز عبور پایگاه داده', 'install' => 'نصب', 'go_registration' => 'رفتن به ثبت‌نام',
        'reset_sent' => 'اگر این ایمیل ثبت شده باشد، لینک بازنشانی ارسال خواهد شد.', 'test_link' => 'لینک آزمایشی:',
        'reset_password_link' => 'بازنشانی رمز عبور', 'valid_username' => 'نام کاربری باید حداقل ۳ کاراکتر باشد!',
        'username_length' => 'نام کاربری نباید بیشتر از ۵۰ کاراکتر باشد!', 'valid_email' => 'لطفاً یک ایمیل معتبر وارد کنید!',
        'username_taken' => 'این نام کاربری قبلاً استفاده شده است! نام دیگری انتخاب کنید.',
        'email_taken' => 'این ایمیل قبلاً ثبت شده است! ایمیل دیگری وارد کنید.', 'generic_error' => 'خطایی رخ داد. لطفاً دوباره تلاش کنید.',
        'password_strength' => 'قدرت رمز عبور', 'weak' => 'ضعیف', 'medium' => 'متوسط', 'good' => 'خوب', 'strong' => 'قوی',
        'length_requirement' => 'حداقل ۸ کاراکتر', 'uppercase_requirement' => 'حداقل یک حرف بزرگ',
        'lowercase_requirement' => 'حداقل یک حرف کوچک', 'number_requirement' => 'حداقل یک عدد',
        'special_requirement' => 'حداقل یک نویسه ویژه', 'passwords_match' => 'رمزهای عبور یکسان هستند',
        'passwords_do_not_match' => 'رمزهای عبور یکسان نیستند',
    ],
];

function t(string $key): string
{
    global $translations, $currentLanguage;
    return $translations[$currentLanguage][$key] ?? $translations['en'][$key] ?? $key;
}

function language_url(string $language): string
{
    $path = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
    $query = $_GET;
    $query['lang'] = $language;
    return $path . '?' . http_build_query($query);
}

function is_persian(): bool
{
    global $currentLanguage;
    return $currentLanguage === 'fa';
}
