<?php
/* Tarefas agendadas (backup automático, limpeza da lixeira e logs).
   Agende no cPanel/servidor:  wget -q -O- "https://seusite.com.br/cron.php?chave=SUA_CHAVE"
   ou via terminal:            php cron.php SUA_CHAVE
   A chave aparece em Admin › Backup. Para vários clientes, chame uma vez por domínio
   (ou no terminal: php cron.php SUA_CHAVE cliente=slug). */
if (PHP_SAPI === 'cli') {
    foreach (array_slice($argv, 2) as $a) if (str_starts_with($a, 'cliente=')) $_GET['cliente'] = substr($a, 8);
}
require_once __DIR__ . '/config/database.php';
$key = PHP_SAPI === 'cli' ? ($argv[1] ?? '') : (string)($_GET['chave'] ?? '');
if (!$pdo || $key === '' || !hash_equals(setting($pdo, 'cron_chave', ''), $key)) {
    http_response_code(403);
    exit("Chave inválida.\n");
}
$GLOBALS['__bn_audit_user'] = ['id' => null, 'nome' => 'Tarefa agendada'];
$out = [];
backup_maybe_auto(); $out[] = 'backup: verificado';
trash_expire(); $out[] = 'lixeira: itens expirados removidos';
$days = max(30, (int)setting($pdo, 'auditoria_dias', '365'));
$pdo->prepare('DELETE FROM auditoria WHERE criado_em < (NOW() - INTERVAL ? DAY)')->execute([$days]); $out[] = "auditoria: registros com mais de $days dias removidos";
maintenance_active(); $out[] = 'manutenção: agendamento verificado';
do_action('cron');
save_setting($pdo, 'cron_ultimo', date('Y-m-d H:i:s'));
header('Content-Type: text/plain; charset=utf-8');
echo implode("\n", $out) . "\n";
