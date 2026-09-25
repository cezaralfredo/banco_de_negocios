<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
start_session();
ensure_schema($pdo);

$perPage = 6;
$pageNum = max(1, (int)($_GET['p'] ?? 1));
$total = (int)(db_one($pdo, 'SELECT COUNT(*) n FROM noticias WHERE status=1 AND publicado_em<=NOW()')['n'] ?? 0);
$pages = max(1, (int)ceil($total / $perPage));
$pageNum = min($pageNum, $pages);
$news = get_news($pdo, $perPage, ($pageNum - 1) * $perPage);
$lead = $news[0] ?? null;
$sidebarAds = get_featured_ads($pdo, 5) ?: get_ads($pdo, 5);

$page_title = t('news.title') . ' | ' . setting($pdo, 'site_name', 'Banco de Negócios');
include __DIR__ . '/includes/header.php';
?>
<main id="conteudo" class="container inner-page profile-page">
  <a class="back-link" href="index.php"><?= e(t('page.back')) ?></a>
  <div class="profile-layout">
    <article>
      <div class="profile-hero" style="<?= !empty($lead['imagem']) ? "background-image:linear-gradient(90deg,rgba(8,31,49,.88),rgba(8,31,49,.25)),url('" . e($lead['imagem']) . "')" : '' ?>">
        <div class="profile-logo"><?= svg_icon('megafone', 'icon-hero-logo') ?></div>
        <div><p class="eyebrow"><?= e(t('news.eyebrow')) ?></p><h1><?= e(t('news.title')) ?></h1><strong><?= e(t('news.subtitle')) ?></strong></div>
      </div>
      <div class="profile-body">
        <?php if (!$news): ?>
          <p class="muted"><?= e(t('news.empty')) ?></p>
        <?php else: ?>
          <div class="news-list">
            <?php foreach ($news as $n): ?>
            <article class="news-row">
              <a class="news-row-cover" href="noticia.php?slug=<?= urlencode($n['slug']) ?>" tabindex="-1" aria-hidden="true">
                <?php if (!empty($n['imagem'])): ?><img src="<?= e($n['imagem']) ?>" alt="" loading="lazy"><?php else: ?><span class="news-card-placeholder"><?= svg_icon('megafone', 'icon-benefit') ?></span><?php endif; ?>
              </a>
              <div>
                <time datetime="<?= e(date('Y-m-d', strtotime($n['publicado_em']))) ?>"><?= e(date('d/m/Y', strtotime($n['publicado_em']))) ?></time>
                <h2><a href="noticia.php?slug=<?= urlencode($n['slug']) ?>"><?= e($n['titulo']) ?></a></h2>
                <?php if (!empty($n['resumo'])): ?><p><?= e($n['resumo']) ?></p><?php endif; ?>
                <a class="news-card-more" href="noticia.php?slug=<?= urlencode($n['slug']) ?>"><?= e(t('news.read_more')) ?></a>
              </div>
            </article>
            <?php endforeach; ?>
          </div>
          <?php if ($pages > 1): ?>
          <nav class="pager" aria-label="Paginação">
            <?php if ($pageNum > 1): ?><a class="btn btn-outline" href="?p=<?= $pageNum - 1 ?>"><?= e(t('news.prev')) ?></a><?php endif; ?>
            <span><?= $pageNum ?> / <?= $pages ?></span>
            <?php if ($pageNum < $pages): ?><a class="btn btn-outline" href="?p=<?= $pageNum + 1 ?>"><?= e(t('news.next')) ?></a><?php endif; ?>
          </nav>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </article>
    <aside class="profile-sidebar">
      <h2><?= e(t('home.ads_title')) ?></h2>
      <?php foreach ($sidebarAds as $other): ?>
      <a class="sidebar-ad" href="<?= e(ad_profile_url($other)) ?>">
        <?php if (ad_cover($other)): ?><img src="<?= e(ad_cover($other)) ?>" alt=""><?php else: ?><span class="sidebar-thumb"><?= e(initials($other['titulo'])) ?></span><?php endif; ?>
        <span><strong><?= e($other['titulo']) ?></strong><small><?= e($other['categoria'] ?? '') ?></small></span>
      </a>
      <?php endforeach; ?>
    </aside>
  </div>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
