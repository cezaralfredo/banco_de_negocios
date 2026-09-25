<?php
$admin_title = 'Notícias';
include __DIR__ . '/includes/header.php';
$msg = '';
$erro = '';
$edit = isset($_GET['edit']) && $pdo ? db_one($pdo, 'SELECT * FROM noticias WHERE id=?', [(int)$_GET['edit']]) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo) {
    $id = (int)($_POST['id'] ?? 0);
    $old = $id ? db_one($pdo, 'SELECT * FROM noticias WHERE id=?', [$id]) : null;
    $titulo = trim($_POST['titulo'] ?? '');
    $slug = slugify(trim($_POST['slug'] ?? '') ?: $titulo);
    $resumo = mb_substr(trim($_POST['resumo'] ?? ''), 0, 400);
    $conteudo = trim($_POST['conteudo'] ?? '');
    $autor = trim($_POST['autor'] ?? '');
    $destaque = isset($_POST['destaque']) ? 1 : 0;
    $status = isset($_POST['status']) ? 1 : 0;
    $data = trim($_POST['publicado_em'] ?? '');
    $publicado = $data !== '' && strtotime($data) ? date('Y-m-d H:i:s', strtotime($data)) : date('Y-m-d H:i:s');
    $imagem = $old['imagem'] ?? '';
    if (!verify_csrf($_POST['csrf_token'] ?? null)) $erro = 'Token de segurança inválido.';
    elseif ($titulo === '' || $conteudo === '') $erro = 'Informe o título e o conteúdo da notícia.';
    elseif (db_one($pdo, 'SELECT id FROM noticias WHERE slug=? AND id<>?', [$slug, $id])) $erro = 'Já existe uma notícia com este endereço (slug). Altere o slug.';
    else {
        try {
            $imagem = upload_image_file($_FILES['imagem'] ?? [], 'noticia', 1600, 900) ?? $imagem;
            if (isset($_POST['remover_imagem'])) $imagem = '';
        } catch (Throwable $ex) {
            $erro = $ex->getMessage();
        }
    }
    if (!$erro) {
        if ($id) {
            $pdo->prepare('UPDATE noticias SET titulo=?,slug=?,resumo=?,conteudo=?,imagem=?,autor=?,destaque=?,status=?,publicado_em=? WHERE id=?')->execute([$titulo, $slug, $resumo, $conteudo, $imagem, $autor, $destaque, $status, $publicado, $id]);
        } else {
            $pdo->prepare('INSERT INTO noticias(titulo,slug,resumo,conteudo,imagem,autor,destaque,status,publicado_em) VALUES(?,?,?,?,?,?,?,?,?)')->execute([$titulo, $slug, $resumo, $conteudo, $imagem, $autor, $destaque, $status, $publicado]);
        }
        $msg = 'Notícia salva com sucesso.';
        $edit = null;
        unset($_GET['novo']);
    } else {
        $edit = ['id' => $id, 'titulo' => $titulo, 'slug' => $_POST['slug'] ?? '', 'resumo' => $resumo, 'conteudo' => $conteudo, 'imagem' => $imagem, 'autor' => $autor, 'destaque' => $destaque, 'status' => $status, 'publicado_em' => $publicado];
    }
}
if (isset($_GET['delete']) && $pdo && verify_csrf($_GET['token'] ?? null)) {
    $pdo->prepare('DELETE FROM noticias WHERE id=?')->execute([(int)$_GET['delete']]);
    $msg = 'Notícia excluída.';
}
$news = $pdo ? db_rows($pdo, 'SELECT * FROM noticias ORDER BY publicado_em DESC, id DESC') : [];
$showForm = $edit || isset($_GET['novo']);
?>
<main class="admin-content">
  <div class="admin-heading">
    <div><h1>Notícias</h1><p class="muted">Conteúdo exibido na página “Notícias” do menu superior e em “Últimas notícias” na home.</p></div>
    <div class="d-flex gap-2"><a class="btn btn-outline-secondary" href="../noticias.php" target="_blank">Ver página ↗</a><a class="btn btn-primary" href="?novo=1">+ Nova notícia</a></div>
  </div>
  <?php if ($msg): ?><div class="notice success"><?= e($msg) ?></div><?php endif; ?>
  <?php if ($erro): ?><div class="notice error"><?= e($erro) ?></div><?php endif; ?>
  <div class="admin-panel">
  <?php if ($showForm): $editId = (int)($edit['id'] ?? 0); ?>
    <div class="edit-panel<?= $editId ? ' is-editing' : '' ?>">
      <div class="edit-panel-head"><?php if ($editId): ?><span class="edit-badge">✎ Editando</span><h2><?= e($edit['titulo']) ?></h2><?php else: ?><h2>Nova notícia</h2><?php endif; ?></div>
      <form class="admin-form page-editor" method="post" action="gerenciar_noticias.php" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="id" value="<?= $editId ?>">
        <label>Título<input name="titulo" required maxlength="200" value="<?= e($edit['titulo'] ?? '') ?>"></label>
        <div class="form-grid-2">
          <label>Slug (endereço) <small class="muted">Vazio = gerado pelo título</small><input name="slug" pattern="[a-z0-9-]*" value="<?= e($edit['slug'] ?? '') ?>"></label>
          <label>Data de publicação<input type="datetime-local" name="publicado_em" value="<?= e(date('Y-m-d\TH:i', strtotime($edit['publicado_em'] ?? 'now'))) ?>"><small class="muted">Datas futuras ficam agendadas.</small></label>
        </div>
        <label>Resumo <small class="muted">Aparece na listagem e no topo da notícia (até 400 caracteres).</small><textarea name="resumo" maxlength="400" style="min-height:80px"><?= e($edit['resumo'] ?? '') ?></textarea></label>
        <div class="editor-toolbar" data-editor="#news-content-editor">
          <button type="button" data-editor-tag="<p>|</p>">Parágrafo</button>
          <button type="button" data-editor-tag="<strong>|</strong>">Negrito</button>
          <button type="button" data-editor-tag="<h2>|</h2>">Título</button>
          <button type="button" data-editor-tag="<ul><li>|</li></ul>">Lista</button>
          <button type="button" data-editor-tag='<a href="https://">|</a>'>Link</button>
          <button type="button" data-editor-image>Inserir imagem</button>
        </div>
        <label>Conteúdo<textarea id="news-content-editor" name="conteudo" required><?= e($edit['conteudo'] ?? '') ?></textarea><small class="muted">HTML básico (parágrafos, títulos, listas, links e imagens).</small></label>
        <div class="form-grid-2">
          <label>Imagem de capa<input type="file" name="imagem" accept="image/jpeg,image/png,image/webp"><small class="muted">JPG, PNG ou WebP · até 5 MB.</small></label>
          <label>Autor<input name="autor" value="<?= e($edit['autor'] ?? ($_SESSION['admin_nome'] ?? '')) ?>"></label>
        </div>
        <?php if (!empty($edit['imagem'])): ?><div class="upload-previews"><figure><img src="../<?= e($edit['imagem']) ?>" alt="Capa atual"><figcaption><label class="check-row"><input type="checkbox" name="remover_imagem" value="1"> remover</label></figcaption></figure></div><?php endif; ?>
        <label class="check-row"><input type="checkbox" name="destaque" value="1" <?= !empty($edit['destaque']) ? 'checked' : '' ?>> Fixar no topo (destaque)</label>
        <label class="check-row"><input type="checkbox" name="status" value="1" <?= ($edit['status'] ?? 1) ? 'checked' : '' ?>> Publicada</label>
        <div><button class="btn btn-primary">Salvar notícia</button> <a class="btn btn-outline-secondary" href="gerenciar_noticias.php">Cancelar</a></div>
      </form>
    </div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="admin-table">
        <thead><tr><th>Data</th><th>Título</th><th>Destaque</th><th>Status</th><th>Ações</th></tr></thead>
        <tbody>
        <?php if (!$news): ?><tr><td colspan="5" class="muted">Nenhuma notícia cadastrada.</td></tr><?php endif; ?>
        <?php foreach ($news as $n): $scheduled = strtotime($n['publicado_em']) > time(); ?>
          <tr>
            <td><?= e(date('d/m/Y H:i', strtotime($n['publicado_em']))) ?></td>
            <td><strong><?= e($n['titulo']) ?></strong><br><small class="muted">noticia.php?slug=<?= e($n['slug']) ?></small></td>
            <td><?= $n['destaque'] ? '★' : '—' ?></td>
            <td><span class="status status-<?= $n['status'] && !$scheduled ? 'ativo' : 'inativo' ?>"><?= !$n['status'] ? 'Rascunho' : ($scheduled ? 'Agendada' : 'Publicada') ?></span></td>
            <td class="actions"><a class="btn-action" href="?edit=<?= (int)$n['id'] ?>">Editar</a> <a class="btn-action" href="../noticia.php?slug=<?= urlencode($n['slug']) ?>" target="_blank">Ver</a> <a class="btn-action danger" href="?delete=<?= (int)$n['id'] ?>&token=<?= e(csrf_token()) ?>" onclick="return confirm('Excluir esta notícia?')">Excluir</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
  </div>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
