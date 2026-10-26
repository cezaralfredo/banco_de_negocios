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

function bn_default_tenant(): array
{
    // Credenciais locais opcionais: config/local.php → return ['db' => ['host'=>..,'name'=>..,'user'=>..,'pass'=>..]];
    $local = is_file(__DIR__ . '/../config/local.php') ? (array)(include __DIR__ . '/../config/local.php') : [];
    $t = [
        'slug' => 'principal',
        'nome' => 'Banco de Negócios',
        'dominios' => [],
        'ativo' => true,
        'principal' => true,
        'db' => [
            'host' => getenv('DB_HOST') ?: '127.0.0.1',
            'name' => getenv('DB_NAME') ?: 'banco_negocios',
            'user' => getenv('DB_USER') ?: 'root',
            'pass' => getenv('DB_PASS') ?: '',
            'port' => (int)(getenv('DB_PORT') ?: 3306),
        ],
    ];
    if (!empty($local['db'])) $t['db'] = array_merge($t['db'], $local['db']);
    if (!empty($local['dominios'])) $t['dominios'] = (array)$local['dominios'];
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
    $dsn = 'mysql:host=' . ($db['host'] ?? '127.0.0.1') . ';port=' . (int)($db['port'] ?? 3306) . ($withDb ? ';dbname=' . ($db['name'] ?? '') : '') . ';charset=utf8mb4';
    try {
        return new PDO($dsn, (string)($db['user'] ?? ''), (string)($db['pass'] ?? ''), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } catch (Throwable $e) {
        error_log('[CMS] Falha ao conectar no banco do cliente: ' . $e->getMessage());
        return null;
    }
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
