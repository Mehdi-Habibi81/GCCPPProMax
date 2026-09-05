<?php

declare(strict_types=1);

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
    if (!str_starts_with($storedSecret, 'enc:v1:')) {
        return strrev($storedSecret);
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
