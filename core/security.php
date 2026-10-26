<?php
declare(strict_types=1);

/* ==========================================================================
   Sessão segura (isolada por cliente), cabeçalhos HTTP e mensagens "flash".
   ========================================================================== */

function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') || (int)($_SERVER['SERVER_PORT'] ?? 0) === 443;
}

function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE || PHP_SAPI === 'cli') return;
    // Cada cliente tem sua própria sessão: um login no cliente A não vale no cliente B.
    session_name('cms_' . preg_replace('/[^a-z0-9]/', '', defined('BN_TENANT') ? BN_TENANT : 'principal'));
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax', 'secure' => is_https()]);
    ini_set('session.use_strict_mode', '1');
    session_start();
}

function send_security_headers(bool $admin): void
{
    if (headers_sent() || PHP_SAPI === 'cli') return;
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    if (is_https()) header('Strict-Transport-Security: max-age=31536000');
    if ($admin) {
        header('Cache-Control: no-store, private');
        header('X-Robots-Tag: noindex, nofollow');
    }
}

/** Guarda uma mensagem para a próxima página (padrão Post/Redirect/Get). */
function flash(string $type, string $message, string $undoUrl = ''): void
{
    start_session();
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $message, 'undo' => $undoUrl];
}

function flash_take(): array
{
    start_session();
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

/** URL base confiável para links enviados por e-mail: só o endereço configurado ou um domínio do cliente.
    Evita que um cabeçalho Host forjado gere links para outro site (envenenamento de reset de senha). */
function trusted_base_url(): ?string
{
    global $pdo;
    $configured = rtrim(setting($pdo, 'site_url', ''), '/');
    if ($configured !== '') return $configured . '/';
    $host = strtolower(preg_replace('/:\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? '')));
    $domains = array_map(fn($d) => strtolower(preg_replace('/^www\./', '', (string)$d)), (array)(BN_TENANT_DATA['dominios'] ?? []));
    if (bn_is_local_host($host) || in_array(preg_replace('/^www\./', '', $host), $domains, true)) return site_base_url();
    return null;
}

/** URL base pública do site (com barra final). */
function site_base_url(): string
{
    global $pdo;
    $configured = rtrim(setting($pdo, 'site_url', ''), '/');
    if ($configured !== '') return $configured . '/';
    $scheme = is_https() ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '/'));
    $base = preg_replace('#/(admin|plugins/[^/]+)(/.*)?$#', '', dirname($script));
    return $scheme . '://' . $host . rtrim($base, '/') . '/';
}
