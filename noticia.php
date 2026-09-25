<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
start_session();
ensure_schema($pdo);

$slug = trim((string)($_GET['slug'] ?? ''));
$news = $pdo ? db_one($pdo, 'SELECT * FROM noticias WHERE slug=? AND status=1 AND publicado_em<=NOW()', [$slug]) : null;
if (!$news) http_response_code(404);
$sidebarNews = get_news($pdo, 5, 0, (int)($news['id'] ?? 0));
$sidebarTitle = t('news.others');
$title = $news['titulo'] ?? t('page.not_found');

$page_title = $title . ' | ' . setting($pdo, 'site_name', 'Banco de Negócios');
$page_description = $news['resumo'] ?? '';
include __DIR__ . '/includes/header.php';
?>
<main id="conteudo" class="container inner-page profile-page">
  <a class="back-link" href="noticias.php"><?= e(t('page.back')) ?></a>
  <div class="profile-layout">
    <article>
      <div class="profile-hero" style="<?= !empty($news['imagem']) ? "background-image:linear-gradient(90deg,rgba(8,31,49,.88),rgba(8,31,49,.2)),url('" . e($news['imagem']) . "')" : '' ?>">
        <div class="profile-logo"><?= svg_icon('megafone', 'icon-hero-logo') ?></div>
        <div>
          <p class="eyebrow"><?= e(t('news.eyebrow')) ?></p>
          <h1><?= e($title) ?></h1>
          <?php if ($news): ?><strong><?= e(date('d/m/Y', strtotime($news['publicado_em']))) ?><?= !empty($news['autor']) ? ' · ' . e(t('news.by', ['autor' => $news['autor']])) : '' ?></strong><?php endif; ?>
        </div>
      </div>
      <div class="profile-body page-content">
        <?php if ($news): ?>
          <?php if (!empty($news['resumo'])): ?><p class="lead-text"><?= e($news['resumo']) ?></p><?php endif; ?>
          <?= safe_html($news['conteudo']) ?>
        <?php else: ?>
          <p><?= e(t('page.not_found_text')) ?></p>
        <?php endif; ?>
      </div>
    </article>
    <?php include __DIR__ . '/includes/sidebar_news.php'; ?>
  </div>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
