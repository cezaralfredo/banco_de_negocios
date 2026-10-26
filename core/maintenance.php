<?php
declare(strict_types=1);

/* ==========================================================================
   Modo de manutenção
   - Ativado/desativado em Admin › Manutenção (permissão gerenciar:manutencao).
   - Visitantes recebem uma página personalizada (HTTP 503 + Retry-After).
   - Usuários logados com a permissão "acessar:manutencao" veem o site normalmente.
   - Também é possível liberar IPs ou compartilhar um link secreto de visualização.
   - Agendamento: início e fim (o modo é desligado automaticamente no fim).
   ========================================================================== */

function maintenance_active(): bool
{
    global $pdo;
    static $active = null;
    if ($active !== null) return $active;
    if (setting($pdo, 'manutencao_ativa', '0') !== '1') return $active = false;
    $now = time();
    $start = setting($pdo, 'manutencao_inicio', '');
    $end = setting($pdo, 'manutencao_fim', '');
    if ($start !== '' && strtotime($start) > $now) return $active = false;
    if ($end !== '' && strtotime($end) <= $now && setting($pdo, 'manutencao_auto_desligar', '1') === '1') {
        save_setting($pdo, 'manutencao_ativa', '0');
        $GLOBALS['__bn_audit_user'] = ['id' => null, 'nome' => 'Sistema (agendamento)'];
        audit('manutencao_desativada', 'manutencao', null, ['motivo' => 'horário de término atingido']);
        unset($GLOBALS['__bn_audit_user']);
        return $active = false;
    }
    return $active = true;
}

/** Verdadeiro se o visitante atual pode navegar durante a manutenção. */
function maintenance_bypass(): bool
{
    global $pdo;
    $token = setting($pdo, 'manutencao_token', '');
    if ($token !== '' && isset($_GET['preview']) && hash_equals($token, (string)$_GET['preview'])) {
        setcookie('cms_mnt', hash('sha256', $token), ['expires' => time() + 86400, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
        return true;
    }
    if ($token !== '' && isset($_COOKIE['cms_mnt']) && hash_equals(hash('sha256', $token), (string)$_COOKIE['cms_mnt'])) return true;
    $ips = array_filter(array_map('trim', preg_split('/[\s,]+/', setting($pdo, 'manutencao_ips', ''))));
    if ($ips && in_array(client_ip(), $ips, true)) return true;
    return can('acessar:manutencao');
}

/** Chamado no início de cada página pública. */
function maintenance_gate(): void
{
    if (!maintenance_active()) return;
    $script = basename($_SERVER['SCRIPT_NAME'] ?? '');
    if (in_array($script, ['cookie_consent.php', 'robots.php', 'cron.php'], true)) return;
    if (maintenance_bypass()) {
        $GLOBALS['__bn_maintenance_bypass'] = true;
        return;
    }
    maintenance_render();
}

function maintenance_render(bool $preview = false): never
{
    global $pdo;
    $end = setting($pdo, 'manutencao_fim', '');
    if (!$preview) {
        http_response_code(503);
        header('Retry-After: ' . ($end && strtotime($end) > time() ? max(60, strtotime($end) - time()) : 3600));
        header('Cache-Control: no-store');
    }
    include BN_ROOT . '/includes/maintenance_page.php';
    exit;
}
