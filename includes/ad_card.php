<?php
/* Card de anúncio reutilizado na home e na listagem. Espera $ad. */
$waText = rawurlencode(t('card.whatsapp_msg'));
$wa = preg_replace('/\D+/', '', (string)($ad['whatsapp'] ?? ''));
?>
<article class="ad-card">
  <a class="ad-cover-wrap" href="<?= e(ad_profile_url($ad)) ?>" tabindex="-1" aria-hidden="true">
    <?php if (ad_cover($ad)): ?><img src="<?= e(ad_cover($ad)) ?>" alt="" loading="lazy"><?php else: ?><span class="ad-cover-empty"></span><?php endif; ?>
    <span class="ad-avatar"><?php if (!empty($ad['logo'])): ?><img src="<?= e($ad['logo']) ?>" alt=""><?php else: ?><span><?= e(initials($ad['titulo'])) ?></span><?php endif; ?></span>
  </a>
  <div class="ad-content">
    <h3><a href="<?= e(ad_profile_url($ad)) ?>"><?= e($ad['titulo']) ?></a></h3>
    <strong><?= e($ad['categoria'] ?? '') ?></strong>
    <p><?= e(mb_strimwidth((string)($ad['descricao'] ?? ''), 0, 150, '…')) ?></p>
    <?php if (!empty($ad['localizacao'])): ?><span><?= svg_icon('mapa', 'icon-xs') ?> <?= e($ad['localizacao']) ?></span><?php endif; ?>
    <span class="rating">★ <?= e(number_format((float)str_replace(',', '.', (string)($ad['avaliacao'] ?? 0)), 1, ',', '')) ?></span>
    <div class="ad-actions">
      <a class="btn btn-outline" href="<?= e(ad_profile_url($ad)) ?>"><?= e(t('card.profile')) ?></a>
      <?php if ($wa): ?><a class="btn btn-primary" target="_blank" rel="noopener" href="https://wa.me/<?= e($wa) ?>?text=<?= $waText ?>"><?= svg_icon('whatsapp', 'icon-xs') ?> <?= e(t('card.contact')) ?></a><?php endif; ?>
    </div>
  </div>
</article>
