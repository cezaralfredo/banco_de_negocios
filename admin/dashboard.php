<?php
$admin_title = 'Dashboard';
include __DIR__ . '/includes/header.php';
$count = fn(string $sql) => (int)(db_one($pdo, $sql)['n'] ?? 0);
$totals = [
    ['Anúncios ativos', $count("SELECT COUNT(*) n FROM anuncios WHERE status='ativo'"), 'gerenciar_anuncios.php'],
    ['Categorias', $count('SELECT COUNT(*) n FROM categorias'), 'gerenciar_categorias.php'],
    ['Notícias publicadas', $count('SELECT COUNT(*) n FROM noticias WHERE status=1'), 'gerenciar_noticias.php'],
    ['Usuários aguardando ativação', $count("SELECT COUNT(*) n FROM usuarios WHERE status='inativo'"), 'gerenciar_usuarios.php'],
];
$langs = get_languages($pdo);
?>
<main class="admin-content">
  <h1>Dashboard</h1>
  <p class="muted">Visão geral do <?= e(setting($pdo, 'site_name', 'Banco de Negócios')) ?>.</p>
  <div class="admin-cards">
    <?php foreach ($totals as [$label, $value, $link]): ?>
    <a class="stat-card" href="<?= e($link) ?>"><span class="muted"><?= e($label) ?></span><strong><?= $value ?></strong><span class="muted">Gerenciar →</span></a>
    <?php endforeach; ?>
  </div>
  <div class="admin-panel">
    <h2>Ações rápidas</h2>
    <p class="d-flex flex-wrap gap-2">
      <a class="btn btn-primary" href="gerenciar_anuncios.php?novo=1">+ Novo anúncio</a>
      <a class="btn btn-outline-secondary" href="gerenciar_noticias.php?novo=1">+ Nova notícia</a>
      <a class="btn btn-outline-secondary" href="gerenciar_banners.php">Banners e slider</a>
      <a class="btn btn-outline-secondary" href="rodape.php">Rodapé e redes sociais</a>
      <a class="btn btn-outline-secondary" href="idiomas.php">Idiomas (<?= count($langs) ?> ativos)</a>
    </p>
  </div>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
