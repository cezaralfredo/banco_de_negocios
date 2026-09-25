<?php
/* Barra lateral padrão das páginas internas (mesmo visual de "Outros anúncios" do perfil).
   Espera: $sidebarTitle, $sidebarNews (lista de notícias), opcional $sidebarPages. */
?>
<aside class="profile-sidebar">
  <?php if (!empty($sidebarPages)): ?>
  <h2><?= e(t('page.related')) ?></h2>
  <nav class="sidebar-links">
    <?php foreach ($sidebarPages as $sp): ?>
    <a href="pagina.php?slug=<?= urlencode($sp['slug']) ?>"<?= !empty($sp['current']) ? ' class="active" aria-current="page"' : '' ?>><?= e($sp['titulo']) ?></a>
    <?php endforeach; ?>
  </nav>
  <?php endif; ?>
  <?php if (!empty($sidebarNews)): ?>
  <h2 class="<?= !empty($sidebarPages) ? 'mt-4' : '' ?>"><?= e($sidebarTitle ?? t('page.latest_news')) ?></h2>
  <?php foreach ($sidebarNews as $sn): ?>
  <a class="sidebar-ad" href="noticia.php?slug=<?= urlencode($sn['slug']) ?>">
    <?php if (!empty($sn['imagem'])): ?><img src="<?= e($sn['imagem']) ?>" alt=""><?php else: ?><span class="sidebar-thumb"><?= svg_icon('megafone', 'icon-sm') ?></span><?php endif; ?>
    <span><strong><?= e($sn['titulo']) ?></strong><small><?= e(date('d/m/Y', strtotime($sn['publicado_em']))) ?></small></span>
  </a>
  <?php endforeach; ?>
  <?php endif; ?>
</aside>
