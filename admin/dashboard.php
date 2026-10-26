<?php
$admin_title = 'Dashboard';
require __DIR__ . '/includes/init.php';
$count = fn(string $sql) => (int)(db_one($pdo, $sql)['n'] ?? 0);
$cards = [];
if (can('ler:anuncios')) $cards[] = ['Anúncios ativos', $count("SELECT COUNT(*) n FROM anuncios WHERE status='ativo'"), 'gerenciar_anuncios.php'];
if (can('ler:noticias')) $cards[] = ['Notícias publicadas', $count("SELECT COUNT(*) n FROM noticias WHERE estado='publicado'"), 'gerenciar_noticias.php'];
if (can('ler:paginas')) $cards[] = ['Páginas publicadas', $count("SELECT COUNT(*) n FROM paginas WHERE estado='publicado'"), 'configuracoes.php'];
if (can('ler:midia')) $cards[] = ['Arquivos na biblioteca', $count('SELECT COUNT(*) n FROM midias'), 'midia.php'];
if (can('gerenciar:usuarios')) $cards[] = ['Cadastros aguardando ativação', $count("SELECT COUNT(*) n FROM usuarios WHERE status='inativo'"), 'gerenciar_usuarios.php?status=inativo'];
$queue = workflow_queue();
$mine = db_rows($pdo, "SELECT 'noticias' tipo,id,titulo,estado FROM noticias WHERE id IN (SELECT recurso_id FROM workflow_eventos WHERE recurso='noticias' AND usuario_id=?) AND estado IN ('rascunho','revisao') UNION ALL SELECT 'paginas',id,titulo,estado FROM paginas WHERE id IN (SELECT recurso_id FROM workflow_eventos WHERE recurso='paginas' AND usuario_id=?) AND estado IN ('rascunho','revisao') LIMIT 10", [$me['id'], $me['id']]);
$recent = can('ler:auditoria') ? db_rows($pdo, 'SELECT * FROM auditoria ORDER BY id DESC LIMIT 8') : [];
$actions = audit_actions();
$diag = [];
if (can('gerenciar:configuracoes')) {
    $m = schema_missing($pdo);
    $diag = [
        ['Versão do CMS', BN_VERSION . ' · PHP ' . PHP_VERSION, version_compare(PHP_VERSION, '8.1', '>=')],
        ['Banco de dados', $pdo ? 'conectado (' . $pdo->getAttribute(PDO::ATTR_SERVER_VERSION) . ')' : 'sem conexão', (bool)$pdo],
        ['Estrutura do banco', $m ? 'faltando: ' . implode(', ', $m) : 'completa', !$m],
        ['Pasta de mídia', is_writable(BN_ROOT . '/media') || @mkdir(BN_ROOT . '/' . media_base(), 0755, true) ? 'gravável (' . media_base() . ')' : 'sem permissão de escrita', is_writable(BN_ROOT . '/media')],
        ['Pasta storage (backups/logs)', is_writable(storage_path()) ? 'gravável' : 'sem permissão de escrita', is_writable(storage_path())],
        ['Último backup', setting($pdo, 'backup_ultimo', '') ? date('d/m/Y H:i', strtotime(setting($pdo, 'backup_ultimo', ''))) : 'nenhum', setting($pdo, 'backup_ultimo', '') !== '' && time() - strtotime(setting($pdo, 'backup_ultimo', '')) < 86400 * 8],
        ['Endereço oficial do site', setting($pdo, 'site_url', '') ?: (trusted_base_url() ? 'não definido (usando ' . trusted_base_url() . ')' : 'não definido — links de recuperação de senha não serão enviados. Defina em Configurações globais › Geral.'), setting($pdo, 'site_url', '') !== ''],
        ['E-mail (SMTP)', setting($pdo, 'smtp_host', '') ? 'configurado (' . setting($pdo, 'smtp_host', '') . ')' : 'não configurado — usando mail() do servidor', setting($pdo, 'smtp_host', '') !== ''],
        ['Anúncios ativos', ($na = $count("SELECT COUNT(*) n FROM anuncios WHERE status='ativo'")) ? (string)$na : 'nenhum — o site exibe anúncios de demonstração', $na > 0],
        ['Banners ativos', ($nb = $count('SELECT COUNT(*) n FROM banners WHERE status=1')) ? (string)$nb : 'nenhum — o site exibe o banner padrão', $nb > 0],
    ];
}
include __DIR__ . '/includes/header.php';
?>
<main class="admin-content">
  <h1>Olá, <?= e(explode(' ', (string)$me['nome'])[0]) ?>!</h1>
  <p class="muted">Visão geral de <?= e(setting($pdo, 'site_name', 'CMS')) ?> · seu papel: <strong><?= e($me['papel_nome'] ?? '') ?></strong></p>

  <?php if ($cards): ?>
  <div class="stat-grid my-4">
    <?php foreach ($cards as [$label, $value, $link]): ?>
    <a class="stat-card" href="<?= e($link) ?>"><span class="muted"><?= e($label) ?></span><strong><?= $value ?></strong><span class="muted">Abrir →</span></a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <div class="split">
    <div>
      <?php if ($queue): ?>
      <div class="admin-panel mb-4">
        <h2>Aguardando sua ação <span class="badge-soft warn"><?= count($queue) ?></span></h2>
        <ul class="queue-list">
          <?php foreach ($queue as $q): ?>
          <li><span><?= workflow_badge($q['estado']) ?> <span class="muted"><?= e($q['rotulo']) ?>:</span> <strong><?= e($q['titulo']) ?></strong></span>
            <a class="btn-action" href="<?= e(content_types()[$q['tipo']]['admin']) ?>?edit=<?= (int)$q['id'] ?>"><?= $q['estado'] === 'revisao' ? 'Revisar' : 'Publicar' ?></a></li>
          <?php endforeach; ?>
        </ul>
      </div>
      <?php endif; ?>
      <?php if ($mine): ?>
      <div class="admin-panel mb-4">
        <h2>Seus rascunhos e envios</h2>
        <ul class="queue-list">
          <?php foreach ($mine as $q): ?><li><span><?= workflow_badge($q['estado']) ?> <strong><?= e($q['titulo']) ?></strong></span><a class="btn-action" href="<?= e(content_types()[$q['tipo']]['admin']) ?>?edit=<?= (int)$q['id'] ?>">Abrir</a></li><?php endforeach; ?>
        </ul>
      </div>
      <?php endif; ?>
      <div class="admin-panel mb-4">
        <h2>Ações rápidas</h2>
        <p class="d-flex flex-wrap gap-2 mb-0">
          <?php if (can('criar:anuncios')): ?><a class="btn btn-primary" href="gerenciar_anuncios.php?novo=1">+ Novo anúncio</a><?php endif; ?>
          <?php if (can('criar:noticias')): ?><a class="btn btn-outline-secondary" href="gerenciar_noticias.php?novo=1">+ Nova notícia</a><?php endif; ?>
          <?php if (can('criar:paginas')): ?><a class="btn btn-outline-secondary" href="configuracoes.php?novo=1">+ Nova página</a><?php endif; ?>
          <?php if (can('enviar:midia')): ?><a class="btn btn-outline-secondary" href="midia.php">Enviar mídia</a><?php endif; ?>
          <?php if (can('gerenciar:temas')): ?><a class="btn btn-outline-secondary" href="temas.php">Trocar tema</a><?php endif; ?>
          <a class="btn btn-outline-secondary" href="ajuda.php">Guia do administrador</a>
        </p>
      </div>
      <?php do_action('dashboard_widgets'); ?>
      <?php if ($diag): ?>
      <div class="admin-panel">
        <h2>Diagnóstico do sistema</h2>
        <table class="admin-table"><tbody>
          <?php foreach ($diag as [$label, $value, $ok]): ?>
          <tr><td style="width:40%"><?= $ok ? '✅' : '⚠️' ?> <strong><?= e($label) ?></strong></td><td style="white-space:normal"><?= e($value) ?></td></tr>
          <?php endforeach; ?>
        </tbody></table>
      </div>
      <?php endif; ?>
    </div>
    <?php if ($recent): ?>
    <aside class="side-card">
      <h3>Atividade recente</h3>
      <ul class="timeline">
        <?php foreach ($recent as $r): ?>
        <li><strong><?= e($r['usuario_nome'] ?: 'Sistema') ?></strong> — <?= e($actions[$r['acao']] ?? $r['acao']) ?><?= $r['recurso'] ? ' · ' . e($r['recurso']) : '' ?><small><?= e(date('d/m H:i', strtotime($r['criado_em']))) ?></small></li>
        <?php endforeach; ?>
      </ul>
      <a class="btn-action mt-2" href="auditoria.php">Ver todos os logs</a>
    </aside>
    <?php endif; ?>
  </div>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
