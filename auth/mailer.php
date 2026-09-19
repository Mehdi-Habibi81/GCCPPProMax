<?php
declare(strict_types=1);

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (!is_file($autoload)) {
    throw new RuntimeException(
        'Composer autoloader not found. Run: composer require phpmailer/phpmailer'
    );
}
require_once $autoload;

$smtpConfigPath = __DIR__ . '/smtp_config.php';
if (!is_file($smtpConfigPath)) {
    throw new RuntimeException(
        'SMTP configuration is missing. Copy auth/smtp_config.php.example to auth/smtp_config.php and fill in the SMTP settings.'
    );
}

$smtpConfig = require $smtpConfigPath;
if (!is_array($smtpConfig)) {
    throw new RuntimeException('SMTP configuration must return an array.');
}

function create_smtp_mailer(array $smtpConfig): PHPMailer
{
    $required = [
        'host',
        'port',
        'username',
        'password',
        'from_address',
        'from_name',
    ];

    foreach ($required as $key) {
        if (!array_key_exists($key, $smtpConfig) || trim((string)$smtpConfig[$key]) === '') {
            throw new RuntimeException("SMTP setting '{$key}' is missing.");
        }
    }

    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = trim((string)$smtpConfig['host']);
    $mail->Port = (int)$smtpConfig['port'];
    $mail->SMTPAuth = true;
    $mail->Username = (string)$smtpConfig['username'];
    $mail->Password = (string)$smtpConfig['password'];
    $mail->SMTPDebug = SMTP::DEBUG_OFF;
    $mail->Timeout = 15;
    $mail->CharSet = 'UTF-8';
    $mail->Encoding = 'base64';
    $mail->isHTML(true);

    $encryption = strtolower(trim((string)($smtpConfig['encryption'] ?? '')));
    if ($encryption === 'tls') {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    } elseif ($encryption === 'ssl') {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    } elseif ($encryption !== '') {
        throw new RuntimeException("Unsupported SMTP encryption: {$encryption}");
    }

    $mail->setFrom(
        (string)$smtpConfig['from_address'],
        (string)$smtpConfig['from_name']
    );

    return $mail;
}

function send_password_reset_email(
    string $recipient,
    string $recipientName,
    string $resetUrl,
    bool $persian,
    array $smtpConfig
): void {
    $mail = create_smtp_mailer($smtpConfig);
    $mail->addAddress($recipient, $recipientName);

    if ($persian) {
        $mail->Subject = 'بازنشانی رمز عبور GCCPPProMax';
        $greeting = 'سلام';
        $title = 'بازنشانی رمز عبور';
        $message = 'درخواست بازنشانی رمز عبور حساب شما دریافت شده است.';
        $button = 'بازنشانی رمز عبور';
        $expiry = 'این لینک تا ۱ ساعت معتبر است.';
        $ignore = 'اگر این درخواست را شما ارسال نکرده‌اید، این ایمیل را نادیده بگیرید.';
        $copy = 'در صورتی که دکمه بالا کار نکرد، این لینک را در مرورگر باز کنید:';
        $footer = 'GCCPPProMax';
    } else {
        $mail->Subject = 'GCCPPProMax password reset';
        $greeting = 'Hello';
        $title = 'Password reset';
        $message = 'We received a request to reset the password for your account.';
        $button = 'Reset password';
        $expiry = 'This link is valid for 1 hour.';
        $ignore = 'If you did not request this, you can safely ignore this email.';
        $copy = 'If the button does not work, open this link in your browser:';
        $footer = 'GCCPPProMax';
    }

    $safeUrl = htmlspecialchars($resetUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $safeName = htmlspecialchars($recipientName !== '' ? $recipientName : $greeting, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    $mail->Body = <<<HTML
<!doctype html>
<html lang="{$safeName}" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin:0;padding:24px;background:#f5f7fb;font-family:Tahoma,Arial,sans-serif;color:#222;">
<div style="max-width:620px;margin:0 auto;background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:28px;">
    <h2 style="margin-top:0;">{$title}</h2>
    <p>{$safeName}،</p>
    <p>{$message}</p>
    <p style="margin:28px 0;text-align:center;">
        <a href="{$safeUrl}" style="display:inline-block;padding:13px 22px;background:#2563eb;color:#fff;text-decoration:none;border-radius:8px;">{$button}</a>
    </p>
    <p>{$expiry}</p>
    <p style="color:#666;">{$ignore}</p>
    <hr style="border:0;border-top:1px solid #eee;margin:24px 0;">
    <p style="font-size:13px;color:#666;">{$copy}</p>
    <p style="font-size:12px;word-break:break-all;color:#555;">{$safeUrl}</p>
    <p style="margin-bottom:0;color:#888;font-size:12px;">{$footer}</p>
</div>
</body>
</html>
HTML;

    $mail->AltBody = implode("\n", [
        $title,
        '',
        $message,
        '',
        $button . ': ' . $resetUrl,
        '',
        $expiry,
        $ignore,
    ]);

    try {
        $mail->send();
    } catch (Exception $e) {
        error_log('Password reset email failed: ' . $mail->ErrorInfo);
        throw new RuntimeException('Could not send the password reset email.', 0, $e);
    }
}
