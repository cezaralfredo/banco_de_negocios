<?php
declare(strict_types=1);

/* ==========================================================================
   Criptografia de segredos (senha SMTP, chaves de API) com libsodium.
   A chave da instalação fica em storage/app.key (fora do acesso web).
   ========================================================================== */

function app_key(): string
{
    static $key = null;
    if ($key !== null) return $key;
    $file = BN_ROOT . '/storage/app.key';
    if (!is_file($file)) {
        if (!is_dir(dirname($file))) @mkdir(dirname($file), 0755, true);
        @file_put_contents($file, base64_encode(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES)), LOCK_EX);
        @chmod($file, 0600);
    }
    $raw = base64_decode(trim((string)@file_get_contents($file)), true);
    if (!$raw || strlen($raw) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
        // fallback determinístico (instalação sem permissão de escrita)
        $raw = hash('sha256', __DIR__ . php_uname(), true);
    }
    return $key = $raw;
}

function secret_encrypt(string $plain): string
{
    if ($plain === '') return '';
    $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    return 'enc:' . base64_encode($nonce . sodium_crypto_secretbox($plain, $nonce, app_key()));
}

function secret_decrypt(string $stored): string
{
    if (!str_starts_with($stored, 'enc:')) return $stored;
    $bin = base64_decode(substr($stored, 4), true);
    if (!$bin || strlen($bin) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) return '';
    $plain = sodium_crypto_secretbox_open(substr($bin, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), substr($bin, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), app_key());
    return $plain === false ? '' : $plain;
}

/** Lê uma configuração secreta (criptografada no banco). */
function secret_setting(string $key): string
{
    global $pdo;
    return secret_decrypt(setting($pdo, $key, ''));
}

function save_secret_setting(string $key, string $value): void
{
    global $pdo;
    save_setting($pdo, $key, secret_encrypt($value));
}

/** Exibe um segredo mascarado no painel (ex.: ••••••a1b2). */
function mask_secret(string $value): string
{
    if ($value === '') return '';
    return str_repeat('•', 8) . substr($value, -4);
}

/** Chave de API cadastrada em Configurações globais › Chaves de API (para plugins/integrações). */
function api_key(string $id): string
{
    global $pdo;
    foreach ((array)json_decode(setting($pdo, 'api_chaves', '[]'), true) as $k) {
        if (($k['chave'] ?? '') === $id) return secret_decrypt((string)$k['valor']);
    }
    return '';
}
