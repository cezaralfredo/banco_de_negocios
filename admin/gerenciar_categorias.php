<?php
$admin_title = 'Gerenciar Categorias';
include __DIR__ . '/includes/header.php';
$msg = '';
$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo) {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $erro = 'Token de segurança inválido.';
    } else {
        $id = (int)($_POST['id'] ?? 0);
        $nome = trim($_POST['nome'] ?? '');
        $icone = trim($_POST['icone'] ?? '') ?: '✦';
        $destaque = isset($_POST['destaque']) ? 1 : 0;
        $slug = slugify(trim($_POST['slug'] ?? '') ?: $nome);
        if ($nome === '') {
            $erro = 'Informe o nome da categoria.';
        } else {
            try {
                if ($id) {
                    $pdo->prepare('UPDATE categorias SET nome=?,icone=?,slug=?,destaque=? WHERE id=?')->execute([$nome, $icone, $slug, $destaque, $id]);
                } else {
                    $pdo->prepare('INSERT INTO categorias(nome,icone,slug,destaque) VALUES(?,?,?,?)')->execute([$nome, $icone, $slug, $destaque]);
                }
                $msg = $id ? 'Categoria atualizada com sucesso.' : 'Categoria criada com sucesso.';
            } catch (Throwable $e) {
                $erro = str_contains($e->getMessage(), 'Duplicate') ? 'Já existe uma categoria com este slug.' : 'Não foi possível salvar a categoria.';
            }
        }
    }
}
if (isset($_GET['delete']) && $pdo && verify_csrf($_GET['token'] ?? null)) {
    $pdo->prepare('DELETE FROM categorias WHERE id=?')->execute([(int)$_GET['delete']]);
    $msg = 'Categoria excluída com sucesso.';
}
$cats = $pdo ? db_rows($pdo, 'SELECT c.*, (SELECT COUNT(*) FROM anuncios a WHERE a.categoria_id=c.id) total FROM categorias c ORDER BY nome') : [];
$edit = (!$msg && isset($_GET['edit']) && $pdo) ? db_one($pdo, 'SELECT * FROM categorias WHERE id=?', [(int)$_GET['edit']]) : null;
if ($erro && $_SERVER['REQUEST_METHOD'] === 'POST') $edit = ['id' => (int)$_POST['id'], 'nome' => $_POST['nome'] ?? '', 'icone' => $_POST['icone'] ?? '', 'slug' => $_POST['slug'] ?? '', 'destaque' => isset($_POST['destaque']) ? 1 : 0];
$editId = (int)($edit['id'] ?? 0);
?>
<main class="admin-content">
  <h1>Gerenciar Categorias</h1>
  <p class="muted">Novas categorias ficam disponíveis em “Todas as categorias”; marque “Exibir em Categorias em destaque” para mostrá-las na home.</p>
  <?php if ($msg): ?><div class="notice success"><?= e($msg) ?></div><?php endif; ?>
  <?php if ($erro): ?><div class="notice error"><?= e($erro) ?></div><?php endif; ?>

  <div class="admin-panel">
    <div class="edit-panel<?= $editId ? ' is-editing' : '' ?>">
      <div class="edit-panel-head">
        <?php if ($editId): ?>
          <span class="edit-badge">✎ Editando</span>
          <h2>Editar categoria: <strong><?= e($edit['nome']) ?></strong></h2>
          <a class="btn btn-sm btn-outline-secondary ms-auto" href="gerenciar_categorias.php">Cancelar edição</a>
        <?php else: ?>
          <h2>Nova categoria</h2>
        <?php endif; ?>
      </div>
      <form class="admin-form" method="post" action="gerenciar_categorias.php">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="id" value="<?= $editId ?>">
        <div class="form-grid-2">
          <label>Nome<input name="nome" required value="<?= e($edit['nome'] ?? '') ?>"></label>
          <label>Ícone <small class="muted">Emoji ou símbolo</small><input name="icone" maxlength="10" value="<?= e($edit['icone'] ?? '✦') ?>"></label>
        </div>
        <label>Slug <small class="muted">Deixe vazio para gerar a partir do nome.</small><input name="slug" pattern="[a-z0-9-]*" value="<?= e($edit['slug'] ?? '') ?>"></label>
        <label class="check-row"><input type="checkbox" name="destaque" value="1" <?= !empty($edit['destaque']) ? 'checked' : '' ?>> Exibir em Categorias em destaque</label>
        <div><button class="btn btn-primary"><?= $editId ? 'Salvar alterações' : 'Criar categoria' ?></button></div>
      </form>
    </div>

    <div class="table-responsive mt-4">
      <table class="admin-table">
        <thead><tr><th>Ícone</th><th>Nome</th><th>Slug</th><th>Destaque</th><th>Anúncios</th><th>Ações</th></tr></thead>
        <tbody>
        <?php foreach ($cats as $c): $active = (int)$c['id'] === $editId; ?>
          <tr class="<?= $active ? 'is-active-row' : '' ?>"<?= $active ? ' aria-current="true"' : '' ?>>
            <td class="fs-5"><?= e($c['icone']) ?></td>
            <td><strong><?= e($c['nome']) ?></strong><?php if ($active): ?> <span class="edit-badge sm">Editando</span><?php endif; ?></td>
            <td><?= e($c['slug'] ?? '') ?></td>
            <td><?= !empty($c['destaque']) ? '<span class="status status-ativo">Sim</span>' : '<span class="status status-inativo">Não</span>' ?></td>
            <td><?= (int)$c['total'] ?></td>
            <td class="actions">
              <a class="btn-action<?= $active ? ' is-active' : '' ?>" href="?edit=<?= (int)$c['id'] ?>"><?= $active ? '✎ Editando' : 'Editar' ?></a>
              <a class="btn-action danger" href="?delete=<?= (int)$c['id'] ?>&token=<?= e(csrf_token()) ?>" onclick="return confirm('Excluir esta categoria? Os anúncios ficarão sem categoria.')">Excluir</a>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
