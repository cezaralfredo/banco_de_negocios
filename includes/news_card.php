<?php /* Card de notícia. Espera $n. */ ?>
<article class="news-card">
  <a class="news-card-cover" href="noticia.php?slug=<?= urlencode($n['slug']) ?>" tabindex="-1" aria-hidden="true">
    <?php if (!empty($n['imagem'])): ?><img src="<?= e($n['imagem']) ?>" alt="" loading="lazy"><?php else: ?><span class="news-card-placeholder"><?= svg_icon('megafone', 'icon-benefit') ?></span><?php endif; ?>
  </a>
  <div class="news-card-body">
    <time datetime="<?= e(date('Y-m-d', strtotime($n['publicado_em']))) ?>"><?= e(date('d/m/Y', strtotime($n['publicado_em']))) ?></time>
    <h3><a href="noticia.php?slug=<?= urlencode($n['slug']) ?>"><?= e($n['titulo']) ?></a></h3>
    <?php if (!empty($n['resumo'])): ?><p><?= e($n['resumo']) ?></p><?php endif; ?>
    <a class="news-card-more" href="noticia.php?slug=<?= urlencode($n['slug']) ?>"><?= e(t('news.read_more')) ?></a>
  </div>
</article>
