<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
start_session();
ensure_schema($pdo);

$cats = get_featured_categories($pdo);
$ads = get_ads($pdo);
$featured_ads = get_featured_ads($pdo) ?: array_slice($ads, 0, 4);
// Cards sobre o banner: destaques primeiro; se houver menos de 3, completa com os mais bem avaliados
$hero_ads = $featured_ads;
foreach ($ads as $x) {
    if (count($hero_ads) >= 3) break;
    if (!in_array((int)$x['id'], array_map('intval', array_column($hero_ads, 'id')), true)) $hero_ads[] = $x;
}
$hero_ads = array_slice($hero_ads, 0, 3);
$benefits = get_benefits($pdo);
$news = get_news($pdo, 3);
$popular = db_rows($pdo, 'SELECT termo FROM buscas_populares ORDER BY contagem DESC LIMIT 5');
$searchCats = get_categories($pdo);
$searchLocations = get_locations($pdo);
$filters = search_filters([]);

// Slider
$banners = get_banners($pdo);
if (!$banners) {
    $banners = [['id' => 0, 'titulo' => 'Divulgue seu negócio. Encontre oportunidades.', 'subtitulo' => 'Conectamos empreendedores, vendedores e prestadores de serviços a potenciais clientes em um só lugar.', 'imagem_fundo' => '', 'texto_botao' => 'Criar conta', 'link_botao' => 'cadastro.php', 'duracao_segundos' => 5, 'efeito' => 'padrao']];
}
$slider = [
    'arrows' => setting($pdo, 'slider_arrows', '1') === '1',
    'dots' => setting($pdo, 'slider_dots', '1') === '1',
    'autoplay' => setting($pdo, 'slider_autoplay', '1') === '1',
    'effect' => setting($pdo, 'slider_effect', 'fade'),
];
$multi = count($banners) > 1;

// Área promocional "Amplie suas oportunidades!" (Admin › Promoção da home)
$promo = $pdo ? db_one($pdo, 'SELECT * FROM home_promocao WHERE id=1') : null;
$promo = $promo ?: ['titulo' => 'Amplie suas oportunidades!', 'texto' => 'Cadastre seu negócio e seja encontrado por milhares de pessoas.', 'imagem' => '', 'link' => 'cadastro.php', 'ativo' => 1, 'beneficios' => "Cadastre seu negócio em poucos minutos\nApareça para clientes da sua região\nGerencie seus anúncios facilmente\nConecte-se e faça mais negócios", 'texto_botao' => ''];
$promoBenefits = array_values(array_filter(array_map('trim', preg_split('/\R/', tc('promo.beneficios', (string)($promo['beneficios'] ?? ''))))));
$promoLink = trim((string)($promo['link'] ?? '')) ?: 'cadastro.php';
$promoIsSignup = basename(strtok($promoLink, '?#')) === 'cadastro.php';

