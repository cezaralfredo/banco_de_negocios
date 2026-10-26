<?php
declare(strict_types=1);

/* ==========================================================================
   Inicialização do CMS (carregada por config/database.php em todas as páginas)
   1. Identifica o cliente (multi-cliente) e conecta ao banco dele
   2. Carrega os módulos do núcleo
   3. Sessão segura, cabeçalhos HTTP, migração do banco
   4. Plugins ativos e hook "init"
   5. Modo de manutenção (somente páginas públicas)
   ========================================================================== */

if (defined('BN_ROOT')) return;
define('BN_ROOT', dirname(__DIR__));
define('BN_VERSION', '8.0.0');

require_once __DIR__ . '/tenant.php';
$__tenant = bn_resolve_tenant();
define('BN_TENANT', $__tenant['slug']);
define('BN_TENANT_DATA', array_diff_key($__tenant, ['db' => 1]));

require_once BN_ROOT . '/includes/functions.php';
foreach (['hooks', 'security', 'crypto', 'audit', 'rbac', 'trash', 'revisions', 'mailer', 'media', 'seo', 'themes', 'workflow', 'maintenance', 'plugins', 'backup', 'schema'] as $__m) {
    require_once __DIR__ . "/$__m.php";
}

$pdo = bn_connect($__tenant['db']);
unset($__tenant, $__m);

function db(): ?PDO
{
    return $GLOBALS['pdo'] ?? null;
}

$__script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_FILENAME'] ?? ''));
define('BN_IS_ADMIN', str_contains($__script, '/admin/'));
unset($__script);

date_default_timezone_set(setting($pdo, 'fuso_horario', 'America/Sao_Paulo') ?: 'America/Sao_Paulo');
start_session();
send_security_headers(BN_IS_ADMIN);

if ($pdo) {
    ensure_schema($pdo);
    try { $pdo->exec("SET time_zone = '" . (new DateTime())->format('P') . "'"); } catch (Throwable $e) { }
}

plugins_load();
do_action('init');

if (!BN_IS_ADMIN && PHP_SAPI !== 'cli' && $pdo) {
    maintenance_gate();
}
