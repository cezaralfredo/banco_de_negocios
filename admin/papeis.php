<?php
$admin_title = 'Papéis e permissões';
require __DIR__ . '/includes/init.php';
$catalog = permission_catalog();
$isAdminUser = in_array('*', $me['permissoes'], true);
$myPerms = $isAdminUser ? array_keys($catalog) : $me['permissoes'];
$groups = [];
foreach ($catalog as $k => [$g, $d]) $groups[$g][$k] = $d;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) { flash('error', 'Sessão expirada. Tente novamente.'); redirect('papeis.php'); }
    $id = (int)($_POST['id'] ?? 0);
    $nome = trim((string)($_POST['nome'] ?? ''));
    $desc = trim((string)($_POST['descricao'] ?? ''));
    $perms = array_values(array_intersect(array_keys($catalog), (array)($_POST['perm'] ?? [])));
    $role = $id ? db_one($pdo, 'SELECT * FROM papeis WHERE id=?', [$id]) : null;
    if ($role && $role['slug'] === 'administrador') { flash('error', 'O papel Administrador possui todas as permissões e não pode ser alterado.'); redirect('papeis.php'); }
    if ($nome === '') { flash('error', 'Informe o nome do papel.'); redirect('papeis.php' . ($id ? '?edit=' . $id : '?novo=1')); }
    if (!in_array('acessar:painel', $perms, true) && $perms) $perms[] = 'acessar:painel';
    // Menor privilégio: ninguém concede permissões que não possui
    if ($extra = array_diff($perms, $myPerms)) { flash('error', 'Você não pode conceder permissões que não possui: ' . implode(', ', $extra) . '.'); redirect('papeis.php' . ($id ? '?edit=' . $id : '?novo=1')); }
    if ($id && !$isAdminUser && (int)$id === (int)($me['papel_id'] ?? 0)) { flash('error', 'Você não pode alterar as permissões do seu próprio papel.'); redirect('papeis.php?edit=' . $id); }
    if ($id) {
        $before = array_column(db_rows($pdo, 'SELECT permissao FROM papel_permissoes WHERE papel_id=?', [$id]), 'permissao');
        $pdo->prepare('UPDATE papeis SET nome=?, descricao=? WHERE id=?')->execute([$nome, $desc, $id]);
    } else {
        $slug = unique_slug('papeis', $nome);
        $pdo->prepare('INSERT INTO papeis (slug,nome,descricao) VALUES (?,?,?)')->execute([$slug, $nome, $desc]);
        $id = (int)$pdo->lastInsertId();
        $before = [];
    }
    $pdo->prepare('DELETE FROM papel_permissoes WHERE papel_id=?')->execute([$id]);
    $ins = $pdo->prepare('INSERT INTO papel_permissoes (papel_id,permissao) VALUES (?,?)');
    foreach ($perms as $p) $ins->execute([$id, $p]);
    audit('permissoes', 'papel', $id, ['papel' => $nome, 'adicionadas' => array_values(array_diff($perms, $before)), 'removidas' => array_values(array_diff($before, $perms))]);
    flash('success', "Papel “{$nome}” salvo com " . count($perms) . ' permissões.');
    redirect('papeis.php?edit=' . $id);
}
if (isset($_GET['delete']) && verify_csrf($_GET['token'] ?? null)) {
    $r = db_one($pdo, 'SELECT p.*, (SELECT COUNT(*) FROM usuarios u WHERE u.papel_id=p.id) n FROM papeis p WHERE p.id=?', [(int)$_GET['delete']]);
    if (!$r || $r['sistema']) flash('error', 'Este papel não pode ser excluído.');
    elseif ($r['n'] > 0) flash('error', "Há {$r['n']} usuário(s) com o papel “{$r['nome']}”. Troque o papel deles antes de excluir.");
    else {
        $tid = trash_delete('papeis', (int)$r['id'], $r['nome'], 'papeis', [['papel_permissoes', 'papel_id']]);
        flash('success', "Papel “{$r['nome']}” excluído.", undo_link($tid, 'papeis.php'));
    }
    redirect('papeis.php');
}
if (isset($_GET['duplicar']) && verify_csrf($_GET['token'] ?? null)) {
    $r = db_one($pdo, 'SELECT * FROM papeis WHERE id=?', [(int)$_GET['duplicar']]);
    if ($r) {
        $nome = $r['nome'] . ' (cópia)';
        $pdo->prepare('INSERT INTO papeis (slug,nome,descricao) VALUES (?,?,?)')->execute([unique_slug('papeis', $nome), $nome, $r['descricao']]);
        $nid = (int)$pdo->lastInsertId();
        $perms = $r['slug'] === 'administrador' ? array_keys($catalog) : array_column(db_rows($pdo, 'SELECT permissao FROM papel_permissoes WHERE papel_id=?', [$r['id']]), 'permissao');
        if (array_diff($perms, $myPerms)) { flash('error', 'Você não pode duplicar um papel com permissões que não possui.'); redirect('papeis.php'); }
        foreach ($perms as $p) $pdo->prepare('INSERT IGNORE INTO papel_permissoes VALUES (?,?)')->execute([$nid, $p]);
        audit('permissoes', 'papel', $nid, ['duplicado_de' => $r['nome']]);
        flash('success', 'Papel duplicado. Ajuste o nome e as permissões.');
        redirect('papeis.php?edit=' . $nid);
    }
}
$roles = roles_all();
$edit = isset($_GET['edit']) ? db_one($pdo, 'SELECT * FROM papeis WHERE id=?', [(int)$_GET['edit']]) : null;
$editPerms = $edit ? ($edit['slug'] === 'administrador' ? array_keys($catalog) : array_column(db_rows($pdo, 'SELECT permissao FROM papel_permissoes WHERE papel_id=?', [$edit['id']]), 'permissao')) : [];
$showForm = $edit || isset($_GET['novo']);
include __DIR__ . '/includes/header.php';
?>
<main class="admin-content">
  <div class="admin-heading">
    <div><h1>Papéis e permissões</h1><p class="muted">Defina o que cada perfil pode fazer. Siga o princípio do menor privilégio: dê apenas as permissões necessárias.</p></div>
    <a class="btn btn-primary" href="?novo=1">+ Novo papel</a>
  </div>
  <div class="admin-panel mb-4">
    <div class="table-responsive">
      <table class="admin-table">
        <thead><tr><th>Papel</th><th>Descrição</th><th>Permissões</th><th>Usuários</th><th>Ações</th></tr></thead>
        <tbody>
        <?php foreach ($roles as $r): $n = $r['slug'] === 'administrador' ? count($catalog) : (int)(db_one($pdo, 'SELECT COUNT(*) n FROM papel_permissoes WHERE papel_id=?', [$r['id']])['n'] ?? 0); $active = $edit && (int)$edit['id'] === (int)$r['id']; ?>
          <tr class="<?= $active ? 'is-active-row' : '' ?>">
            <td><strong><?= e($r['nome']) ?></strong><?= $r['sistema'] ? ' <span class="badge-soft">sistema</span>' : '' ?></td>
            <td style="white-space:normal;max-width:380px"><?= e($r['descricao']) ?></td>
            <td><?= $n ?> / <?= count($catalog) ?></td>
            <td><a href="gerenciar_usuarios.php?papel=<?= (int)$r['id'] ?>"><?= (int)$r['usuarios'] ?></a></td>
            <td class="actions">
              <a class="btn-action<?= $active ? ' is-active' : '' ?>" href="?edit=<?= (int)$r['id'] ?>"><?= $r['slug'] === 'administrador' ? 'Ver' : ($active ? '✎ Editando' : 'Editar') ?></a>
              <a class="btn-action" href="?duplicar=<?= (int)$r['id'] ?>&token=<?= e(csrf_token()) ?>">Duplicar</a>
              <?php if (!$r['sistema']): ?><a class="btn-action danger" href="?delete=<?= (int)$r['id'] ?>&token=<?= e(csrf_token()) ?>" data-confirm="Excluir o papel “<?= e($r['nome']) ?>”? Você poderá desfazer em seguida.">Excluir</a><?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php if ($showForm): $locked = $edit && $edit['slug'] === 'administrador'; ?>
  <div class="admin-panel edit-panel<?= $edit ? ' is-editing' : '' ?>">
    <div class="edit-panel-head"><?php if ($edit): ?><span class="edit-badge">✎ Editando</span><h2><?= e($edit['nome']) ?></h2><?php else: ?><h2>Novo papel</h2><?php endif; ?><a class="btn btn-sm btn-outline-secondary ms-auto" href="papeis.php">Fechar</a></div>
    <?php if ($locked): ?><p class="notice success">O Administrador sempre possui todas as permissões. Para criar uma variação, use “Duplicar”.</p><?php endif; ?>
    <form method="post" data-dirty-check>
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
      <div class="admin-form mb-3" style="max-width:none">
        <div class="form-grid-2">
          <label>Nome do papel<input name="nome" required maxlength="80" value="<?= e($edit['nome'] ?? '') ?>" <?= $locked ? 'disabled' : '' ?>></label>
          <label>Descrição<input name="descricao" maxlength="255" value="<?= e($edit['descricao'] ?? '') ?>" <?= $locked ? 'disabled' : '' ?>></label>
        </div>
      </div>
      <p class="d-flex gap-2 flex-wrap"><button type="button" class="btn btn-sm btn-outline-secondary" onclick="document.querySelectorAll('.perm-group input').forEach(i=>i.checked=true)" <?= $locked ? 'disabled' : '' ?>>Marcar todas</button><button type="button" class="btn btn-sm btn-outline-secondary" onclick="document.querySelectorAll('.perm-group input').forEach(i=>i.checked=false)" <?= $locked ? 'disabled' : '' ?>>Desmarcar todas</button><button type="button" class="btn btn-sm btn-outline-secondary" onclick="document.querySelectorAll('.perm-group input').forEach(i=>i.checked=i.value.startsWith('ler:')||i.value==='acessar:painel')" <?= $locked ? 'disabled' : '' ?>>Somente leitura</button></p>
      <div class="perm-grid">
        <?php foreach ($groups as $g => $perms): ?>
        <fieldset class="perm-group">
          <h3><?= e($g) ?> <button type="button" class="btn btn-link btn-sm p-0" onclick="const b=this.closest('fieldset').querySelectorAll('input');const all=[...b].every(i=>i.checked);b.forEach(i=>i.checked=!all)" <?= $locked ? 'disabled' : '' ?>>alternar</button></h3>
          <?php foreach ($perms as $k => $d): ?>
          <label><input type="checkbox" name="perm[]" value="<?= e($k) ?>" <?= in_array($k, $editPerms, true) ? 'checked' : '' ?> <?= $locked || !in_array($k, $myPerms, true) ? 'disabled' : '' ?>><span><?= e($d) ?><br><code><?= e($k) ?></code></span></label>
          <?php endforeach; ?>
        </fieldset>
        <?php endforeach; ?>
      </div>
      <?php if (!$locked): ?><div class="sticky-save"><button class="btn btn-primary">Salvar papel</button> <span class="muted">A permissão “Acessar o painel” é incluída automaticamente.</span></div><?php endif; ?>
    </form>
  </div>
  <?php endif; ?>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
