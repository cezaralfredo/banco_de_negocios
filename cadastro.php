<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
start_session();
ensure_schema($pdo);

$isAjax = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest' || isset($_GET['ajax']);
$erro = '';
$sucesso = false;
$form = ['nome' => trim((string)($_POST['nome'] ?? '')), 'email' => trim((string)($_POST['email'] ?? '')), 'termos' => !empty($_POST['termos'])];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower($form['email']);
    $senha = (string)($_POST['senha'] ?? '');
    $confirmacao = (string)($_POST['confirmacao'] ?? '');
    if (!$pdo) $erro = t('signup.err_db');
    elseif (!verify_csrf($_POST['csrf_token'] ?? null)) $erro = t('signup.err_session');
    elseif (mb_strlen($form['nome']) < 3) $erro = t('signup.err_name');
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $erro = t('signup.err_email');
    elseif (strlen($senha) < 8) $erro = t('signup.err_password');
    elseif ($senha !== $confirmacao) $erro = t('signup.err_confirm');
    elseif (!$form['termos']) $erro = t('signup.err_terms');
    elseif (db_one($pdo, 'SELECT id FROM usuarios WHERE email=?', [$email])) $erro = t('signup.err_exists');
    else {
        try {
            $pdo->prepare('INSERT INTO usuarios(nome,email,senha,nivel_acesso,status) VALUES(?,?,?,"user","inativo")')
                ->execute([$form['nome'], $email, password_hash($senha, PASSWORD_DEFAULT)]);
            $sucesso = true;
        } catch (Throwable $ex) {
            $erro = t('signup.err_db');
        }
    }
    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($sucesso ? ['ok' => true, 'message' => t('signup.success_text')] : ['ok' => false, 'error' => $erro], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// Página de fallback (sem JavaScript). Com JavaScript, o cadastro abre no modal padrão.
$page_title = t('signup.title') . ' | ' . setting($pdo, 'site_name', 'Banco de Negócios');
include __DIR__ . '/includes/header.php';
$termsLink = '<a target="_blank" rel="noopener" href="pagina.php?slug=termos-de-uso">' . e(t('signup.terms_link')) . '</a>';
?>
<main id="conteudo" class="container auth-page">
  <div class="auth-card">
    <p class="eyebrow dark mb-1"><?= e(t('signup.eyebrow')) ?></p>
    <h1 class="fs-3"><?= e(t('signup.title')) ?></h1>
    <?php if ($sucesso): ?>
      <div class="alert alert-success"><strong><?= e(t('signup.success_title')) ?></strong><br><?= e(t('signup.success_text')) ?></div>
      <a class="btn btn-primary" href="index.php"><?= e(t('page.back')) ?></a>
    <?php else: ?>
      <?php if ($erro): ?><div class="alert alert-danger"><?= e($erro) ?></div><?php endif; ?>
      <p class="muted"><?= e(t('signup.intro')) ?></p>
      <form class="auth-form" method="post">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <label><?= e(t('signup.name')) ?><input name="nome" required minlength="3" value="<?= e($form['nome']) ?>" autocomplete="name"></label>
        <label><?= e(t('signup.email')) ?><input type="email" name="email" required value="<?= e($form['email']) ?>" autocomplete="email"></label>
        <label><?= e(t('signup.password')) ?><input type="password" name="senha" required minlength="8" autocomplete="new-password"></label>
        <label><?= e(t('signup.confirm')) ?><input type="password" name="confirmacao" required minlength="8" autocomplete="new-password"></label>
        <label class="check-row"><input type="checkbox" name="termos" value="1" required <?= $form['termos'] ? 'checked' : '' ?>> <span><?= str_replace('{link}', $termsLink, e(t('signup.terms'))) ?></span></label>
        <button class="btn btn-primary w-100"><?= e(t('signup.submit')) ?></button>
      </form>
      <p class="auth-footer"><?= e(t('signup.have_account')) ?> <a href="admin/index.php"><?= e(t('signup.login')) ?></a></p>
    <?php endif; ?>
  </div>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
