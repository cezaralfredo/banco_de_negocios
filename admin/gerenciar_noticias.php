<?php
$admin_title = 'Notícias';
require __DIR__ . '/includes/init.php';
$T = 'noticias';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) { flash('error', 'Sessão expirada.'); redirect('gerenciar_noticias.php'); }
    $id = (int)($_POST['id'] ?? 0);
    $old = $id ? db_one($pdo, 'SELECT * FROM noticias WHERE id=?', [$id]) : null;
    if ($id && !$old) redirect('gerenciar_noticias.php');
    $fromState = $old['estado'] ?? 'rascunho';
    deny_unless($id ? 'editar:noticias' : 'criar:noticias', 'gerenciar_noticias.php');
    if ($old && !workflow_can_edit($T, $fromState)) { flash('error', 'Este conteúdo está ' . workflow_states()[$fromState][0] . '. Somente quem pode publicar consegue alterá-lo.'); redirect('gerenciar_noticias.php?edit=' . $id); }
    $toState = (string)($_POST['estado_destino'] ?? $fromState);
    $allowed = workflow_actions($T, $fromState, !$id);
    if (!isset($allowed[$toState])) $toState = $id ? $fromState : 'rascunho';
    $titulo = trim((string)($_POST['titulo'] ?? ''));
    $conteudo = trim((string)($_POST['conteudo'] ?? ''));
    $back = 'gerenciar_noticias.php?' . ($id ? 'edit=' . $id : 'novo=1');
    if ($titulo === '' || $conteudo === '') { flash('error', 'Informe o título e o conteúdo da notícia.'); $_SESSION['form_noticia'] = $_POST; redirect($back); }
    $slug = unique_slug('noticias', trim((string)($_POST['slug'] ?? '')) ?: $titulo, $id);
    $data = trim((string)($_POST['publicado_em'] ?? ''));
    try {
        $imagem = media_field_value('imagem', 'noticia', 1600, 900);
        $seo = seo_post($old ?? []);
    } catch (Throwable $ex) { flash('error', $ex->getMessage()); $_SESSION['form_noticia'] = $_POST; redirect($back); }
    $row = [
        'titulo' => $titulo, 'slug' => $slug, 'resumo' => mb_substr(trim((string)($_POST['resumo'] ?? '')), 0, 400), 'conteudo' => $conteudo,
        'imagem' => $imagem, 'autor' => trim((string)($_POST['autor'] ?? '')), 'destaque' => isset($_POST['destaque']) ? 1 : 0,
        'publicado_em' => $data !== '' && strtotime($data) ? date('Y-m-d H:i:s', strtotime($data)) : date('Y-m-d H:i:s'),
        'estado' => $toState, 'status' => $toState === 'publicado' ? 1 : 0,
    ] + $seo;
    $cols = array_keys($row);
    if ($id) {
        $pdo->prepare('UPDATE noticias SET ' . implode(',', array_map(fn($c) => "`$c`=?", $cols)) . ' WHERE id=?')->execute([...array_values($row), $id]);
    } else {
        $pdo->prepare('INSERT INTO noticias (' . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')')->execute(array_values($row));
        $id = (int)$pdo->lastInsertId();
    }
    $comment = trim((string)($_POST['comentario'] ?? ''));
    workflow_transition($T, $id, $old ? $fromState : 'novo', $toState, $comment, $titulo);
    revision_save($T, $id, $fromState !== $toState ? 'Estado: ' . workflow_states()[$toState][0] : '');
    audit($old ? 'editar' : 'criar', $T, $id, ['titulo' => $titulo, 'estado' => $toState]);
    unset($_SESSION['form_noticia']);
    flash('success', '“' . $titulo . '” salva — ' . workflow_states()[$toState][0] . '.');
    redirect('gerenciar_noticias.php?edit=' . $id);
}
if (isset($_GET['delete']) && verify_csrf($_GET['token'] ?? null)) {
    deny_unless('excluir:noticias', 'gerenciar_noticias.php');
    $n = db_one($pdo, 'SELECT id,titulo FROM noticias WHERE id=?', [(int)$_GET['delete']]);
    if ($n) { $tid = trash_delete('noticias', (int)$n['id'], $n['titulo'], 'noticias'); flash('success', "Notícia “{$n['titulo']}” enviada para a lixeira.", undo_link($tid, 'gerenciar_noticias.php')); }
    redirect('gerenciar_noticias.php');
}