$page_title = setting($pdo, 'site_name', 'Banco de Negócios');
include __DIR__ . '/includes/header.php';
?>
<main id="conteudo">
<section class="hero banner-hero<?= $slider['arrows'] && $multi ? ' has-arrows' : '' ?>" aria-roledescription="carousel" aria-label="Destaques">
  <div class="banner-slides" data-banner-slider data-autoplay="<?= $slider['autoplay'] ? '1' : '0' ?>">
    <?php foreach ($banners as $i => $banner):
        $effect = $banner['efeito'] ?? 'padrao';
        if (!in_array($effect, ['fade', 'slide', 'zoom', 'none'], true)) $effect = $slider['effect'];
        $bid = (int)$banner['id'];
        $bg = !empty($banner['imagem_fundo']) ? "background-image:linear-gradient(90deg,rgba(8,31,49,.93) 0%,rgba(20,66,76,.76) 55%,rgba(110,24,59,.58) 100%),url('" . e($banner['imagem_fundo']) . "')" : '';
        $btnLink = $banner['link_botao'] ?: 'cadastro.php';
    ?>
    <article class="banner-slide banner-effect-<?= e($effect) ?><?= $i === 0 ? ' is-active' : '' ?>" data-banner-slide data-duration="<?= max(2, (int)($banner['duracao_segundos'] ?? 5)) ?>" aria-roledescription="slide" aria-label="<?= $i + 1 ?> / <?= count($banners) ?>"<?= $i === 0 ? '' : ' aria-hidden="true"' ?> style="<?= $bg ?>">
      <div class="container hero-inner">
        <div class="hero-copy">
          <p class="eyebrow"><?= e(t('hero.eyebrow')) ?></p>
          <?= $i === 0 ? '<h1>' : '<h2 class="h1-like">' ?><?= nl2br(e(tc("banner.$bid.titulo", $banner['titulo']))) ?><?= $i === 0 ? '</h1>' : '</h2>' ?>
          <?php if (!empty($banner['subtitulo'])): ?><p class="hero-sub"><?= nl2br(e(tc("banner.$bid.subtitulo", $banner['subtitulo']))) ?></p><?php endif; ?>
          <div class="hero-actions">
            <?php if (!empty($banner['texto_botao'])): ?><a class="btn btn-primary" href="<?= e($btnLink) ?>"<?= basename(strtok($btnLink, '?#')) === 'cadastro.php' ? ' data-signup-open' : '' ?>><?= e(tc("banner.$bid.texto_botao", $banner['texto_botao'])) ?></a><?php endif; ?>
            <a class="btn btn-ghost" href="#busca"><?= svg_icon('busca', 'icon-sm') ?> <?= e(t('hero.explore')) ?></a>
          </div>
        </div>
      </div>
    </article>
    <?php endforeach; ?>
  </div>

  <!-- Camada fixa sobre os slides: benefícios (esq.) e anúncios em destaque (dir.) -->
  <div class="hero-overlay container">
    <?php if ($benefits): ?>
    <ul class="hero-benefit-list" aria-label="Benefícios">
      <?php foreach ($benefits as $b): ?>
      <li>
        <span class="hero-benefit-icon"><?= svg_icon($b['icone'], 'icon-benefit') ?></span>
        <span><b><?= e(tc('beneficio.' . $b['id'] . '.titulo', $b['titulo'])) ?></b><?php if (!empty($b['subtitulo'])): ?><small><?= e(tc('beneficio.' . $b['id'] . '.subtitulo', $b['subtitulo'])) ?></small><?php endif; ?></span>
      </li>
      <?php endforeach; ?>
    </ul>
    <?php endif; ?>
  </div>

  <!-- Anúncios em destaque: encostados na coluna de acessibilidade -->
  <?php if ($hero_ads): ?>
  <div class="hero-ads" aria-label="<?= e(t('home.ads_title')) ?>">
    <?php foreach ($hero_ads as $featured): $thumb = $featured['logo'] ?: ad_cover($featured); ?>
    <a class="hero-ad-card" href="<?= e(ad_profile_url($featured)) ?>">
      <span class="hero-ad-thumb"><?php if ($thumb): ?><img src="<?= e($thumb) ?>" alt=""><?php else: ?><?= e(initials($featured['titulo'])) ?><?php endif; ?></span>
      <span class="hero-ad-info">
        <b><?= e($featured['titulo']) ?></b>
        <small class="hero-ad-cat"><?= e($featured['categoria'] ?? '') ?></small>
        <small><span class="star">★</span> <?= e(number_format((float)str_replace(',', '.', (string)($featured['avaliacao'] ?? 0)), 1, ',', '')) ?></small>
        <?php if (!empty($featured['localizacao'])): ?><small><?= svg_icon('mapa', 'icon-xs icon-pin') ?> <?= e($featured['localizacao']) ?></small><?php endif; ?>
      </span>
    </a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php if ($multi && $slider['arrows']): ?>
  <button class="hero-arrow hero-arrow-prev" type="button" data-slide-prev aria-label="<?= e(t('hero.prev')) ?>"><?= svg_icon('seta-esq', 'icon-arrow') ?></button>
  <button class="hero-arrow hero-arrow-next" type="button" data-slide-next aria-label="<?= e(t('hero.next')) ?>"><?= svg_icon('seta-dir', 'icon-arrow') ?></button>
  <?php endif; ?>
  <?php if ($multi && ($slider['dots'] || $slider['autoplay'])): ?>
  <div class="hero-dots" data-slider-controls>
    <?php if ($slider['dots']): foreach ($banners as $i => $banner): ?>
    <button type="button" data-dot class="<?= $i === 0 ? 'is-active' : '' ?>" aria-label="<?= e(t('hero.goto', ['n' => $i + 1])) ?>"></button>
    <?php endforeach; endif; ?>
    <?php if ($slider['autoplay']): ?><button class="slider-control" type="button" data-slide-play aria-label="<?= e(t('hero.pause')) ?>">Ⅱ</button><?php endif; ?>
  </div>
  <?php endif; ?>

  <?php $a11yVariant = 'banner'; include __DIR__ . '/includes/a11y_bar.php'; ?>
