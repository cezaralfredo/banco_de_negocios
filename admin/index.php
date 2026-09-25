<?php 
require_once __DIR__.'/../config/database.php'; 
require_once __DIR__.'/../includes/functions.php';
session_start(); 

if(isset($_GET['logout'])){
    session_destroy();
    header('Location:index.php');
    exit;
} 

if(!empty($_SESSION['admin_id'])){
    header('Location:dashboard.php');
    exit;
} 

$erro=''; 

if($_SERVER['REQUEST_METHOD']==='POST'){ 
    $email=trim($_POST['email']??'');
    $senha=$_POST['senha']??'';
    
	$u = db_one($pdo, "SELECT * FROM usuarios WHERE email = ? AND nivel_acesso='admin'", [$email]);
	
    
    if($u && password_verify($senha,$u['senha'])){
        $_SESSION['admin_id']=$u['id'];
        $_SESSION['admin_nome']=$u['nome'];
        header('Location:dashboard.php');
        exit;
    } 
    $erro='E-mail ou senha inválidos.'; 
} 
?>
<!doctype html>
<html lang="pt-BR">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width,initial-scale=1">
	<title>Login | Banco de Negócios</title>
	<link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body class="login-page">
	<main class="login-box">
		<a href="../index.php" class="brand">
			<strong>BANCO DE</strong>
			<strong>NEGÓCIOS</strong>
		</a>
		<h1>Entrar no painel</h1>
		<p class="muted">Gerencie seus anúncios, categorias e banners.</p>
		<?php if($erro): ?>
		<p class="error">
			<?= htmlspecialchars($erro) ?>
		</p>
		<?php endif; ?>
		<form class="admin-form" method="post">
			<label>E-mail
				<input type="email" name="email" required autocomplete="email">
			</label>
			<label>Senha
				<input type="password" name="senha" required autocomplete="current-password">
			</label>
			<button class="btn btn-primary" type="submit">Entrar</button>
		</form>
		<p class="muted">Demo: admin@bancodenegocios.com.br / password</p>
	</main>
</body>
</html>





