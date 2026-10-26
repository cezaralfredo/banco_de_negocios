<?php
/* Recuperação de senha por e-mail (requer SMTP configurado em Configurações globais › E-mail). */
require_once __DIR__ . '/../config/database.php';
$sent = false; $erro = '';
$token = (string)($_GET['token'] ?? $_POST['token'] ?? '');
$site = setting($pdo, 'site_name', 'CMS');

if ($token !== '') {
    $row = db_one($pdo, 'SELECT r.*, u.email FROM senha_resets r JOIN usuarios u ON u.id=r.usuario_id WHERE r.token_hash=? AND r.usado=0 AND r.expira_em > NOW()', [hash('sha256', $token)]);
    if (!$row) $erro = 'Link inválido ou expirado. Solicite um novo.';
    elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $senha = (string)($_POST['senha'] ?? '');
        if (!verify_csrf($_POST['csrf_token'] ?? null)) $erro = 'Sessão expirada. Tente novamente.';
        elseif ($p = password_problem($senha)) $erro = $p;
        elseif ($senha !== ($_POST['confirmacao'] ?? '')) $erro = 'As senhas não coincidem.';
        else {
            $pdo->prepare('UPDATE usuarios SET senha=? WHERE id=?')->execute([password_hash($senha, PASSWORD_DEFAULT), $row['usuario_id']]);
            $pdo->prepare('UPDATE senha_resets SET usado=1 WHERE usuario_id=?')->execute([$row['usuario_id']]);
            $GLOBALS['__bn_audit_user'] = ['id' => $row['usuario_id'], 'nome' => $row['email']];
            audit('senha_redefinida', 'usuario', $row['usuario_id']);
            redirect('index.php?redefinida=1');
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    if (!verify_csrf($_POST['csrf_token'] ?? null)) $erro = 'Sessão expirada. Tente novamente.';
    elseif (login_blocked('reset:' . $email)) $erro = 'Muitas solicitações. Aguarde alguns minutos.';
    else {
        login_attempt('reset:' . $email, false); // limite próprio (não bloqueia o login da conta)
        $u = db_one($pdo, "SELECT id,nome,email FROM usuarios WHERE email=? AND status='ativo'", [$email]);
        $base = trusted_base_url();
        if ($u && !$base) error_log('[CMS] Redefinição de senha não enviada: defina o endereço oficial do site em Configurações globais.');
        if ($u && $base) {
            $tok = bin2hex(random_bytes(24));
            $pdo->prepare('INSERT INTO senha_resets (usuario_id,token_hash,expira_em) VALUES (?,?,DATE_ADD(NOW(), INTERVAL 1 HOUR))')->execute([$u['id'], hash('sha256', $tok)]);
            $link = $base . 'admin/esqueci_senha.php?token=' . $tok;
            send_mail($u['email'], "Redefinição de senha — $site", mail_template('Redefinir sua senha', '<p>Olá, ' . e($u['nome']) . '.</p><p>Recebemos um pedido para redefinir a senha do painel. O link vale por 1 hora. Se não foi você, ignore esta mensagem.</p>', $link, 'Criar nova senha'));
            $GLOBALS['__bn_audit_user'] = ['id' => $u['id'], 'nome' => $u['email']];
            audit('senha_reset_solicitado', 'usuario', $u['id']);
        }
        $sent = true; // mesma resposta para e-mails existentes ou não (evita enumeração)
    }
}
?><!doctype html>
<html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex">
<title>Recuperar senha | <?= e($site) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Hanken+Grotesk:wght@400;500;600;700&family=Poppins:wght@500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset('assets/css/admin.css', '../')) ?>">
<style>:root{--wine:<?= e(theme_var('cor_primaria', '#9f1549')) ?>}.login-page{background:linear-gradient(135deg,<?= e(theme_var('hero_inicio', '#09213a')) ?>,<?= e(theme_var('cor_primaria', '#9f1549')) ?>)}</style>
</head><body class="login-page"><main class="login-box">
<h1><?= $token !== '' ? 'Criar nova senha' : 'Recuperar senha' ?></h1>
<?php if ($erro): ?><p class="notice error" role="alert"><?= e($erro) ?></p><?php endif; ?>
<?php if ($sent): ?>
  <p class="notice success">Se o e-mail estiver cadastrado, você receberá um link para criar uma nova senha em instantes.</p>
<?php elseif ($token !== '' && empty($row)): ?>
  <p><a href="esqueci_senha.php">Solicitar um novo link</a></p>
<?php elseif ($token !== ''): ?>
  <form class="admin-form" method="post" style="padding:0">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="token" value="<?= e($token) ?>">
    <label>Nova senha<input type="password" name="senha" required minlength="8" autocomplete="new-password"></label>
    <label>Confirmar nova senha<input type="password" name="confirmacao" required minlength="8" autocomplete="new-password"></label>
    <button class="btn btn-primary">Salvar nova senha</button>
  </form>
<?php else: ?>
  <p class="muted">Informe o e-mail da sua conta. Enviaremos um link para criar uma nova senha.</p>
  <form class="admin-form" method="post" style="padding:0">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <label>E-mail<input type="email" name="email" required autocomplete="email"></label>
    <button class="btn btn-primary">Enviar link</button>
  </form>
<?php endif; ?>
<p class="muted mt-3"><a href="index.php" style="color:var(--wine);font-weight:700">← Voltar ao login</a></p>
</main></body></html>