</section>

<?php include __DIR__ . '/includes/search_form.php'; ?>

<section class="section container" id="servicos">
  <div class="section-heading">
    <div><h2><?= e(t('home.categories_title')) ?></h2><p><?= e(t('home.categories_text')) ?></p></div>
    <a href="categoria.php?todas=1"><?= e(t('home.categories_all')) ?></a>
  </div>
  <div class="category-grid">
    <?php foreach ($cats as $cat): ?>
    <a class="category-card" href="categoria.php?categoria=<?= (int)$cat['id'] ?>"><span class="category-icon"><?= e($cat['icone'] ?? '✦') ?></span><b><?= e($cat['nome']) ?></b><small><?= e(t('home.see_more')) ?></small></a>
    <?php endforeach; ?>
  </div>
</section>

<section class="section container">
  <div class="section-heading">
    <div><h2><?= e(t('home.ads_title')) ?></h2><p><?= e(t('home.ads_text')) ?></p></div>
    <a href="categoria.php"><?= e(t('home.ads_all')) ?></a>
  </div>
  <div class="ads-grid">
    <?php foreach (array_slice($featured_ads, 0, 4) as $ad) include __DIR__ . '/includes/ad_card.php'; ?>
  </div>
</section>

<?php if ((int)($promo['ativo'] ?? 1) === 1): ?>
<section class="cta container reveal" id="amplie">
  <?php if (!empty($promo['imagem'])): ?>
    <img class="cta-image" src="<?= e($promo['imagem']) ?>" alt="">
  <?php else: ?>
    <div class="phone-mockup" aria-hidden="true">Banco<br><strong>Negócios</strong></div>
  <?php endif; ?>
  <div class="cta-copy">
    <h2><?= e(tc('promo.titulo', $promo['titulo'])) ?></h2>
    <?php if (!empty($promo['texto'])): ?><p><?= nl2br(e(tc('promo.texto', $promo['texto']))) ?></p><?php endif; ?>
  </div>
  <?php if ($promoBenefits): ?><ul><?php foreach ($promoBenefits as $benefit): ?><li><?= e($benefit) ?></li><?php endforeach; ?></ul><?php endif; ?>
  <a class="btn btn-primary" href="<?= e($promoLink) ?>"<?= $promoIsSignup ? ' data-signup-open' : '' ?>><?= e(tc('promo.texto_botao', ($promo['texto_botao'] ?? '') ?: t('cta.button'))) ?></a>
</section>
<?php endif; ?>

<?php if ($news): ?>
<section class="section container home-news">
  <div class="section-heading">
    <div><h2><?= e(t('home.news_title')) ?></h2></div>
    <a href="noticias.php"><?= e(t('home.news_all')) ?></a>
  </div>
  <div class="news-grid">
    <?php foreach ($news as $n) include __DIR__ . '/includes/news_card.php'; ?>
  </div>
</section>
<?php endif; ?>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
