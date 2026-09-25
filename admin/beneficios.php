<?php
$admin_title = 'Textos do banner';
include __DIR__ . '/includes/header.php';
$msg = '';
$erro = '';
$icons = benefit_icon_options();
$edit = isset($_GET['edit']) && $pdo ? db_one($pdo, 'SELECT * FROM banner_beneficios WHERE id=?', [(int)$_GET['edit']]) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo) {
    $id = (int)($_POST['id'] ?? 0);
    $titulo = trim($_POST['titulo'] ?? '');
    $subtitulo = trim($_POST['subtitulo'] ?? '');
    $icone = isset($icons[$_POST['icone'] ?? '']) ? $_POST['icone'] : 'megafone';
    $ordem = max(0, (int)($_POST['ordem'] ?? 0));
    $ativo = isset($_POST['ativo']) ? 1 : 0;
    if (!verify_csrf($_POST['csrf_token'] ?? null)) $erro = 'Token de segurança inválido.';
    elseif ($titulo === '') $erro = 'Informe o título do item.';
    else {
        if ($id) $pdo->prepare('UPDATE banner_beneficios SET icone=?,titulo=?,subtitulo=?,ordem=?,ativo=? WHERE id=?')->execute([$icone, $titulo, $subtitulo, $ordem, $ativo, $id]);
        else $pdo->prepare('INSERT INTO banner_beneficios(icone,titulo,subtitulo,ordem,ativo) VALUES(?,?,?,?,?)')->execute([$icone, $titulo, $subtitulo, $ordem, $ativo]);
        $msg = 'Item salvo com sucesso.';
        $edit = null;
    }
}
if (isset($_GET['delete']) && $pdo && verify_csrf($_GET['token'] ?? null)) {
    $pdo->prepare('DELETE FROM banner_beneficios WHERE id=?')->execute([(int)$_GET['delete']]);
    $msg = 'Item excluído.';
}
$items = get_benefits($pdo, false);
$editId = (int)($edit['id'] ?? 0);
?>
<main class="admin-content">
  <div class="admin-heading">
    <div><h1>Textos do banner</h1><p class="muted">Itens exibidos abaixo do texto do banner principal (ex.: “Visibilidade ao seu negócio”, “Ambiente seguro”, “Negócios verificados”). Recomendado: até 3 itens.</p></div>
  </div>
  <?php if ($msg): ?><div class="notice success"><?= e($msg) ?></div><?php endif; ?>
  <?php if ($erro): ?><div class="notice error"><?= e($erro) ?></div><?php endif; ?>
  <div class="admin-panel">
    <div class="edit-panel<?= $editId ? ' is-editing' : '' ?>">
      <div class="edit-panel-head"><?php if ($editId): ?><span class="edit-badge">✎ Editando</span><h2><?= e($edit['titulo']) ?></h2><a class="btn btn-sm btn-outline-secondary ms-auto" href="beneficios.php">Cancelar edição</a><?php else: ?><h2>Novo item</h2><?php endif; ?></div>
      <form class="admin-form" method="post" action="beneficios.php">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="id" value="<?= $editId ?>">
        <div class="form-grid-2">
          <label>Título (negrito)<input name="titulo" required maxlength="120" value="<?= e($edit['titulo'] ?? '') ?>" placeholder="Visibilidade ao seu negócio"></label>
          <label>Complemento<input name="subtitulo" maxlength="160" value="<?= e($edit['subtitulo'] ?? '') ?>" placeholder="e até novos clientes"></label>
        </div>
        <fieldset class="icon-picker">
          <legend>Ícone</legend>
          <?php foreach ($icons as $k => $label): ?>
          <label title="<?= e($label) ?>"><input type="radio" name="icone" value="<?= e($k) ?>" <?= ($edit['icone'] ?? 'megafone') === $k ? 'checked' : '' ?>><span><?= svg_icon($k, 'icon-pick') ?><small><?= e($label) ?></small></span></label>
          <?php endforeach; ?>
        </fieldset>
        <div class="form-grid-2">
          <label>Ordem<input type="number" name="ordem" min="0" value="<?= (int)($edit['ordem'] ?? (count($items) + 1)) ?>"></label>
          <label class="check-row align-self-end"><input type="checkbox" name="ativo" value="1" <?= ($edit['ativo'] ?? 1) ? 'checked' : '' ?>> Visível no banner</label>
        </div>
        <div><button class="btn btn-primary"><?= $editId ? 'Salvar alterações' : 'Adicionar item' ?></button></div>
      </form>
    </div>

    <h2 class="mt-4">Pré-visualização</h2>
    <div class="benefit-preview">
      <?php foreach ($items as $b): if (!$b['ativo']) continue; ?>
      <div><span class="bp-icon"><?= svg_icon($b['icone'], 'icon-pick') ?></span><span><b><?= e($b['titulo']) ?></b><small><?= e($b['subtitulo']) ?></small></span></div>
      <?php endforeach; ?>
    </div>

    <div class="table-responsive mt-4">
      <table class="admin-table">
        <thead><tr><th>Ordem</th><th>Ícone</th><th>Título</th><th>Complemento</th><th>Status</th><th>Ações</th></tr></thead>
        <tbody>
        <?php foreach ($items as $b): $active = (int)$b['id'] === $editId; ?>
          <tr class="<?= $active ? 'is-active-row' : '' ?>">
            <td><?= (int)$b['ordem'] ?></td>
            <td class="text-center" style="color:#9f1549"><?= svg_icon($b['icone'], 'icon-pick') ?></td>
            <td><strong><?= e($b['titulo']) ?></strong></td>
            <td><?= e($b['subtitulo']) ?></td>
            <td><span class="status status-<?= $b['ativo'] ? 'ativo' : 'inativo' ?>"><?= $b['ativo'] ? 'Visível' : 'Oculto' ?></span></td>
            <td class="actions"><a class="btn-action<?= $active ? ' is-active' : '' ?>" href="?edit=<?= (int)$b['id'] ?>"><?= $active ? '✎ Editando' : 'Editar' ?></a> <a class="btn-action danger" href="?delete=<?= (int)$b['id'] ?>&token=<?= e(csrf_token()) ?>" onclick="return confirm('Excluir este item?')">Excluir</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
