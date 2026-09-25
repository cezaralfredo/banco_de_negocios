<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_admin();
ensure_schema($pdo);
$current_admin = basename($_SERVER['PHP_SELF']);
$adminNav = [
    'Conteúdo' => [
        'dashboard.php' => ['▦', 'Dashboard'],
        'gerenciar_anuncios.php' => ['▣', 'Anúncios'],
        'gerenciar_categorias.php' => ['◉', 'Categorias'],
        'gerenciar_noticias.php' => ['✎', 'Notícias'],
        'gerenciar_banners.php' => ['▰', 'Banners e slider'],
        'beneficios.php' => ['★', 'Textos do banner'],
        'promocao.php' => ['▤', 'Promoção da home'],
        'configuracoes.php' => ['▤', 'Páginas e textos'],
    ],
    'Aparência' => [
        'identidade.php' => ['✦', 'Identidade do site'],
        'aparencia.php' => ['◒', 'Aparência'],
        'menu.php' => ['☷', 'Menu principal'],
        'rodape.php' => ['▁', 'Rodapé e redes sociais'],
        'idiomas.php' => ['🌐', 'Idiomas e traduções'],
    ],
    'Sistema' => [
        'gerenciar_usuarios.php' => ['♟', 'Usuários'],
    ],
];
?><!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($admin_title ?? 'Painel') ?> | Painel</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/admin.css?v=6">
</head>
<body>
<div class="admin-shell">
  <aside class="admin-side">
    <a href="dashboard.php" class="brand"><strong>BANCO DE</strong><strong>NEGÓCIOS</strong></a>
    <?php foreach ($adminNav as $section => $links): ?>
    <p class="admin-section-label"><?= e($section) ?></p>
    <nav>
      <?php foreach ($links as $file => [$icon, $label]): ?>
      <a class="<?= $current_admin === $file ? 'active' : '' ?>" href="<?= e($file) ?>"<?= $current_admin === $file ? ' aria-current="page"' : '' ?> title="<?= e($label) ?>"><?= $icon ?> &nbsp; <?= e($label) ?></a>
      <?php endforeach; ?>
      <?php if ($section === 'Sistema'): ?>
      <a href="../index.php" target="_blank">⌂ &nbsp; Ver site</a>
      <a href="index.php?logout=1">↪ &nbsp; Sair</a>
      <?php endif; ?>
    </nav>
    <?php endforeach; ?>
  </aside>
  <div class="admin-main">
    <header class="admin-top navbar navbar-light bg-white"><span>Painel Administrativo</span><span>Olá, <?= e($_SESSION['admin_nome'] ?? 'Admin') ?> &nbsp; ◉</span></header>
