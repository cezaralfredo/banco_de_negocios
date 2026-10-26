<?php
$admin_title = 'Anúncios';
require __DIR__ . '/includes/init.php';
$tipos = ['servico' => 'Serviço', 'produto' => 'Produto', 'ambos' => 'Produto e serviço'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) { flash('error', 'Sessão expirada.'); redirect('gerenciar_anuncios.php'); }
    $id = (int)($_POST['id'] ?? 0);
    $old = $id ? db_one($pdo, 'SELECT * FROM anuncios WHERE id=?', [$id]) : null;
    if ($id && !$old) redirect('gerenciar_anuncios.php');
    deny_unless($id ? 'editar:anuncios' : 'criar:anuncios', 'gerenciar_anuncios.php');
    $back = 'gerenciar_anuncios.php?' . ($id ? 'edit=' . $id : 'novo=1');
    $titulo = trim((string)($_POST['titulo'] ?? ''));
    $descricao = trim((string)($_POST['descricao'] ?? ''));
    if ($titulo === '' || $descricao === '') { flash('error', 'Preencha o título e a descrição.'); $_SESSION['form_anuncio'] = $_POST; redirect($back); }
    // Ativar/desativar (publicar) exige permissão própria
    $status = ($_POST['status'] ?? 'ativo') === 'inativo' ? 'inativo' : 'ativo';
    $notice = '';
    if (!can('publicar:anuncios')) {
        $wanted = $status;
        $status = $old['status'] ?? 'inativo';
        if ($wanted !== $status) $notice = ' O status não foi alterado: publicar/despublicar exige a permissão “Publicar anúncios”.';
        if (!$old) $notice = ' O anúncio foi salvo como inativo e aguarda publicação por um responsável.';
    }
    try {
        $capa = media_field_value('imagem_destaque', 'capa', 1400, 700);
        $logo = media_field_value('logo', 'logo', 500, 500);
        $seo = seo_post($old ?? []);
    } catch (Throwable $ex) { flash('error', $ex->getMessage()); $_SESSION['form_anuncio'] = $_POST; redirect($back); }
    $row = [
        'titulo' => $titulo, 'slug' => unique_slug('anuncios', trim((string)($_POST['slug'] ?? '')) ?: $titulo, $id), 'descricao' => $descricao,
        'categoria_id' => (int)($_POST['categoria_id'] ?? 0) ?: null, 'tipo' => isset($tipos[$_POST['tipo'] ?? '']) ? $_POST['tipo'] : 'servico',
        'imagem_destaque' => $capa ?: null, 'logo' => $logo ?: null, 'whatsapp' => preg_replace('/\D+/', '', (string)($_POST['whatsapp'] ?? '')),
        'localizacao' => trim((string)($_POST['localizacao'] ?? '')), 'link' => trim((string)($_POST['link'] ?? '')),
        'avaliacao' => min(5, max(0, (float)str_replace(',', '.', (string)($_POST['avaliacao'] ?? 0)))), 'status' => $status,
    ] + $seo;
    $cols = array_keys($row);
    if ($id) $pdo->prepare('UPDATE anuncios SET ' . implode(',', array_map(fn($c) => "`$c`=?", $cols)) . ' WHERE id=?')->execute([...array_values($row), $id]);
    else { $pdo->prepare('INSERT INTO anuncios (' . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')')->execute(array_values($row)); $id = (int)$pdo->lastInsertId(); }
    if (can('publicar:anuncios')) {
        $pdo->prepare('DELETE FROM anuncio_destaques WHERE anuncio_id=?')->execute([$id]);
        if (isset($_POST['destaque'])) $pdo->prepare('INSERT INTO anuncio_destaques (anuncio_id,ordem,ativo) VALUES (?,?,1)')->execute([$id, max(0, (int)($_POST['destaque_ordem'] ?? 0))]);
    }
    revision_save('anuncios', $id);
    audit($old ? 'editar' : 'criar', 'anuncios', $id, ['titulo' => $titulo, 'status' => $status]);
    unset($_SESSION['form_anuncio']);
    flash('success', "Anúncio “{$titulo}” salvo." . $notice);
    redirect('gerenciar_anuncios.php');
}
if (isset($_GET['delete']) && verify_csrf($_GET['token'] ?? null)) {
    deny_unless('excluir:anuncios', 'gerenciar_anuncios.php');
    $a = db_one($pdo, 'SELECT id,titulo FROM anuncios WHERE id=?', [(int)$_GET['delete']]);
    if ($a) {
        $tid = trash_delete('anuncios', (int)$a['id'], $a['titulo'], 'anuncios', [['anuncio_destaques', 'anuncio_id'], ['blog_posts', 'anuncio_id']]);
        flash('success', "Anúncio “{$a['titulo']}” enviado para a lixeira.", undo_link($tid, 'gerenciar_anuncios.php'));
    }
    redirect('gerenciar_anuncios.php');
}
if (isset($_GET['status'], $_GET['id']) && verify_csrf($_GET['token'] ?? null) && in_array($_GET['status'], ['ativo', 'inativo'], true)) {
    deny_unless('publicar:anuncios', 'gerenciar_anuncios.php');
    $pdo->prepare('UPDATE anuncios SET status=? WHERE id=?')->execute([$_GET['status'], (int)$_GET['id']]);
    revision_save('anuncios', (int)$_GET['id'], $_GET['status'] === 'ativo' ? 'Publicado' : 'Despublicado');
    audit('editar', 'anuncios', (int)$_GET['id'], ['status' => $_GET['status']]);
    flash('success', $_GET['status'] === 'ativo' ? 'Anúncio publicado.' : 'Anúncio desativado (não aparece mais no site).');
    redirect('gerenciar_anuncios.php');
}
$cats = get_categories($pdo);
$term = trim((string)($_GET['q'] ?? ''));
$filter = (string)($_GET['filtro'] ?? '');
$sql = 'SELECT a.*, c.nome categoria, d.ordem destaque_ordem FROM anuncios a LEFT JOIN categorias c ON c.id=a.categoria_id LEFT JOIN anuncio_destaques d ON d.anuncio_id=a.id AND d.ativo=1 WHERE 1=1';
$params = [];
if ($term !== '') { $sql .= ' AND (a.titulo LIKE ? OR a.descricao LIKE ?)'; $params = ["%$term%", "%$term%"]; }
if (in_array($filter, ['ativo', 'inativo'], true)) { $sql .= ' AND a.status=?'; $params[] = $filter; }
$ads = db_rows($pdo, $sql . ' ORDER BY a.data_criacao DESC', $params);
$edit = isset($_GET['edit']) ? db_one($pdo, 'SELECT a.*, d.ordem destaque_ordem, d.ativo destaque_ativo FROM anuncios a LEFT JOIN anuncio_destaques d ON d.anuncio_id=a.id WHERE a.id=?', [(int)$_GET['edit']]) : null;
if (!empty($_SESSION['form_anuncio'])) { $edit = array_merge($edit ?? [], $_SESSION['form_anuncio'], ['destaque_ativo' => isset($_SESSION['form_anuncio']['destaque']) ? 1 : 0]); unset($_SESSION['form_anuncio']); }
$showForm = $edit || isset($_GET['novo']);
include __DIR__ . '/includes/header.php';
?>
<main class="admin-content">
  <div class="admin-heading">
    <div><h1>Anúncios</h1><p class="muted">Negócios exibidos no site. Os cards sobre o banner usam os anúncios em destaque.</p></div>
    <?php if (can('criar:anuncios')): ?><a class="btn btn-primary" href="?novo=1">+ Novo anúncio</a><?php endif; ?>
  </div>
  <?php if ($showForm): $eid = (int)($edit['id'] ?? 0); ?>
  <form method="post" action="gerenciar_anuncios.php" enctype="multipart/form-data" data-dirty-check>
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="id" value="<?= $eid ?>">
    <div class="split mb-4">
      <div class="admin-panel edit-panel<?= $eid ? ' is-editing' : '' ?>">
        <div class="edit-panel-head"><?php if ($eid): ?><span class="edit-badge">✎ Editando</span><h2><?= e($edit['titulo']) ?></h2><?php else: ?><h2>Novo anúncio</h2><?php endif; ?></div>
        <fieldset class="admin-form" style="padding:0;max-width:none" <?= ($eid ? can('editar:anuncios') : can('criar:anuncios')) ? '' : 'disabled' ?>>
          <label>Título<input name="titulo" required value="<?= e($edit['titulo'] ?? '') ?>"></label>
          <label>URL amigável (slug)<input name="slug" pattern="[a-z0-9-]*" value="<?= e($edit['slug'] ?? '') ?>" data-auto-slug><small class="muted">Perfil: <?= e(site_base_url() . ad_profile_url(['id' => $eid, 'slug' => $edit['slug'] ?? 'exemplo'])) ?></small></label>
          <div class="form-grid-2">
            <label>Categoria<select name="categoria_id" required><?php foreach ($cats as $c): ?><option value="<?= (int)$c['id'] ?>" <?= (int)($edit['categoria_id'] ?? 0) === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['nome']) ?></option><?php endforeach; ?></select></label>
            <label>Tipo <small class="muted">Filtro “Tipo” da busca</small><select name="tipo"><?php foreach ($tipos as $k => $l): ?><option value="<?= $k ?>" <?= ($edit['tipo'] ?? 'servico') === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></label>
          </div>
          <label>Descrição<textarea name="descricao" required><?= e($edit['descricao'] ?? '') ?></textarea></label>
          <div class="form-grid-2">
            <?= media_field('imagem_destaque', $edit['imagem_destaque'] ?? '', 'Imagem de capa', 'Redimensionada para 1400×700.') ?>
            <?= media_field('logo', $edit['logo'] ?? '', 'Logotipo / avatar', 'Quadrado. Aparece à esquerda nos cards sobre o banner.') ?>
          </div>
          <div class="form-grid-2">
            <label>WhatsApp<input name="whatsapp" inputmode="tel" value="<?= e($edit['whatsapp'] ?? '') ?>" placeholder="5585999999999"></label>
            <label>Localização<input name="localizacao" value="<?= e($edit['localizacao'] ?? 'Fortaleza - CE') ?>"></label>
          </div>
          <div class="form-grid-2">
            <label>Site do negócio (opcional)<input name="link" value="<?= e($edit['link'] ?? '') ?>" placeholder="https://..."></label>
            <label>Avaliação<input type="number" step="0.1" min="0" max="5" name="avaliacao" value="<?= e((string)($edit['avaliacao'] ?? 4.9)) ?>"></label>
          </div>
          <?= seo_fields($edit ?? []) ?>
        </fieldset>
      </div>
      <aside>
        <div class="side-card">
          <h3>Publicação</h3>
          <?php if (can('publicar:anuncios')): ?>
          <label class="small w-100">Status<select name="status" class="form-select form-select-sm"><option value="ativo" <?= ($edit['status'] ?? 'ativo') === 'ativo' ? 'selected' : '' ?>>Ativo (visível no site)</option><option value="inativo" <?= ($edit['status'] ?? '') === 'inativo' ? 'selected' : '' ?>>Inativo</option></select></label>
          <label class="check-row small mt-2"><input type="checkbox" name="destaque" value="1" <?= !empty($edit['destaque_ativo']) ? 'checked' : '' ?>> Exibir em “Negócios em destaque” e sobre o banner</label>
          <label class="small w-100">Ordem do destaque<input class="form-control form-control-sm" type="number" name="destaque_ordem" min="0" value="<?= (int)($edit['destaque_ordem'] ?? 0) ?>"></label>
          <?php else: ?>
          <p class="small">Status: <span class="status status-<?= e($edit['status'] ?? 'inativo') ?>"><?= ($edit['status'] ?? 'inativo') === 'ativo' ? 'Ativo' : 'Inativo' ?></span></p>
          <p class="small muted">Publicar, despublicar e destacar exigem a permissão “Publicar anúncios”.</p>
          <?php endif; ?>
          <button class="btn btn-primary w-100 mt-3">Salvar anúncio</button>
          <?php if ($eid): ?><hr><div class="d-grid gap-2">
            <a class="btn btn-sm btn-outline-secondary" href="../<?= e(ad_profile_url($edit)) ?>" target="_blank">👁 Ver perfil</a>
            <?php if (can('ler:versoes')): ?><a class="btn btn-sm btn-outline-secondary" href="versoes.php?tipo=anuncios&id=<?= $eid ?>">🕘 Histórico de versões</a><?php endif; ?>
            <?php if (can('excluir:anuncios')): ?><a class="btn btn-sm btn-outline-danger" href="?delete=<?= $eid ?>&token=<?= e(csrf_token()) ?>" data-confirm="Enviar “<?= e($edit['titulo']) ?>” para a lixeira? Você poderá desfazer.">Excluir</a><?php endif; ?>
          </div><?php endif; ?>
          <a class="btn btn-link btn-sm mt-2 px-0" href="gerenciar_anuncios.php">← Voltar à lista</a>
        </div>
      </aside>
    </div>
  </form>
  <?php endif; ?>
  <div class="admin-panel">
    <form class="admin-filter" method="get">
      <input name="q" value="<?= e($term) ?>" placeholder="Buscar anúncio">
      <select name="filtro"><option value="">Todos os status</option><option value="ativo" <?= $filter === 'ativo' ? 'selected' : '' ?>>Ativos</option><option value="inativo" <?= $filter === 'inativo' ? 'selected' : '' ?>>Inativos</option></select>
      <button class="btn btn-outline-secondary">Filtrar</button>
    </form>
    <div class="table-responsive">
      <table class="admin-table">
        <thead><tr><th>Negócio</th><th>Categoria</th><th>Tipo</th><th>Destaque</th><th>Status</th><th>Ações</th></tr></thead>
        <tbody>
        <?php foreach ($ads as $ad): $active = $edit && (int)($edit['id'] ?? 0) === (int)$ad['id']; ?>
          <tr class="<?= $active ? 'is-active-row' : '' ?>">
            <td><strong><?= e($ad['titulo']) ?></strong><br><small class="muted"><?= e(mb_strimwidth($ad['descricao'], 0, 65, '…')) ?></small></td>
            <td><?= e($ad['categoria'] ?? '-') ?></td>
            <td><?= e($tipos[$ad['tipo'] ?? 'servico'] ?? '-') ?></td>
            <td><?= $ad['destaque_ordem'] !== null ? '★ ' . (int)$ad['destaque_ordem'] : '—' ?></td>
            <td><span class="status status-<?= e($ad['status']) ?>"><?= $ad['status'] === 'ativo' ? 'Ativo' : 'Inativo' ?></span></td>
            <td class="actions">
              <a class="btn-action<?= $active ? ' is-active' : '' ?>" href="?edit=<?= (int)$ad['id'] ?>"><?= $active ? '✎ Editando' : 'Editar' ?></a>
              <?php if (can('publicar:anuncios')): ?><a class="btn-action" href="?id=<?= (int)$ad['id'] ?>&status=<?= $ad['status'] === 'ativo' ? 'inativo' : 'ativo' ?>&token=<?= e(csrf_token()) ?>"<?= $ad['status'] === 'ativo' ? ' data-confirm="Desativar “' . e($ad['titulo']) . '”? Ele deixará de aparecer no site." data-confirm-style="primary" data-confirm-button="Desativar"' : '' ?>><?= $ad['status'] === 'ativo' ? 'Desativar' : 'Publicar' ?></a><?php endif; ?>
              <?php if (can('excluir:anuncios')): ?><a class="btn-action danger" href="?delete=<?= (int)$ad['id'] ?>&token=<?= e(csrf_token()) ?>" data-confirm="Enviar “<?= e($ad['titulo']) ?>” para a lixeira? Você poderá desfazer.">Excluir</a><?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$ads): ?><tr><td colspan="6" class="muted">Nenhum anúncio encontrado.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
