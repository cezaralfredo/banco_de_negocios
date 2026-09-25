<?php
require_once __DIR__ . '/functions.php';
start_session();
$pdo = $pdo ?? null;
ensure_schema($pdo);
$lang = current_lang();

$siteName = setting($pdo, 'site_name', 'Banco de Negócios');
$siteTopbar = tc('topbar_text', setting($pdo, 'topbar_text', 'Bem-vindo ao Banco de Negócios!'));
$siteTagline = tc('site_tagline', setting($pdo, 'site_tagline', 'Conectando oportunidades'));
$siteLogo = setting($pdo, 'site_logo', '');
$siteCompactLogo = setting($pdo, 'site_logo_compacto', '');
$siteFavicon = setting($pdo, 'site_favicon', '');
$sitePages = page_settings($pdo);
$menuItems = get_menu_items($pdo);
$theme = theme_settings($pdo);
$navTheme = setting($pdo, 'nav_theme', 'light');
$languages = get_languages($pdo);
$currentLanguage = $languages[0];
foreach ($languages as $l) if ($l['codigo'] === $lang) $currentLanguage = $l;
$menuParents = array_values(array_filter($menuItems, fn($i) => empty($i['parent_id'])));
$isHome = basename($_SERVER['SCRIPT_NAME'] ?? '') === 'index.php';
?><!doctype html>
<html lang="<?= e(html_lang()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($page_title ?? $siteName) ?></title>
<?php if ($siteFavicon): ?><link rel="icon" href="<?= e($siteFavicon) ?>"><?php endif; ?>
<meta name="description" content="<?= e($page_description ?? ('Diretório comercial ' . $siteName)) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css?v=6">
<style>:root{--wine:<?= e($theme['primary']) ?>;--wine-dark:<?= e($theme['dark']) ?>;--pink:<?= e($theme['accent']) ?>;--site-font:'<?= e($theme['font']) ?>',Arial,sans-serif}body{font-family:var(--site-font)}<?= $theme['layout'] === 'boxed' ? '.container{max-width:1080px}' : '' ?></style>
<script>
/* Aplica as preferências de acessibilidade antes da renderização (evita "piscar") */
try{var a=JSON.parse(localStorage.getItem('bn_a11y')||sessionStorage.getItem('bn_a11y')||'{}');var c=document.documentElement.classList;if(a.contrast)c.add('a11y-contrast');if(a.links)c.add('a11y-links');if(a.dyslexia)c.add('a11y-dyslexia');}catch(e){}
</script>
</head>
<body class="<?= $isHome ? 'is-home' : 'is-inner' ?>" data-lang="<?= e(html_lang()) ?>">
<a class="skip-link" href="#conteudo"><?= e(t('nav.skip')) ?></a>

<div class="topbar">
  <div class="container topbar-inner">
    <span class="topbar-text"><?= e($siteTopbar) ?></span>
    <div class="top-actions">
      <form class="quick-search" action="categoria.php" role="search">
        <input name="q" aria-label="<?= e(t('top.search_label')) ?>" placeholder="<?= e(t('top.search_placeholder')) ?>">
        <button aria-label="<?= e(t('search.button')) ?>"><?= svg_icon('busca', 'icon-sm') ?></button>
      </form>
      <a href="cadastro.php" data-signup-open><?= e(t('top.create_account')) ?></a>
      <a href="admin/index.php"><?= e(t('top.login')) ?></a>
      <?php if (count($languages) > 1): ?>
      <div class="dropdown lang-control">
        <button class="lang-toggle dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="<?= e(t('top.language')) ?>: <?= e($currentLanguage['nome']) ?>">
          <span aria-hidden="true"><?= e($currentLanguage['bandeira'] ?: '🌐') ?></span> <?= e(strtoupper($currentLanguage['codigo'])) ?>
        </button>
        <ul class="dropdown-menu dropdown-menu-end">
          <?php foreach ($languages as $l): ?>
          <li><a class="dropdown-item<?= $l['codigo'] === $lang ? ' active' : '' ?>" href="<?= e(lang_url($l['codigo'])) ?>" hreflang="<?= e($l['codigo']) ?>" lang="<?= e($l['codigo']) ?>"><span aria-hidden="true"><?= e($l['bandeira']) ?></span> <?= e($l['nome']) ?></a></li>
          <?php endforeach; ?>
        </ul>
      </div>
      <?php endif; ?>
      <a class="panel-link" href="admin/index.php"><?= e(t('top.panel')) ?></a>
      <button class="access-trigger" type="button" data-a11y-toggle aria-expanded="false" aria-controls="a11yFloating" aria-label="<?= e(t('top.accessibility')) ?>" title="<?= e(t('top.accessibility')) ?>">
        <?= svg_icon('acessibilidade', 'icon-a11y') ?>
      </button>
    </div>
  </div>
</div>

<header class="site-header">
  <div class="container header-inner">
    <a class="brand" href="index.php">
      <?php if ($siteLogo): ?>
        <picture><source media="(max-width:520px)" srcset="<?= e($siteCompactLogo ?: $siteLogo) ?>"><img src="<?= e($siteLogo) ?>" alt="<?= e($siteName) ?>" class="site-logo"></picture>
      <?php else: ?>
        <strong><?= e(mb_strtoupper($siteName)) ?></strong>
      <?php endif; ?>
      <small><?= e($siteTagline) ?></small>
    </a>
    <nav class="nav-theme-<?= e($navTheme) ?>" aria-label="Navegação principal">
      <a class="<?= $isHome ? 'active' : '' ?>" href="index.php"<?= $isHome ? ' aria-current="page"' : '' ?>><?= e(t('nav.home')) ?></a>
      <?php foreach ($menuParents as $item):
        $active = is_current_url($item['url']);
        if (!$active && $item['has_children']) {
            foreach ($menuItems as $c) if ((int)$c['parent_id'] === (int)$item['id'] && is_current_url($c['url'])) $active = true;
        }
        $menuTitle = tc('menu.' . $item['id'], $item['titulo']);
      ?>
      <div class="nav-item<?= $item['has_children'] ? ' has-children dropdown' : '' ?>">
        <a href="<?= e($item['url']) ?>" class="<?= $active ? 'active' : '' ?><?= $item['has_children'] ? ' dropdown-toggle' : '' ?>"<?= $item['has_children'] ? ' data-bs-toggle="dropdown" aria-expanded="false"' : '' ?><?= $active ? ' aria-current="page"' : '' ?>><?= e($menuTitle) ?></a>
        <?php if ($item['has_children']): ?>
        <div class="submenu dropdown-menu">
          <?php foreach ($menuItems as $child): if ((int)$child['parent_id'] === (int)$item['id']): ?>
          <a class="dropdown-item" href="<?= e($child['url']) ?>"><?= e(tc('menu.' . $child['id'], $child['titulo'])) ?></a>
          <?php endif; endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
      <?php if (!$menuItems): ?>
        <a href="noticias.php"><?= e(t('news.title')) ?></a>
        <?php foreach ($sitePages as $page): ?><a href="pagina.php?slug=<?= urlencode($page['slug']) ?>"><?= e($page['titulo']) ?></a><?php endforeach; ?>
      <?php endif; ?>
    </nav>
    <button class="mobile-menu" aria-label="<?= e(t('nav.menu_open')) ?>" aria-expanded="false">☰</button>
  </div>
</header>

<?php $a11yVariant = 'floating'; include __DIR__ . '/a11y_bar.php'; ?>
