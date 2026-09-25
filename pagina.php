<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
start_session();
ensure_schema($pdo);

$slug = trim((string)($_GET['slug'] ?? ''));
$page = $pdo ? db_one($pdo, 'SELECT * FROM paginas WHERE slug=? AND status=1', [$slug]) : null;
if (!$page) http_response_code(404);
$siteName = setting($pdo, 'site_name', 'Banco de Negócios');

// Páginas relacionadas: página-mãe e irmãs (ou filhas), para a barra lateral
$sidebarPages = [];
if ($page) {
    $rootId = (int)($page['parent_id'] ?: $page['id']);
    $sidebarPages = db_rows($pdo, 'SELECT id,titulo,slug FROM paginas WHERE status=1 AND (id=? OR parent_id=?) ORDER BY parent_id IS NOT NULL, ordem, id', [$rootId, $rootId]);
    if (count($sidebarPages) < 2) $sidebarPages = db_rows($pdo, 'SELECT id,titulo,slug FROM paginas WHERE status=1 AND parent_id IS NULL ORDER BY ordem,id');
    foreach ($sidebarPages as &$sp) $sp['current'] = (int)$sp['id'] === (int)$page['id'];
    unset($sp);
}
$sidebarNews = get_news($pdo, 4);
$title = $page ? tc('pagina.' . $page['id'] . '.titulo', $page['titulo']) : t('page.not_found');
$content = $page ? tc('pagina.' . $page['id'] . '.conteudo', $page['conteudo']) : '';
$fromSignup = !empty($_GET['return']);

$page_title = $title . ' | ' . $siteName;
include __DIR__ . '/includes/header.php';
?>
<main id="conteudo" class="container inner-page profile-page">
  <?php if ($fromSignup): ?>
    <a class="back-link" href="index.php" data-signup-open><?= e(t('page.back_signup')) ?></a>
  <?php else: ?>
    <a class="back-link" href="javascript:history.back()"><?= e(t('page.back')) ?></a>
  <?php endif; ?>
  <div class="profile-layout">
    <article>
      <div class="profile-hero" style="<?= !empty($page['imagem']) ? "background-image:linear-gradient(90deg,rgba(8,31,49,.85),rgba(8,31,49,.2)),url('" . e($page['imagem']) . "')" : '' ?>">
        <div class="profile-logo"><?= svg_icon('site', 'icon-hero-logo') ?></div>
        <div><p class="eyebrow"><?= e(mb_strtoupper($siteName)) ?></p><h1><?= e($title) ?></h1></div>
      </div>
      <div class="profile-body page-content">
        <?php if ($page): ?>
          <?= safe_html($content) ?>
        <?php else: ?>
          <p><?= e(t('page.not_found_text')) ?></p>
        <?php endif; ?>
      </div>
    </article>
    <?php include __DIR__ . '/includes/sidebar_news.php'; ?>
  </div>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
