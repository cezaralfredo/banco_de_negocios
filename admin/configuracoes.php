<?php
$admin_title = 'Páginas';
require __DIR__ . '/includes/init.php';
$T = 'paginas';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) { flash('error', 'Sessão expirada.'); redirect('configuracoes.php'); }
    $id = (int)($_POST['id'] ?? 0);
    $old = $id ? db_one($pdo, 'SELECT * FROM paginas WHERE id=?', [$id]) : null;
    if ($id && !$old) redirect('configuracoes.php');
    $fromState = $old['estado'] ?? 'rascunho';
    deny_unless($id ? 'editar:paginas' : 'criar:paginas', 'configuracoes.php');
    if ($old && !workflow_can_edit($T, $fromState)) { flash('error', 'Esta página está ' . workflow_states()[$fromState][0] . '. Somente quem pode publicar consegue alterá-la.'); redirect('configuracoes.php?edit=' . $id); }
    $toState = (string)($_POST['estado_destino'] ?? $fromState);
    if (!isset(workflow_actions($T, $fromState, !$id)[$toState])) $toState = $id ? $fromState : 'rascunho';
    $titulo = trim((string)($_POST['titulo'] ?? ''));
    $conteudo = trim((string)($_POST['conteudo'] ?? ''));
    $parent = (int)($_POST['parent_id'] ?? 0) ?: null;
    $back = 'configuracoes.php?' . ($id ? 'edit=' . $id : 'novo=1');
    if ($titulo === '' || $conteudo === '') { flash('error', 'Informe título e conteúdo.'); $_SESSION['form_pagina'] = $_POST; redirect($back); }
    if ($id && $parent === $id) { flash('error', 'A página não pode ser filha dela mesma.'); redirect($back); }
    try {
        $imagem = media_field_value('imagem', 'pagina', 1600, 900);
        $seo = seo_post($old ?? []);
    } catch (Throwable $ex) { flash('error', $ex->getMessage()); redirect($back); }
    $row = [
        'titulo' => $titulo, 'slug' => unique_slug('paginas', trim((string)($_POST['slug'] ?? '')) ?: $titulo, $id), 'conteudo' => $conteudo,
        'imagem' => $imagem, 'parent_id' => $parent, 'ordem' => max(0, (int)($_POST['ordem'] ?? 0)),
        'estado' => $toState, 'status' => $toState === 'publicado' ? 1 : 0,
    ] + $seo;
    $cols = array_keys($row);
    if ($id) $pdo->prepare('UPDATE paginas SET ' . implode(',', array_map(fn($c) => "`$c`=?", $cols)) . ' WHERE id=?')->execute([...array_values($row), $id]);
    else { $pdo->prepare('INSERT INTO paginas (' . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')')->execute(array_values($row)); $id = (int)$pdo->lastInsertId(); }
    workflow_transition($T, $id, $old ? $fromState : 'novo', $toState, trim((string)($_POST['comentario'] ?? '')), $titulo);
    revision_save($T, $id, $fromState !== $toState ? 'Estado: ' . workflow_states()[$toState][0] : '');
    audit($old ? 'editar' : 'criar', $T, $id, ['titulo' => $titulo, 'estado' => $toState]);
    unset($_SESSION['form_pagina']);
    flash('success', "Página “{$titulo}” salva — " . workflow_states()[$toState][0] . '.');
    redirect('configuracoes.php?edit=' . $id);
}
if (isset($_GET['delete']) && verify_csrf($_GET['token'] ?? null)) {
    deny_unless('excluir:paginas', 'configuracoes.php');
    $pg = db_one($pdo, 'SELECT id,titulo FROM paginas WHERE id=?', [(int)$_GET['delete']]);
    if ($pg) {
        $children = (int)(db_one($pdo, 'SELECT COUNT(*) n FROM paginas WHERE parent_id=?', [$pg['id']])['n'] ?? 0);
        if ($children) $pdo->prepare('UPDATE paginas SET parent_id=NULL WHERE parent_id=?')->execute([$pg['id']]);
        $tid = trash_delete('paginas', (int)$pg['id'], $pg['titulo'], 'paginas');
        flash('success', "Página “{$pg['titulo']}” enviada para a lixeira." . ($children ? " Suas $children subpágina(s) viraram páginas principais." : ''), undo_link($tid, 'configuracoes.php'));
    }
    redirect('configuracoes.php');
}
$pages = db_rows($pdo, 'SELECT * FROM paginas ORDER BY COALESCE(parent_id,id), parent_id IS NOT NULL, ordem, id');
$parents = array_filter($pages, fn($pg) => empty($pg['parent_id']));
$edit = isset($_GET['edit']) ? db_one($pdo, 'SELECT * FROM paginas WHERE id=?', [(int)$_GET['edit']]) : null;
if (!empty($_SESSION['form_pagina'])) { $edit = array_merge($edit ?? [], $_SESSION['form_pagina']); unset($_SESSION['form_pagina']); }
$showForm = $edit || isset($_GET['novo']);
include __DIR__ . '/includes/header.php';
?>
<main class="admin-content">
  <div class="admin-heading">
    <div><h1>Páginas</h1><p class="muted">Páginas institucionais (Sobre, Termos, Política de Privacidade…). O texto da barra superior agora fica em <a href="configuracoes_gerais.php">Configurações globais</a>.</p></div>
    <?php if (can('criar:paginas')): ?><a class="btn btn-primary" href="?novo=1">+ Nova página</a><?php endif; ?>
  </div>
  <?php if ($showForm):
      $eid = (int)($edit['id'] ?? 0); $state = $edit['estado'] ?? 'rascunho';
      $canEdit = $eid ? workflow_can_edit($T, $state) : can('criar:paginas');
      $acts = workflow_actions($T, $state, !$eid); ?>
  <form method="post" action="configuracoes.php" enctype="multipart/form-data" data-dirty-check>
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="id" value="<?= $eid ?>">
    <div class="split mb-4">
      <div class="admin-panel edit-panel<?= $eid ? ' is-editing' : '' ?>">
        <div class="edit-panel-head"><?php if ($eid): ?><span class="edit-badge">✎ Editando</span><h2><?= e($edit['titulo']) ?></h2><?= workflow_badge($state) ?><?php else: ?><h2>Nova página</h2><?php endif; ?></div>
        <?php if (!$canEdit): ?><p class="notice error">Esta página está <strong><?= e(workflow_states()[$state][0]) ?></strong>. Somente quem pode publicar consegue alterá-la.</p><?php endif; ?>
        <fieldset <?= $canEdit ? '' : 'disabled' ?> class="admin-form" style="padding:0;max-width:none">
          <label>Título<input name="titulo" required value="<?= e($edit['titulo'] ?? '') ?>"></label>
          <label>URL amigável (slug)<input name="slug" pattern="[a-z0-9-]*" value="<?= e($edit['slug'] ?? '') ?>" data-auto-slug><small class="muted">Endereço: <?= e(site_base_url() . url_pagina($edit['slug'] ?? 'exemplo')) ?></small></label>
          <div class="form-grid-2">
            <label>Página pai<select name="parent_id"><option value="0">Nenhuma (página principal)</option><?php foreach ($parents as $pp): if ((int)$pp['id'] === $eid) continue; ?><option value="<?= (int)$pp['id'] ?>" <?= (int)($edit['parent_id'] ?? 0) === (int)$pp['id'] ? 'selected' : '' ?>><?= e($pp['titulo']) ?></option><?php endforeach; ?></select></label>
            <label>Ordem<input type="number" name="ordem" min="0" value="<?= (int)($edit['ordem'] ?? 0) ?>"></label>
          </div>
          <?= media_field('imagem', $edit['imagem'] ?? '', 'Imagem de capa') ?>
          <div class="editor-toolbar" data-editor="#page-content-editor"><button type="button" data-editor-tag="<p>|</p>">Parágrafo</button><button type="button" data-editor-tag="<strong>|</strong>">Negrito</button><button type="button" data-editor-tag="<h2>|</h2>">Título</button><button type="button" data-editor-tag="<ul><li>|</li></ul>">Lista</button><button type="button" data-editor-tag='<a href="https://">|</a>'>Link</button><button type="button" data-editor-image>Imagem da biblioteca</button></div>
          <label>Conteúdo (HTML básico)<textarea id="page-content-editor" name="conteudo" required style="min-height:300px"><?= e($edit['conteudo'] ?? '') ?></textarea></label>
          <?= seo_fields($edit ?? []) ?>
        </fieldset>
      </div>
      <aside>
        <div class="side-card">
          <h3>Publicação</h3>
          <p class="small mb-2">Estado atual: <?= workflow_badge($eid ? $state : 'rascunho') ?></p>
          <?php if ($canEdit && $acts): ?>
          <label class="small w-100 mb-2">Comentário (opcional)<textarea name="comentario" class="form-control form-control-sm" rows="2"></textarea></label>
          <div class="wf-actions"><?php foreach ($acts as $to => $label): ?><button class="btn <?= in_array($to, ['publicado', 'aprovado'], true) ? 'btn-primary' : ($to === 'arquivado' ? 'btn-outline-dark' : 'btn-outline-secondary') ?>" name="estado_destino" value="<?= e($to) ?>" <?= $to === 'arquivado' || ($to === 'rascunho' && $state === 'publicado') ? 'data-confirm="' . e($label) . '? A página deixará de aparecer no site." data-confirm-style="primary" data-confirm-button="' . e($label) . '"' : '' ?>><?= e($label) ?></button><?php endforeach; ?></div>
          <?php endif; ?>
          <?php if ($eid): ?><hr><div class="d-grid gap-2">
            <a class="btn btn-sm btn-outline-secondary" href="../pagina.php?slug=<?= urlencode($edit['slug']) ?>&preview=1" target="_blank">👁 Pré-visualizar</a>
            <?php if (can('ler:versoes')): ?><a class="btn btn-sm btn-outline-secondary" href="versoes.php?tipo=paginas&id=<?= $eid ?>">🕘 Histórico de versões (<?= count(revisions_list($T, $eid)) ?>)</a><?php endif; ?>
            <?php if (can('excluir:paginas')): ?><a class="btn btn-sm btn-outline-danger" href="?delete=<?= $eid ?>&token=<?= e(csrf_token()) ?>" data-confirm="Enviar a página “<?= e($edit['titulo']) ?>” para a lixeira? Links do menu que apontam para ela deixarão de funcionar até você restaurá-la.">Excluir</a><?php endif; ?>
          </div><?php endif; ?>
          <a class="btn btn-link btn-sm mt-2 px-0" href="configuracoes.php">← Voltar à lista</a>
        </div>
        <?php if ($eid && ($hist = workflow_history($T, $eid))): ?><div class="side-card"><h3>Histórico do fluxo</h3><ul class="timeline"><?php foreach ($hist as $h): ?><li><?= workflow_badge($h['para']) ?> por <strong><?= e($h['usuario_nome']) ?></strong><?= $h['comentario'] ? '<br>“' . e($h['comentario']) . '”' : '' ?><small><?= e(date('d/m/Y H:i', strtotime($h['criado_em']))) ?></small></li><?php endforeach; ?></ul></div><?php endif; ?>
      </aside>
    </div>
  </form>
  <?php endif; ?>
  <div class="admin-panel">
    <div class="table-responsive">
      <table class="admin-table">
        <thead><tr><th>Título</th><th>Endereço</th><th>Estado</th><th>Ordem</th><th>Ações</th></tr></thead>
        <tbody>
        <?php foreach ($pages as $pg): $active = $edit && (int)($edit['id'] ?? 0) === (int)$pg['id']; ?>
          <tr class="<?= $active ? 'is-active-row' : '' ?>">
            <td><?= $pg['parent_id'] ? '<span class="muted">↳</span> ' : '' ?><strong><?= e($pg['titulo']) ?></strong></td>
            <td><small><?= e(url_pagina($pg['slug'])) ?></small></td>
            <td><?= workflow_badge($pg['estado'] ?? 'publicado') ?></td>
            <td><?= (int)$pg['ordem'] ?></td>
            <td class="actions"><a class="btn-action<?= $active ? ' is-active' : '' ?>" href="?edit=<?= (int)$pg['id'] ?>"><?= $active ? '✎ Editando' : 'Editar' ?></a> <a class="btn-action" href="../pagina.php?slug=<?= urlencode($pg['slug']) ?>&preview=1" target="_blank">Ver</a><?php if (can('excluir:paginas')): ?> <a class="btn-action danger" href="?delete=<?= (int)$pg['id'] ?>&token=<?= e(csrf_token()) ?>" data-confirm="Enviar a página “<?= e($pg['titulo']) ?>” para a lixeira?">Excluir</a><?php endif; ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
