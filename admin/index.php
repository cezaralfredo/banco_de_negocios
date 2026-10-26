<?php
require_once __DIR__ . '/../config/database.php';

if (isset($_GET['logout'])) {
    if (verify_csrf($_GET['token'] ?? null) && current_user()) {
        audit('logout', 'usuario', current_user()['id']);
        do_action('user_logout', current_user());
    }
    $_SESSION = [];
    session_regenerate_id(true);
    redirect('index.php?saiu=1');
}
if (current_user() && can('acessar:painel')) redirect('dashboard.php');

$erro = '';
$info = isset($_GET['saiu']) ? 'Você saiu do painel com segurança.' : (isset($_GET['expirada']) ? 'Sua sessão expirou por inatividade. Entre novamente.' : (isset($_GET['redefinida']) ? 'Senha alterada. Entre com a nova senha.' : ''));
$email = trim((string)($_POST['email'] ?? ''));
$voltar = (string)($_GET['voltar'] ?? $_POST['voltar'] ?? '');
// Só aceita caminhos locais do painel (bloqueia //outro-site e esquemas)
if (!preg_match('~^/(?![/\\\\])[A-Za-z0-9_./-]*admin/[a-z_]+\.php(\?[^\s#]*)?$~', $voltar)) $voltar = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $senha = (string)($_POST['senha'] ?? '');
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $erro = 'Sessão expirada. Atualize a página e tente novamente.';
    } elseif (!$pdo) {
        $erro = 'Banco de dados indisponível. Verifique a configuração do servidor.';
    } elseif ($min = login_blocked($email)) {
        $erro = "Muitas tentativas sem sucesso. Tente novamente em {$min} minuto(s).";
        audit('login_bloqueado', 'usuario', null, ['email' => $email]);
    } else {
        $u = db_one($pdo, 'SELECT * FROM usuarios WHERE email=?', [strtolower($email)]);
        $ok = $u && password_verify($senha, $u['senha']) && ($u['status'] ?? 'ativo') === 'ativo';
        login_attempt($email, $ok);
        if ($ok) {
            session_regenerate_id(true);
            $_SESSION['admin_id'] = (int)$u['id'];
            $_SESSION['admin_nome'] = $u['nome'];
            $_SESSION['last_activity'] = time();
            if (password_needs_rehash($u['senha'], PASSWORD_DEFAULT)) $pdo->prepare('UPDATE usuarios SET senha=? WHERE id=?')->execute([password_hash($senha, PASSWORD_DEFAULT), $u['id']]);
            $pdo->prepare('UPDATE usuarios SET ultimo_login=NOW() WHERE id=?')->execute([$u['id']]);
            if (!can('acessar:painel')) {
                audit('acesso_negado', 'painel', $u['id'], ['email' => $email]);
                $_SESSION = [];
                $erro = 'Sua conta não tem acesso ao painel administrativo.';
            } else {
                audit('login', 'usuario', $u['id']);
                do_action('user_login', current_user());
                redirect($voltar ?: 'dashboard.php');
            }
        } else {
            $GLOBALS['__bn_audit_user'] = ['id' => $u['id'] ?? null, 'nome' => $email];
            audit('login_falhou', 'usuario', $u['id'] ?? null, ['email' => $email, 'motivo' => $u ? (($u['status'] ?? '') !== 'ativo' ? 'conta inativa' : 'senha incorreta') : 'e-mail não cadastrado']);
            unset($GLOBALS['__bn_audit_user']);
            $erro = 'E-mail ou senha inválidos.';
        }
    }
}
$site = setting($pdo, 'site_name', 'CMS');
$logo = setting($pdo, 'site_logo', '');
?><!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Entrar | <?= e($site) ?></title>
<?php if ($fav = setting($pdo, 'site_favicon', '')): ?><link rel="icon" href="../<?= e($fav) ?>"><?php endif; ?>
<link href="https://fonts.googleapis.com/css2?family=Hanken+Grotesk:wght@400;500;600;700&family=Poppins:wght@500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset('assets/css/admin.css', '../')) ?>">
<style>:root{--wine:<?= e(theme_var('cor_primaria', '#9f1549')) ?>}.login-page{background:linear-gradient(135deg,<?= e(theme_var('hero_inicio', '#09213a')) ?>,<?= e(theme_var('cor_primaria', '#9f1549')) ?>)}</style>
</head>
<body class="login-page">
<main class="login-box">
  <a href="../index.php" class="brand login-brand">
    <?php if ($logo): ?><img src="../<?= e($logo) ?>" alt="<?= e($site) ?>" style="max-height:56px;max-width:220px"><?php else: ?><strong><?= e(mb_strtoupper($site)) ?></strong><?php endif; ?>
  </a>
  <h1>Entrar no painel</h1>
  <p class="muted">Acesse com o e-mail e a senha cadastrados.</p>
  <?php if ($info): ?><p class="notice success"><?= e($info) ?></p><?php endif; ?>
  <?php if ($erro): ?><p class="notice error" role="alert"><?= e($erro) ?></p><?php endif; ?>
  <form class="admin-form" method="post" style="padding:0">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="voltar" value="<?= e($voltar) ?>">
    <label>E-mail<input type="email" name="email" required autocomplete="username" value="<?= e($email) ?>" autofocus></label>
    <label>Senha<input type="password" name="senha" required autocomplete="current-password"></label>
    <button class="btn btn-primary" type="submit">Entrar</button>
  </form>
  <p class="muted mt-3"><a href="esqueci_senha.php" style="color:var(--wine);font-weight:700">Esqueci minha senha</a></p>
  <?php if (BN_TENANT !== 'principal'): ?><p class="muted">Cliente: <?= e(BN_TENANT_DATA['nome'] ?? BN_TENANT) ?></p><?php endif; ?>
</main>
</body>
</html>
