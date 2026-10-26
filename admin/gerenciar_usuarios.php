<?php
$admin_title = 'Usuários';
require __DIR__ . '/includes/init.php';
$roles = roles_all();
$roleById = array_column($roles, null, 'id');
$isAdmin = ($me['papel_slug'] ?? '') === 'administrador';
$adminRole = db_one($pdo, "SELECT id FROM papeis WHERE slug='administrador'");
$myPerms = $isAdmin ? ['*'] : $me['permissoes'];
/** Papel pode ser atribuído por este usuário? (não pode conceder mais do que possui) */
$canAssign = function (?int $roleId) use ($pdo, $isAdmin, $myPerms, $roleById): bool {
    if (!$roleId) return true;
    if ($isAdmin) return true;
    if (($roleById[$roleId]['slug'] ?? '') === 'administrador') return false;
    $rp = array_column(db_rows($pdo, 'SELECT permissao FROM papel_permissoes WHERE papel_id=?', [$roleId]), 'permissao');
    return !array_diff($rp, $myPerms);
};
$countAdmins = fn() => (int)(db_one($pdo, "SELECT COUNT(*) n FROM usuarios WHERE status='ativo' AND papel_id=?", [(int)($adminRole['id'] ?? 0)])['n'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) { flash('error', 'Sessão expirada.'); redirect('gerenciar_usuarios.php'); }
    $id = (int)($_POST['id'] ?? 0);
    $old = $id ? db_one($pdo, 'SELECT * FROM usuarios WHERE id=?', [$id]) : null;
    $nome = trim((string)($_POST['nome'] ?? ''));
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $papel = (int)($_POST['papel_id'] ?? 0) ?: null;
    $status = ($_POST['status'] ?? 'ativo') === 'inativo' ? 'inativo' : 'ativo';
    $senha = (string)($_POST['senha'] ?? '');
    $back = 'gerenciar_usuarios.php?' . ($id ? 'edit=' . $id : 'novo=1');
    $err = null;
    if ($nome === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $err = 'Informe nome e e-mail válidos.';
    elseif (db_one($pdo, 'SELECT id FROM usuarios WHERE email=? AND id<>?', [$email, $id])) $err = 'Este e-mail já está em uso.';
    elseif (!$id && ($p = password_problem($senha))) $err = $p;
    elseif ($id && $senha !== '' && ($p = password_problem($senha))) $err = $p;
    elseif ($papel && !isset($roleById[$papel])) $err = 'Papel inválido.';
    elseif ($papel && $roleById[$papel]['slug'] === 'administrador' && !$isAdmin) $err = 'Somente administradores podem conceder o papel Administrador.';
    elseif (!$canAssign($papel)) $err = 'Você não pode atribuir um papel com permissões que você não possui.';
    elseif ($old && !$isAdmin && ((int)$old['papel_id'] === (int)($adminRole['id'] ?? -1) || $old['nivel_acesso'] === 'admin')) $err = 'Somente administradores podem alterar contas de administradores.';
    elseif ($old && !$isAdmin && !$canAssign((int)$old['papel_id'] ?: null)) $err = 'Você não pode alterar contas com mais permissões do que as suas.';
    elseif ($old && (int)$old['id'] === (int)$me['id'] && ($status !== 'ativo' || $papel !== (int)$old['papel_id'])) $err = 'Você não pode alterar o próprio papel ou desativar a própria conta.';
    elseif ($old && (int)$old['papel_id'] === (int)($adminRole['id'] ?? -1) && ($papel !== (int)$old['papel_id'] || $status !== 'ativo') && $countAdmins() <= 1) $err = 'Este é o único administrador ativo. Defina outro administrador antes.';
    if ($err) { flash('error', $err); $_SESSION['form_usuario'] = $_POST; redirect($back); }
    $nivel = $papel && $roleById[$papel]['slug'] === 'administrador' ? 'admin' : 'user';
    if ($id) {
        $sql = 'UPDATE usuarios SET nome=?,email=?,papel_id=?,nivel_acesso=?,status=?' . ($senha !== '' ? ',senha=?' : '') . ' WHERE id=?';
        $params = [$nome, $email, $papel, $nivel, $status];
        if ($senha !== '') $params[] = password_hash($senha, PASSWORD_DEFAULT);
        $params[] = $id;
        $pdo->prepare($sql)->execute($params);
        audit('editar', 'usuarios', $id, ['email' => $email, 'senha_alterada' => $senha !== '']);
        if ((int)$old['papel_id'] !== (int)$papel) audit('papel_usuario', 'usuarios', $id, ['email' => $email, 'de' => $roleById[$old['papel_id']]['nome'] ?? 'sem papel', 'para' => $roleById[$papel]['nome'] ?? 'sem papel']);
        if ($old['status'] !== $status) audit('editar', 'usuarios', $id, ['status' => $status]);
    } else {
        $pdo->prepare('INSERT INTO usuarios(nome,email,senha,nivel_acesso,status,papel_id) VALUES(?,?,?,?,?,?)')->execute([$nome, $email, password_hash($senha, PASSWORD_DEFAULT), $nivel, $status, $papel]);
        $id = (int)$pdo->lastInsertId();
        audit('criar', 'usuarios', $id, ['email' => $email, 'papel' => $roleById[$papel]['nome'] ?? 'sem papel']);
        if (!empty($_POST['notificar'])) {
            send_mail($email, 'Seu acesso ao painel — ' . setting($pdo, 'site_name', 'CMS'), mail_template('Bem-vindo(a)!', '<p>Olá, ' . e($nome) . '. Uma conta foi criada para você no painel.</p><p>E-mail de acesso: <strong>' . e($email) . '</strong><br>Senha: a informada pelo administrador (você pode alterá-la em “Minha conta”).</p>', site_base_url() . 'admin/', 'Acessar o painel'));
        }
    }
    unset($_SESSION['form_usuario']);
    flash('success', 'Usuário salvo com sucesso.');
    redirect('gerenciar_usuarios.php');
}
if (isset($_GET['ativar']) && verify_csrf($_GET['token'] ?? null)) {
    $u = db_one($pdo, 'SELECT * FROM usuarios WHERE id=?', [(int)$_GET['ativar']]);
    if ($u) {
        $pdo->prepare("UPDATE usuarios SET status='ativo' WHERE id=?")->execute([$u['id']]);
        audit('editar', 'usuarios', $u['id'], ['status' => 'ativo', 'email' => $u['email']]);
        flash('success', "Conta de {$u['nome']} ativada.");
    }
    redirect('gerenciar_usuarios.php');
}
if (isset($_GET['delete']) && verify_csrf($_GET['token'] ?? null)) {
    $u = db_one($pdo, 'SELECT * FROM usuarios WHERE id=?', [(int)$_GET['delete']]);
    if (!$u) redirect('gerenciar_usuarios.php');
    if ((int)$u['id'] === (int)$me['id']) flash('error', 'Você não pode excluir a própria conta.');
    elseif (!$isAdmin && ((int)$u['papel_id'] === (int)($adminRole['id'] ?? -1) || $u['nivel_acesso'] === 'admin' || !$canAssign((int)$u['papel_id'] ?: null))) flash('error', 'Você não pode excluir contas com mais permissões do que as suas.');
    elseif ((int)$u['papel_id'] === (int)($adminRole['id'] ?? -1) && $countAdmins() <= 1) flash('error', 'Não é possível excluir o único administrador.');
    else {
        $tid = trash_delete('usuarios', (int)$u['id'], $u['nome'] . ' <' . $u['email'] . '>', 'usuarios');
        flash('success', "Usuário {$u['nome']} excluído.", undo_link($tid, 'gerenciar_usuarios.php'));
    }
    redirect('gerenciar_usuarios.php');
}

$f = ['q' => trim((string)($_GET['q'] ?? '')), 'papel' => (int)($_GET['papel'] ?? 0), 'status' => (string)($_GET['status'] ?? '')];
$sql = 'SELECT u.id,u.nome,u.email,u.status,u.criado_em,u.ultimo_login,u.papel_id,u.nivel_acesso FROM usuarios u WHERE 1=1';
$p = [];
if ($f['q'] !== '') { $sql .= ' AND (u.nome LIKE ? OR u.email LIKE ?)'; $p[] = "%{$f['q']}%"; $p[] = "%{$f['q']}%"; }
if ($f['papel'] === -1) $sql .= ' AND u.papel_id IS NULL';
elseif ($f['papel']) { $sql .= ' AND u.papel_id=?'; $p[] = $f['papel']; }
if (in_array($f['status'], ['ativo', 'inativo'], true)) { $sql .= ' AND u.status=?'; $p[] = $f['status']; }
$users = db_rows($pdo, $sql . ' ORDER BY u.criado_em DESC', $p);
$edit = isset($_GET['edit']) ? db_one($pdo, 'SELECT * FROM usuarios WHERE id=?', [(int)$_GET['edit']]) : null;
if (!empty($_SESSION['form_usuario'])) { $edit = array_merge($edit ?? [], $_SESSION['form_usuario']); unset($_SESSION['form_usuario']); }
$showForm = $edit || isset($_GET['novo']);
$minLen = max(8, (int)setting($pdo, 'seg_senha_min', '8'));
include __DIR__ . '/includes/header.php';
?>
<main class="admin-content">
  <div class="admin-heading">
    <div><h1>Usuários</h1><p class="muted">Contas do painel e cadastros do site. Usuários sem papel não acessam o painel (cadastros públicos).</p></div>
    <a class="btn btn-primary" href="?novo=1">+ Novo usuário</a>
  </div>
  <?php if ($showForm): $eid = (int)($edit['id'] ?? 0); ?>
  <div class="admin-panel mb-4 edit-panel<?= $eid ? ' is-editing' : '' ?>">
    <div class="edit-panel-head"><?php if ($eid): ?><span class="edit-badge">✎ Editando</span><h2><?= e($edit['nome']) ?></h2><?php else: ?><h2>Novo usuário</h2><?php endif; ?><a class="btn btn-sm btn-outline-secondary ms-auto" href="gerenciar_usuarios.php">Cancelar</a></div>
    <form class="admin-form" method="post" action="gerenciar_usuarios.php" autocomplete="off">
      <input type="hidden" name="id" value="<?= $eid ?>"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <div class="form-grid-2">
        <label>Nome<input name="nome" required value="<?= e($edit['nome'] ?? '') ?>"></label>
        <label>E-mail<input type="email" name="email" required value="<?= e($edit['email'] ?? '') ?>"></label>
      </div>
      <div class="form-grid-2">
        <label>Papel <small class="muted">Define o que a pessoa pode fazer no painel.</small>
          <select name="papel_id">
            <option value="">Sem acesso ao painel (cadastro do site)</option>
            <?php foreach ($roles as $r): if (!$canAssign((int)$r['id'])) continue; ?>
            <option value="<?= (int)$r['id'] ?>" <?= (int)($edit['papel_id'] ?? 0) === (int)$r['id'] ? 'selected' : '' ?>><?= e($r['nome']) ?></option>
            <?php endforeach; ?>
          </select></label>
        <label>Status<select name="status"><option value="ativo" <?= ($edit['status'] ?? 'ativo') === 'ativo' ? 'selected' : '' ?>>Ativo</option><option value="inativo" <?= ($edit['status'] ?? '') === 'inativo' ? 'selected' : '' ?>>Inativo</option></select></label>
      </div>
      <label>Senha <small class="muted"><?= $eid ? 'Deixe em branco para manter a atual. ' : '' ?>Mínimo de <?= $minLen ?> caracteres<?= setting($pdo, 'seg_senha_forte', '1') === '1' ? ', com letras e números' : '' ?>.</small><input type="password" name="senha" <?= $eid ? '' : 'required' ?> minlength="<?= $minLen ?>" autocomplete="new-password"></label>
      <?php if (!$eid): ?><label class="check-row"><input type="checkbox" name="notificar" value="1" checked> Enviar e-mail de boas-vindas com o endereço do painel</label><?php endif; ?>
      <div><button class="btn btn-primary">Salvar usuário</button></div>
    </form>
  </div>
  <?php endif; ?>
  <div class="admin-panel">
    <form class="admin-filter" method="get">
      <input name="q" value="<?= e($f['q']) ?>" placeholder="Buscar por nome ou e-mail">
      <select name="papel"><option value="0">Todos os papéis</option><option value="-1" <?= $f['papel'] === -1 ? 'selected' : '' ?>>Sem papel (site)</option><?php foreach ($roles as $r): ?><option value="<?= (int)$r['id'] ?>" <?= $f['papel'] === (int)$r['id'] ? 'selected' : '' ?>><?= e($r['nome']) ?></option><?php endforeach; ?></select>
      <select name="status"><option value="">Todos os status</option><option value="ativo" <?= $f['status'] === 'ativo' ? 'selected' : '' ?>>Ativos</option><option value="inativo" <?= $f['status'] === 'inativo' ? 'selected' : '' ?>>Inativos / aguardando</option></select>
      <button class="btn btn-outline-secondary">Filtrar</button>
    </form>
    <div class="table-responsive">
      <table class="admin-table">
        <thead><tr><th>Nome</th><th>E-mail</th><th>Papel</th><th>Status</th><th>Último acesso</th><th>Ações</th></tr></thead>
        <tbody>
        <?php foreach ($users as $u): $rn = $roleById[$u['papel_id']]['nome'] ?? ($u['nivel_acesso'] === 'admin' ? 'Administrador' : null); $active = $edit && (int)($edit['id'] ?? 0) === (int)$u['id']; ?>
          <tr class="<?= $active ? 'is-active-row' : '' ?>">
            <td><strong><?= e($u['nome']) ?></strong><?= (int)$u['id'] === (int)$me['id'] ? ' <span class="badge-soft">você</span>' : '' ?></td>
            <td><?= e($u['email']) ?></td>
            <td><?= $rn ? e($rn) : '<span class="muted">sem acesso ao painel</span>' ?></td>
            <td><span class="status status-<?= e($u['status']) ?>"><?= $u['status'] === 'ativo' ? 'Ativo' : 'Inativo' ?></span></td>
            <td><?= $u['ultimo_login'] ? e(date('d/m/Y H:i', strtotime($u['ultimo_login']))) : '<span class="muted">nunca</span>' ?></td>
            <td class="actions">
              <?php if ($u['status'] === 'inativo'): ?><a class="btn-action" href="?ativar=<?= (int)$u['id'] ?>&token=<?= e(csrf_token()) ?>">Ativar</a><?php endif; ?>
              <a class="btn-action<?= $active ? ' is-active' : '' ?>" href="?edit=<?= (int)$u['id'] ?>">Editar</a>
              <a class="btn-action" href="auditoria.php?usuario=<?= (int)$u['id'] ?>">Atividade</a>
              <?php if ((int)$u['id'] !== (int)$me['id']): ?><a class="btn-action danger" href="?delete=<?= (int)$u['id'] ?>&token=<?= e(csrf_token()) ?>" data-confirm="Excluir o usuário <?= e($u['nome']) ?>? Ele perderá o acesso imediatamente. Você poderá desfazer pela lixeira.">Excluir</a><?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$users): ?><tr><td colspan="6" class="muted">Nenhum usuário encontrado.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
