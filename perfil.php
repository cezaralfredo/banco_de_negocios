<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
start_session();
ensure_schema($pdo);

$id = (int)($_GET['id'] ?? 0);
$ad = $pdo ? db_one($pdo, 'SELECT a.*,c.nome categoria FROM anuncios a LEFT JOIN categorias c ON c.id=a.categoria_id WHERE a.id=? AND a.status="ativo"', [$id]) : null;
$ads = get_ads($pdo, 12);
if (!$ad) {
    foreach ($ads as $x) if ((int)$x['id'] === $id) $ad = $x;
}
if (!$ad) {
    http_response_code(404);
    $ad = ['id' => 0, 'titulo' => t('page.not_found'), 'descricao' => t('page.not_found_text'), 'categoria' => '', 'localizacao' => '', 'avaliacao' => 0];
}
$posts = $pdo && $ad['id'] ? db_rows($pdo, 'SELECT * FROM blog_posts WHERE anuncio_id=? AND status=1 ORDER BY criado_em DESC', [(int)$ad['id']]) : [];
$others = array_values(array_filter($ads, fn($x) => (int)($x['id'] ?? 0) !== (int)$ad['id']));
$wa = preg_replace('/\D+/', '', (string)($ad['whatsapp'] ?? ''));
$page_title = $ad['titulo'] . ' | ' . setting($pdo, 'site_name', 'Banco de Negócios');
$page_description = mb_strimwidth((string)$ad['descricao'], 0, 155, '…');
include __DIR__ . '/includes/header.php';
?>
<main id="conteudo" class="container inner-page profile-page">
  <a class="back-link" href="javascript:history.back()"><?= e(t('page.back')) ?></a>
  <div class="profile-layout">
    <article>
      <div class="profile-hero" style="<?= ad_cover($ad) ? "background-image:linear-gradient(90deg,rgba(8,31,49,.82),rgba(8,31,49,.18)),url('" . e(ad_cover($ad)) . "')" : '' ?>">
        <div class="profile-logo"><?php if (!empty($ad['logo'])): ?><img src="<?= e($ad['logo']) ?>" alt="Logo de <?= e($ad['titulo']) ?>"><?php else: ?><span><?= e(initials($ad['titulo'])) ?></span><?php endif; ?></div>
        <div><p class="eyebrow"><?= e(t('profile.eyebrow')) ?></p><h1><?= e($ad['titulo']) ?></h1><strong><?= e($ad['categoria'] ?? '') ?></strong></div>
      </div>
      <div class="profile-body">
        <div class="profile-meta">
          <?php if (!empty($ad['localizacao'])): ?><span><?= svg_icon('mapa', 'icon-sm') ?> <?= e($ad['localizacao']) ?></span><?php endif; ?>
          <span class="rating">★ <?= e(number_format((float)str_replace(',', '.', (string)($ad['avaliacao'] ?? 0)), 1, ',', '')) ?></span>
          <?php if (!empty($ad['link']) && preg_match('#^https?://#', $ad['link'])): ?><a href="<?= e($ad['link']) ?>" target="_blank" rel="noopener"><?= svg_icon('site', 'icon-sm') ?> Site</a><?php endif; ?>
          <?php if ($wa): ?><a class="btn btn-primary" target="_blank" rel="noopener" href="https://wa.me/<?= e($wa) ?>?text=<?= rawurlencode(t('card.whatsapp_msg')) ?>"><?= svg_icon('whatsapp', 'icon-sm') ?> <?= e(t('profile.whatsapp')) ?></a><?php endif; ?>
        </div>
        <h2><?= e(t('profile.about')) ?></h2>
        <p><?= nl2br(e($ad['descricao'] ?? '')) ?></p>
        <section class="profile-blog">
          <h2><?= e(t('profile.posts')) ?></h2>
          <?php if ($posts): foreach ($posts as $post): ?>
            <article><h3><?= e($post['titulo']) ?></h3><small><?= e(date('d/m/Y', strtotime($post['criado_em']))) ?></small><p><?= nl2br(e($post['conteudo'])) ?></p></article>
          <?php endforeach; else: ?>
            <p class="muted"><?= e(t('profile.no_posts')) ?></p>
          <?php endif; ?>
        </section>
      </div>
    </article>
    <aside class="profile-sidebar">
      <h2><?= e(t('profile.others')) ?></h2>
      <?php foreach (array_slice($others, 0, 5) as $other): ?>
      <a class="sidebar-ad" href="<?= e(ad_profile_url($other)) ?>">
        <?php if (ad_cover($other)): ?><img src="<?= e(ad_cover($other)) ?>" alt=""><?php else: ?><span class="sidebar-thumb"><?= e(initials($other['titulo'])) ?></span><?php endif; ?>
        <span><strong><?= e($other['titulo']) ?></strong><small><?= e($other['categoria'] ?? '') ?><br>★ <?= e(number_format((float)str_replace(',', '.', (string)($other['avaliacao'] ?? 0)), 1, ',', '')) ?></small></span>
      </a>
      <?php endforeach; ?>
    </aside>
  </div>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