$estado = (string)($_GET['estado'] ?? '');
$q = trim((string)($_GET['q'] ?? ''));
$sql = 'SELECT * FROM noticias WHERE 1=1'; $p = [];
if (isset(workflow_states()[$estado])) { $sql .= ' AND estado=?'; $p[] = $estado; }
if ($q !== '') { $sql .= ' AND (titulo LIKE ? OR resumo LIKE ?)'; $p[] = "%$q%"; $p[] = "%$q%"; }
$news = db_rows($pdo, $sql . ' ORDER BY publicado_em DESC, id DESC', $p);
$counts = array_column(db_rows($pdo, 'SELECT estado, COUNT(*) n FROM noticias GROUP BY estado'), 'n', 'estado');
$edit = isset($_GET['edit']) ? db_one($pdo, 'SELECT * FROM noticias WHERE id=?', [(int)$_GET['edit']]) : null;
if (!empty($_SESSION['form_noticia'])) { $edit = array_merge($edit ?? [], $_SESSION['form_noticia']); unset($_SESSION['form_noticia']); }
$showForm = $edit || isset($_GET['novo']);
include __DIR__ . '/includes/header.php';
?>
<main class="admin-content">
  <div class="admin-heading">
    <div><h1>Notícias</h1><p class="muted">Conteúdo da página “Notícias” e de “Últimas notícias” na home. Fluxo: rascunho → revisão → aprovado → publicado.</p></div>
    <div class="d-flex gap-2"><a class="btn btn-outline-secondary" href="../noticias.php" target="_blank">Ver página ↗</a><?php if (can('criar:noticias')): ?><a class="btn btn-primary" href="?novo=1">+ Nova notícia</a><?php endif; ?></div>
  </div>

  <?php if ($showForm):
      $eid = (int)($edit['id'] ?? 0);
      $state = $edit['estado'] ?? 'rascunho';
      $canEdit = $eid ? workflow_can_edit($T, $state) : can('criar:noticias');
      $acts = workflow_actions($T, $state, !$eid); ?>
  <form method="post" action="gerenciar_noticias.php" enctype="multipart/form-data" data-dirty-check>
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="id" value="<?= $eid ?>">
    <div class="split mb-4">
      <div class="admin-panel edit-panel<?= $eid ? ' is-editing' : '' ?>">
        <div class="edit-panel-head"><?php if ($eid): ?><span class="edit-badge">✎ Editando</span><h2><?= e($edit['titulo']) ?></h2><?= workflow_badge($state) ?><?php else: ?><h2>Nova notícia</h2><?php endif; ?></div>
        <?php if (!$canEdit): ?><p class="notice error">Esta notícia está <strong><?= e(workflow_states()[$state][0]) ?></strong>. Somente quem tem permissão para publicar pode alterá-la.</p><?php endif; ?>
        <fieldset <?= $canEdit ? '' : 'disabled' ?> class="admin-form" style="padding:0;max-width:none">
          <label>Título<input name="titulo" required maxlength="200" value="<?= e($edit['titulo'] ?? '') ?>"></label>
          <div class="form-grid-2">
            <label>URL amigável (slug)<input name="slug" pattern="[a-z0-9-]*" value="<?= e($edit['slug'] ?? '') ?>" data-auto-slug><small class="muted">Endereço: <?= e(site_base_url() . url_noticia($edit['slug'] ?? 'exemplo')) ?></small></label>
            <label>Data de publicação<input type="datetime-local" name="publicado_em" value="<?= e(date('Y-m-d\TH:i', strtotime($edit['publicado_em'] ?? 'now'))) ?>"><small class="muted">Datas futuras ficam agendadas.</small></label>
          </div>
          <label>Resumo<textarea name="resumo" maxlength="400" style="min-height:80px" data-count="400"><?= e($edit['resumo'] ?? '') ?></textarea></label>
          <div class="editor-toolbar" data-editor="#news-content-editor">
            <button type="button" data-editor-tag="<p>|</p>">Parágrafo</button><button type="button" data-editor-tag="<strong>|</strong>">Negrito</button><button type="button" data-editor-tag="<h2>|</h2>">Título</button><button type="button" data-editor-tag="<ul><li>|</li></ul>">Lista</button><button type="button" data-editor-tag='<a href="https://">|</a>'>Link</button><button type="button" data-editor-image>Imagem da biblioteca</button>
          </div>
          <label>Conteúdo<textarea id="news-content-editor" name="conteudo" required style="min-height:280px"><?= e($edit['conteudo'] ?? '') ?></textarea></label>
          <?= media_field('imagem', $edit['imagem'] ?? '', 'Imagem de capa', 'Recomendado 1600×900.') ?>
          <div class="form-grid-2">
            <label>Autor<input name="autor" value="<?= e($edit['autor'] ?? ($me['nome'] ?? '')) ?>"></label>
            <label class="check-row align-self-end"><input type="checkbox" name="destaque" value="1" <?= !empty($edit['destaque']) ? 'checked' : '' ?>> Fixar no topo (destaque)</label>
          </div>
          <?= seo_fields($edit ?? []) ?>
        </fieldset>
      </div>
      <aside>
        <div class="side-card">
          <h3>Publicação</h3>
          <p class="small mb-2">Estado atual: <?= workflow_badge($eid ? $state : 'rascunho') ?></p>
          <?php if ($canEdit && $acts): ?>
          <label class="small w-100 mb-2">Comentário para o fluxo (opcional)<textarea name="comentario" class="form-control form-control-sm" rows="2" placeholder="Ex.: ajustar o título"></textarea></label>
          <div class="wf-actions">
            <?php foreach ($acts as $to => $label): ?>
              <button class="btn <?= in_array($to, ['publicado', 'aprovado'], true) ? 'btn-primary' : ($to === 'arquivado' ? 'btn-outline-dark' : 'btn-outline-secondary') ?>" name="estado_destino" value="<?= e($to) ?>" <?= $to === 'arquivado' || ($to === 'rascunho' && $state === 'publicado') ? 'data-confirm="' . e($label) . '? A notícia deixará de aparecer no site." data-confirm-style="primary" data-confirm-button="' . e($label) . '"' : '' ?>><?= e($label) ?></button>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
          <?php if ($eid): ?>
          <hr>
          <div class="d-grid gap-2">
            <a class="btn btn-sm btn-outline-secondary" href="../noticia.php?slug=<?= urlencode($edit['slug']) ?>&preview=1" target="_blank">👁 Pré-visualizar</a>
            <?php if (can('ler:versoes')): ?><a class="btn btn-sm btn-outline-secondary" href="versoes.php?tipo=noticias&id=<?= $eid ?>">🕘 Histórico de versões (<?= count(revisions_list($T, $eid)) ?>)</a><?php endif; ?>
            <?php if (can('excluir:noticias')): ?><a class="btn btn-sm btn-outline-danger" href="?delete=<?= $eid ?>&token=<?= e(csrf_token()) ?>" data-confirm="Enviar “<?= e($edit['titulo']) ?>” para a lixeira? Você poderá desfazer.">Excluir</a><?php endif; ?>
          </div>
          <?php endif; ?>
          <a class="btn btn-link btn-sm mt-2 px-0" href="gerenciar_noticias.php">← Voltar à lista</a>
        </div>
        <?php if ($eid && ($hist = workflow_history($T, $eid))): ?>
        <div class="side-card"><h3>Histórico do fluxo</h3><ul class="timeline"><?php foreach ($hist as $h): ?><li><?= workflow_badge($h['para']) ?> por <strong><?= e($h['usuario_nome']) ?></strong><?= $h['comentario'] ? '<br>“' . e($h['comentario']) . '”' : '' ?><small><?= e(date('d/m/Y H:i', strtotime($h['criado_em']))) ?></small></li><?php endforeach; ?></ul></div>
        <?php endif; ?>
      </aside>
    </div>
  </form>
  <?php endif; ?>

  <div class="admin-panel">
    <nav class="admin-tabs" style="margin-top:0">
      <a class="<?= $estado === '' ? 'active' : '' ?>" href="gerenciar_noticias.php">Todas (<?= array_sum($counts) ?>)</a>
      <?php foreach (workflow_states() as $k => [$l]): ?><a class="<?= $estado === $k ? 'active' : '' ?>" href="?estado=<?= $k ?>"><?= e($l) ?> (<?= (int)($counts[$k] ?? 0) ?>)</a><?php endforeach; ?>
    </nav>
    <form class="admin-filter" method="get"><?php if ($estado): ?><input type="hidden" name="estado" value="<?= e($estado) ?>"><?php endif; ?><input name="q" value="<?= e($q) ?>" placeholder="Buscar notícia"><button class="btn btn-outline-secondary">Buscar</button></form>
    <div class="table-responsive">
      <table class="admin-table">
        <thead><tr><th>Data</th><th>Título</th><th>Estado</th><th>Destaque</th><th>Ações</th></tr></thead>
        <tbody>
        <?php foreach ($news as $n): $scheduled = $n['estado'] === 'publicado' && strtotime($n['publicado_em']) > time(); $active = $edit && (int)($edit['id'] ?? 0) === (int)$n['id']; ?>
          <tr class="<?= $active ? 'is-active-row' : '' ?>">
            <td><?= e(date('d/m/Y H:i', strtotime($n['publicado_em']))) ?></td>
            <td><strong><?= e($n['titulo']) ?></strong><br><small class="muted"><?= e(url_noticia($n['slug'])) ?></small></td>
            <td><?= workflow_badge($n['estado']) ?><?= $scheduled ? ' <span class="badge-soft warn">agendada</span>' : '' ?></td>
            <td><?= $n['destaque'] ? '★' : '—' ?></td>
            <td class="actions"><a class="btn-action<?= $active ? ' is-active' : '' ?>" href="?edit=<?= (int)$n['id'] ?>"><?= $active ? '✎ Editando' : 'Editar' ?></a> <a class="btn-action" href="../noticia.php?slug=<?= urlencode($n['slug']) ?>&preview=1" target="_blank">Ver</a><?php if (can('excluir:noticias')): ?> <a class="btn-action danger" href="?delete=<?= (int)$n['id'] ?>&token=<?= e(csrf_token()) ?>" data-confirm="Enviar “<?= e($n['titulo']) ?>” para a lixeira? Você poderá desfazer.">Excluir</a><?php endif; ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$news): ?><tr><td colspan="5" class="muted">Nenhuma notícia encontrada.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
