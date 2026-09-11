<?php

declare(strict_types=1);

/* ============================================================
 * Password hashing (peppered with username)
 * ============================================================ */

function account_password_hash(string $password, string $username): string
{
    return password_hash($password . "\n" . $username, PASSWORD_DEFAULT);
}

function account_password_verify(
    string $password,
    string $username,
    string $storedHash
): bool {
    return password_verify($password . "\n" . $username, $storedHash);
}

/* ============================================================
 * TOTP secret encryption via AES-256-GCM (key stored outside DB)
 * ============================================================ */

function totp_encryption_key(): string
{
    static $key;

    if ($key !== null) {
        return $key;
    }

    $configuredKey = getenv('AUTH_ENCRYPTION_KEY');
    if ($configuredKey === false || $configuredKey === '') {
        $keyFile = __DIR__ . '/.auth_encryption_key';
        $configuredKey = is_readable($keyFile)
            ? trim((string)file_get_contents($keyFile))
            : false;
    }

    if ($configuredKey === false || $configuredKey === '') {
        throw new RuntimeException(
            'An external TOTP encryption key must be configured.'
        );
    }

    $decodedKey = base64_decode($configuredKey, true);
    if ($decodedKey !== false && strlen($decodedKey) === 32) {
        return $key = $decodedKey;
    }

    if (ctype_xdigit($configuredKey) && strlen($configuredKey) === 64) {
        return $key = hex2bin($configuredKey);
    }

    throw new RuntimeException('AUTH_ENCRYPTION_KEY must be a 32-byte key.');
}

function encrypt_totp_secret(string $secret): string
{
    $iv = random_bytes(12);
    $tag = '';
    $ciphertext = openssl_encrypt(
        $secret,
        'aes-256-gcm',
        totp_encryption_key(),
        OPENSSL_RAW_DATA,
        $iv,
        $tag
    );

    if ($ciphertext === false) {
        throw new RuntimeException('Could not encrypt the authenticator secret.');
    }

    return 'enc:v1:' . base64_encode($iv . $tag . $ciphertext);
}

function decrypt_totp_secret(string $storedSecret): string
{
    // Only 'enc:v1:' prefixed payloads are valid — legacy plaintext
    // secrets are no longer supported for security reasons.
    if (!str_starts_with($storedSecret, 'enc:v1:')) {
        throw new RuntimeException(
            'Unsupported or corrupted TOTP secret format. ' .
            'Re-register with Google Authenticator by disabling and re-enabling 2FA.'
        );
    }

    $payload = base64_decode(substr($storedSecret, 7), true);
    if ($payload === false || strlen($payload) < 28) {
        throw new RuntimeException('Invalid encrypted authenticator secret.');
    }

    $iv = substr($payload, 0, 12);
    $tag = substr($payload, 12, 16);
    $ciphertext = substr($payload, 28);
    $secret = openssl_decrypt(
        $ciphertext,
        'aes-256-gcm',
        totp_encryption_key(),
        OPENSSL_RAW_DATA,
        $iv,
        $tag
    );

    if ($secret === false) {
        throw new RuntimeException('Could not decrypt the authenticator secret.');
    }

    return $secret;
}

/* ============================================================
 * CSRF protection — simple, dependency-free token handling
 * ============================================================ */

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="'
        . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8')
        . '">';
}

/**
 * Validate the CSRF token from POST data.
 * Call at the top of every state-changing POST handler.
 */
function csrf_validate(): void
{
    $submitted = $_POST['csrf_token'] ?? '';
    if (
        empty($_SESSION['csrf_token']) ||
        !is_string($submitted) ||
        !hash_equals($_SESSION['csrf_token'], $submitted)
    ) {
        http_response_code(403);
        exit('Invalid CSRF token. Please go back and try again.');
    }
}

/* ============================================================
 * Rate limiting — simple in-session throttle
 * to deter brute-force attacks.
 * ============================================================ */

/**
 * Check and record an attempt for a given action key.
 *
 * Example: rate_limit_check('login_attempt', 5, 900)
 *   → allows at most 5 attempts per 15 minutes.
 *
 * Returns true if the attempt should be allowed, false if it's blocked.
 */
function rate_limit_check(string $action, int $maxAttempts, int $windowSeconds): bool
{
    $now = time();
    $key = 'rate_limit_' . $action;

    $attempts = $_SESSION[$key] ?? [];

    // Prune expired entries
    $attempts = array_values(array_filter($attempts, function ($ts) use ($now, $windowSeconds) {
        return ($now - $ts) < $windowSeconds;
    }));

    if (count($attempts) >= $maxAttempts) {
        $_SESSION[$key] = $attempts;
        return false;
    }

    // Record the attempt
    $attempts[] = $now;
    $_SESSION[$key] = $attempts;
    return true;
}

/**
 * Clear recorded attempts for an action after a successful operation.
 */
function rate_limit_clear(string $action): void
{
    unset($_SESSION['rate_limit_' . $action]);
}

/* ============================================================
 * Session hardening helper
 * ============================================================ */

/**
 * Regenerate session ID and set a fresh token (called after login).
 */
function session_harden(): void
{
    session_regenerate_id(true);
    // Invalidate old CSRF token by forcing a new one.
    unset($_SESSION['csrf_token']);
}
