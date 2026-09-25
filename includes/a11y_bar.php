<?php
/*
 * Barra de acessibilidade.
 * $a11yVariant = 'banner'   → coluna fixa à direita do banner da home (sempre visível)
 * $a11yVariant = 'floating' → painel flutuante aberto pelo ícone da barra superior
 */
$a11yVariant = $a11yVariant ?? 'floating';
$a11yItems = [
    ['font-up', '<span class="a11y-txt">A<sup>+</sup></span>', t('a11y.font_up'), false],
    ['font-down', '<span class="a11y-txt">A<sup>−</sup></span>', t('a11y.font_down'), false],
    ['contrast', svg_icon('contraste', 'icon-a11y-item'), t('a11y.contrast'), true],
    ['links', svg_icon('link', 'icon-a11y-item'), t('a11y.links'), true],
    ['reader', svg_icon('audio', 'icon-a11y-item'), t('a11y.reader'), true],
    ['dyslexia', '<span class="a11y-txt a11y-dys">Dy</span>', t('a11y.dyslexia'), true],
    ['reset', svg_icon('reset', 'icon-a11y-item'), t('a11y.reset'), false],
];
?>
<?php if ($a11yVariant === 'banner'): ?>
<aside class="a11y-column" aria-label="<?= e(t('a11y.title')) ?>">
  <span class="a11y-column-head" title="<?= e(t('a11y.title')) ?>"><?= svg_icon('acessibilidade', 'icon-a11y-item') ?></span>
  <?php foreach ($a11yItems as [$action, $icon, $label, $toggle]): ?>
  <button type="button" class="a11y-btn" data-a11y="<?= e($action) ?>"<?= $toggle ? ' aria-pressed="false"' : '' ?> aria-label="<?= e($label) ?>" data-tip="<?= e($label) ?>"><?= $icon ?></button>
  <?php endforeach; ?>
</aside>
<?php else: ?>
<div class="a11y-floating" id="a11yFloating" data-a11y-panel hidden>
  <div class="a11y-floating-head"><?= svg_icon('acessibilidade', 'icon-a11y-item') ?> <strong><?= e(t('a11y.title')) ?></strong>
    <button type="button" class="a11y-close" data-a11y-close aria-label="<?= e(t('signup.close')) ?>">×</button>
  </div>
  <?php foreach ($a11yItems as [$action, $icon, $label, $toggle]): ?>
  <button type="button" class="a11y-row" data-a11y="<?= e($action) ?>"<?= $toggle ? ' aria-pressed="false"' : '' ?>><span class="a11y-row-icon"><?= $icon ?></span><?= e($label) ?></button>
  <?php endforeach; ?>
</div>
<?php endif; ?>
