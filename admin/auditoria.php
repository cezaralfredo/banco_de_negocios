<?php
$admin_title = 'Logs de auditoria';
require __DIR__ . '/includes/init.php';
$actions = audit_actions();
$f = [
    'acao' => (string)($_GET['acao'] ?? ''), 'usuario' => (int)($_GET['usuario'] ?? 0), 'recurso' => trim((string)($_GET['recurso'] ?? '')),
    'de' => (string)($_GET['de'] ?? ''), 'ate' => (string)($_GET['ate'] ?? ''), 'q' => trim((string)($_GET['q'] ?? '')),
];
$where = ' WHERE 1=1'; $p = [];
if ($f['acao'] !== '') { $where .= ' AND acao=?'; $p[] = $f['acao']; }
if ($f['usuario']) { $where .= ' AND usuario_id=?'; $p[] = $f['usuario']; }
if ($f['recurso'] !== '') { $where .= ' AND recurso=?'; $p[] = $f['recurso']; }
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $f['de'])) { $where .= ' AND criado_em >= ?'; $p[] = $f['de'] . ' 00:00:00'; }
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $f['ate'])) { $where .= ' AND criado_em <= ?'; $p[] = $f['ate'] . ' 23:59:59'; }
if ($f['q'] !== '') { $where .= ' AND (detalhes LIKE ? OR usuario_nome LIKE ? OR ip LIKE ?)'; array_push($p, "%{$f['q']}%", "%{$f['q']}%", "%{$f['q']}%"); }

if (isset($_GET['csv'])) {
    audit('configuracoes', 'auditoria', null, ['exportacao_csv' => true]);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="auditoria-' . BN_TENANT . '-' . date('Ymd-His') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Data', 'Usuário', 'Ação', 'Recurso', 'ID', 'Detalhes', 'IP', 'Navegador'], ';');
    $safe = fn($v) => is_string($v) && preg_match('/^[=+\-@\t\r]/', $v) ? "'" . $v : $v; // evita fórmulas no Excel
    foreach (db_rows($pdo, 'SELECT * FROM auditoria' . $where . ' ORDER BY id DESC LIMIT 20000', $p) as $r) {
        fputcsv($out, array_map($safe, [$r['criado_em'], $r['usuario_nome'], $actions[$r['acao']] ?? $r['acao'], $r['recurso'], $r['recurso_id'], $r['detalhes'], $r['ip'], $r['user_agent']]), ';');
    }
    exit;
}
$per = 50;
$page = max(1, (int)($_GET['p'] ?? 1));
$total = (int)(db_one($pdo, 'SELECT COUNT(*) n FROM auditoria' . $where, $p)['n'] ?? 0);
$rows = db_rows($pdo, 'SELECT * FROM auditoria' . $where . ' ORDER BY id DESC LIMIT ' . $per . ' OFFSET ' . (($page - 1) * $per), $p);
$users = db_rows($pdo, 'SELECT DISTINCT usuario_id id, usuario_nome nome FROM auditoria WHERE usuario_id IS NOT NULL ORDER BY usuario_nome');
$resources = array_column(db_rows($pdo, 'SELECT DISTINCT recurso FROM auditoria WHERE recurso IS NOT NULL AND recurso<>\'\' ORDER BY recurso'), 'recurso');
$critical = ['login_falhou', 'login_bloqueado', 'excluir', 'excluir_definitivo', 'permissoes', 'papel_usuario', 'manutencao_ativada', 'manutencao_desativada', 'backup_restaurado', 'acesso_negado'];
$qs = fn(array $extra) => '?' . http_build_query(array_filter(array_merge($f, $extra), fn($v) => $v !== '' && $v !== 0));
include __DIR__ . '/includes/header.php';
?>
<main class="admin-content">
  <div class="admin-heading">
    <div><h1>Logs de auditoria</h1><p class="muted">Registro das ações importantes: logins, alterações de permissões, manutenção, exclusões, backups e mudanças de conteúdo. Mantidos por <?= (int)setting($pdo, 'auditoria_dias', '365') ?> dias.</p></div>
    <a class="btn btn-outline-secondary" href="<?= e($qs(['csv' => 1])) ?>">⬇ Exportar CSV</a>
  </div>
  <div class="admin-panel">
    <form class="admin-filter" method="get">
      <select name="acao"><option value="">Todas as ações</option><?php foreach ($actions as $k => $l): ?><option value="<?= e($k) ?>" <?= $f['acao'] === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select>
      <select name="usuario"><option value="0">Todos os usuários</option><?php foreach ($users as $u): ?><option value="<?= (int)$u['id'] ?>" <?= $f['usuario'] === (int)$u['id'] ? 'selected' : '' ?>><?= e($u['nome']) ?></option><?php endforeach; ?></select>
      <select name="recurso"><option value="">Todos os recursos</option><?php foreach ($resources as $r): ?><option <?= $f['recurso'] === $r ? 'selected' : '' ?>><?= e($r) ?></option><?php endforeach; ?></select>
      <input type="date" name="de" value="<?= e($f['de']) ?>" aria-label="De" style="min-width:0">
      <input type="date" name="ate" value="<?= e($f['ate']) ?>" aria-label="Até" style="min-width:0">
      <input name="q" value="<?= e($f['q']) ?>" placeholder="Buscar nos detalhes ou IP">
      <button class="btn btn-outline-secondary">Filtrar</button> <a href="auditoria.php" class="btn btn-link btn-sm">Limpar</a>
    </form>
    <p class="muted"><?= $total ?> registro(s)</p>
    <div class="table-responsive">
      <table class="admin-table">
        <thead><tr><th>Data e hora</th><th>Usuário</th><th>Ação</th><th>Recurso</th><th>Detalhes</th><th>IP</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): $d = json_decode((string)$r['detalhes'], true) ?: []; ?>
          <tr>
            <td><?= e(date('d/m/Y H:i:s', strtotime($r['criado_em']))) ?></td>
            <td><?= e($r['usuario_nome'] ?: 'Sistema') ?></td>
            <td><span class="badge-soft <?= in_array($r['acao'], $critical, true) ? 'danger' : '' ?>"><?= e($actions[$r['acao']] ?? $r['acao']) ?></span></td>
            <td><?= e($r['recurso']) ?><?= $r['recurso_id'] ? ' #' . e($r['recurso_id']) : '' ?></td>
            <td style="white-space:normal;max-width:420px;font-size:12px"><?php foreach ($d as $k => $v): ?><span class="me-2"><strong><?= e((string)$k) ?>:</strong> <?= e(is_scalar($v) ? (is_bool($v) ? ($v ? 'sim' : 'não') : (string)$v) : json_encode($v, JSON_UNESCAPED_UNICODE)) ?></span><?php endforeach; ?></td>
            <td title="<?= e($r['user_agent']) ?>"><?= e($r['ip']) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="6" class="muted">Nenhum registro.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
    <?php if ($total > $per): ?>
    <nav class="d-flex gap-2 align-items-center mt-3">
      <?php if ($page > 1): ?><a class="btn-action" href="<?= e($qs(['p' => $page - 1])) ?>">← Anteriores</a><?php endif; ?>
      <span class="muted">Página <?= $page ?> de <?= (int)ceil($total / $per) ?></span>
      <?php if ($page * $per < $total): ?><a class="btn-action" href="<?= e($qs(['p' => $page + 1])) ?>">Próximos →</a><?php endif; ?>
    </nav>
    <?php endif; ?>
  </div>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
