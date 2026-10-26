<?php
$admin_title = 'Minha conta';
require __DIR__ . '/includes/init.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) { flash('error', 'Sessão expirada.'); redirect('minha_conta.php'); }
    $u = db_one($pdo, 'SELECT * FROM usuarios WHERE id=?', [$me['id']]);
    $nome = trim((string)($_POST['nome'] ?? ''));
    $atual = (string)($_POST['senha_atual'] ?? '');
    $nova = (string)($_POST['senha_nova'] ?? '');
    if ($nome === '') { flash('error', 'Informe seu nome.'); redirect('minha_conta.php'); }
    if ($nova !== '') {
        if (!password_verify($atual, $u['senha'])) { audit('login_falhou', 'usuario', $me['id'], ['motivo' => 'senha atual incorreta ao trocar senha']); flash('error', 'A senha atual está incorreta.'); redirect('minha_conta.php'); }
        if ($p = password_problem($nova)) { flash('error', $p); redirect('minha_conta.php'); }
        if ($nova !== ($_POST['senha_confirma'] ?? '')) { flash('error', 'A confirmação não confere com a nova senha.'); redirect('minha_conta.php'); }
        $pdo->prepare('UPDATE usuarios SET senha=? WHERE id=?')->execute([password_hash($nova, PASSWORD_DEFAULT), $me['id']]);
        session_regenerate_id(true);
        audit('senha_redefinida', 'usuario', $me['id'], ['origem' => 'minha conta']);
    }
    $pdo->prepare('UPDATE usuarios SET nome=? WHERE id=?')->execute([$nome, $me['id']]);
    $_SESSION['admin_nome'] = $nome;
    flash('success', $nova !== '' ? 'Dados e senha atualizados.' : 'Dados atualizados.');
    redirect('minha_conta.php');
}
$perms = $me['permissoes'] === ['*'] ? array_keys(permission_catalog()) : $me['permissoes'];
$history = db_rows($pdo, "SELECT acao, ip, criado_em FROM auditoria WHERE usuario_id=? AND acao IN ('login','login_falhou','logout','senha_redefinida') ORDER BY id DESC LIMIT 8", [$me['id']]);
include __DIR__ . '/includes/header.php';
?>
<main class="admin-content">
  <h1>Minha conta</h1>
  <div class="split">
    <div class="admin-panel">
      <form class="admin-form" method="post" style="padding:0">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <label>Nome<input name="nome" required value="<?= e($me['nome']) ?>"></label>
        <label>E-mail <small class="muted">Para alterar, peça a um administrador.</small><input value="<?= e($me['email']) ?>" disabled></label>
        <h3 class="form-section">Trocar senha</h3>
        <label>Senha atual<input type="password" name="senha_atual" autocomplete="current-password"></label>
        <div class="form-grid-2"><label>Nova senha<input type="password" name="senha_nova" minlength="8" autocomplete="new-password"></label><label>Confirmar nova senha<input type="password" name="senha_confirma" minlength="8" autocomplete="new-password"></label></div>
        <div><button class="btn btn-primary">Salvar</button></div>
      </form>
    </div>
    <aside>
      <div class="side-card"><h3>Seu papel: <?= e($me['papel_nome'] ?? '') ?></h3><p class="small muted"><?= count($perms) ?> permissões.</p>
        <details><summary class="small">Ver permissões</summary><ul class="small mt-2"><?php foreach ($perms as $p): ?><li><?= e(perm_label($p)) ?></li><?php endforeach; ?></ul></details></div>
      <div class="side-card"><h3>Acessos recentes</h3><ul class="timeline"><?php foreach ($history as $h): ?><li><?= e(audit_actions()[$h['acao']] ?? $h['acao']) ?><small><?= e(date('d/m/Y H:i', strtotime($h['criado_em']))) ?> · <?= e($h['ip']) ?></small></li><?php endforeach; ?></ul></div>
    </aside>
  </div>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
