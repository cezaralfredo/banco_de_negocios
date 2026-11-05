<?php
declare(strict_types=1);

/* ==========================================================================
   Multi-cliente (multi-tenant): um banco de dados e uma pasta de mídia por cliente.

   O cliente é escolhido pelo domínio acessado (config/tenants.json → "dominios").
   Em ambiente local (localhost/127.0.0.1) é possível alternar com ?cliente=slug.
   O cliente "principal" usa as credenciais de config/database.php / variáveis
   de ambiente, mantendo compatibilidade com instalações existentes.
   ========================================================================== */

const BN_TENANTS_FILE = __DIR__ . '/../config/tenants.json';

/** Carrega variáveis do arquivo .env da raiz do projeto, se existir. */
function bn_load_env(): void
{
    static $loaded = false;
    if ($loaded) return;
    $loaded = true;
    $envFile = dirname(__DIR__) . '/.env';
    if (!is_file($envFile)) return;
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!$lines) return;
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
        [$k, $v] = explode('=', $line, 2);
        $k = trim($k);
        $v = trim($v, " \t\n\r\0\x0B\"'");
        putenv("{$k}={$v}");
        $_ENV[$k] = $v;
        $_SERVER[$k] = $v;
    }
}

function bn_default_tenant(): array
{
    bn_load_env();
    // Credenciais locais opcionais: config/local.php → return ['db' => ['host'=>..,'name'=>..,'user'=>..,'pass'=>..]];
    $local = is_file(__DIR__ . '/../config/local.php') ? (array)(include __DIR__ . '/../config/local.php') : [];
    $t = [
        'slug' => 'principal',
        'nome' => 'Banco de Negócios',
        'dominios' => ['bancodenegocios.com', 'bancodenegocios.com.br'],
        'ativo' => true,
        'principal' => true,
        'db' => [
            'host' => getenv('DB_HOST') ?: 'localhost',
            'name' => getenv('DB_NAME') ?: 'bancodenegocios80_buss1000',
            'user' => getenv('DB_USER') ?: 'bancodenegocios80_buss1000',
            'pass' => (getenv('DB_PASS') !== false && getenv('DB_PASS') !== '') ? (string)getenv('DB_PASS') : 'yBNsPw7nqrNEU5h2HaV3',
            'port' => (int)(getenv('DB_PORT') ?: 3306),
        ],
    ];
    if (!empty($local['db'])) $t['db'] = array_merge($t['db'], $local['db']);
    if (!empty($local['dominios'])) $t['dominios'] = array_values(array_unique(array_merge($t['dominios'], (array)$local['dominios'])));
    return $t;
}

/** Lista de clientes cadastrados (o principal sempre existe). */
function bn_tenants(): array
{
    static $list = null;
    if ($list !== null) return $list;
    $list = ['principal' => bn_default_tenant()];
    if (is_file(BN_TENANTS_FILE)) {
        $json = json_decode((string)file_get_contents(BN_TENANTS_FILE), true);
        foreach ((array)($json['clientes'] ?? []) as $slug => $t) {
            if (!preg_match('/^[a-z0-9][a-z0-9-]{1,40}$/', (string)$slug)) continue;
            $base = $slug === 'principal' ? $list['principal'] : ['slug' => $slug, 'principal' => false, 'ativo' => true, 'dominios' => [], 'db' => []];
            $t['db'] = array_merge($base['db'], (array)($t['db'] ?? []));
            $list[$slug] = array_merge($base, $t, ['slug' => $slug, 'principal' => $slug === 'principal']);
        }
    }
    return $list;
}

function bn_tenants_save(array $tenants): void
{
    $out = ['clientes' => []];
    foreach ($tenants as $slug => $t) {
        unset($t['slug']);
        if ($slug === 'principal') {
            // as credenciais do principal continuam em config/database.php / ambiente
            unset($t['db']);
        }
        $out['clientes'][$slug] = $t;
    }
    if (!is_dir(dirname(BN_TENANTS_FILE))) mkdir(dirname(BN_TENANTS_FILE), 0755, true);
    file_put_contents(BN_TENANTS_FILE, json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
}

function bn_is_local_host(string $host): bool
{
    return in_array($host, ['localhost', '127.0.0.1', '::1', '[::1]'], true) || str_ends_with($host, '.localhost') || str_ends_with($host, '.test');
}

/** Descobre qual cliente atender nesta requisição. */
function bn_resolve_tenant(): array
{
    $tenants = bn_tenants();
    $host = strtolower(preg_replace('/:\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? 'localhost')));
    $host = preg_replace('/^www\./', '', $host);

    // Ambiente local: ?cliente=slug (lembrado em cookie) para testar vários clientes no XAMPP
    if (PHP_SAPI === 'cli' || bn_is_local_host($host)) {
        $want = $_GET['cliente'] ?? ($_COOKIE['bn_cliente'] ?? null);
        if (is_string($want) && isset($tenants[$want]) && !empty($tenants[$want]['ativo'])) {
            if (isset($_GET['cliente']) && PHP_SAPI !== 'cli' && !headers_sent()) {
                setcookie('bn_cliente', $want, ['expires' => time() + 86400 * 30, 'path' => '/', 'samesite' => 'Lax', 'httponly' => true]);
            }
            return $tenants[$want];
        }
    }
    foreach ($tenants as $t) {
        foreach ((array)($t['dominios'] ?? []) as $d) {
            $d = strtolower(preg_replace('/^www\./', '', trim((string)$d)));
            if ($d !== '' && $d === $host && !empty($t['ativo'])) return $t;
        }
    }
    return $tenants['principal'];
}

function bn_connect(array $db, bool $withDb = true): ?PDO
{
    $primaryHost = $db['host'] ?? 'localhost';
    $hosts = array_values(array_unique(array_filter([$primaryHost, '127.0.0.1', 'localhost'])));
    $lastErr = null;
    foreach ($hosts as $h) {
        $dsn = 'mysql:host=' . $h . ';port=' . (int)($db['port'] ?? 3306) . ($withDb ? ';dbname=' . ($db['name'] ?? '') : '') . ';charset=utf8mb4';
        try {
            return new PDO($dsn, (string)($db['user'] ?? ''), (string)($db['pass'] ?? ''), [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (Throwable $e) {
            $lastErr = $e;
        }
    }
    if ($lastErr) {
        error_log('[CMS] Falha ao conectar no banco do cliente: ' . $lastErr->getMessage());
    }
    return null;
}

/** Pasta pública de mídia do cliente (relativa à raiz do site). */
function media_base(): string
{
    return BN_TENANT === 'principal' ? 'media/principal' : 'media/' . BN_TENANT;
}

/** Pasta privada do cliente (backups, logs, lixeira de arquivos). */
function storage_path(string $sub = ''): string
{
    $dir = BN_ROOT . '/storage/' . BN_TENANT . ($sub !== '' ? '/' . trim($sub, '/') : '');
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    return $dir;
}

function is_primary_tenant(): bool
{
    return !empty(BN_TENANT_DATA['principal']);
}
