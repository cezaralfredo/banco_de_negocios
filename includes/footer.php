<?php
$pdo = $pdo ?? null;
$footerName = setting($pdo, 'site_name', 'Banco de Negócios');
$footerLogo = setting($pdo, 'site_logo', '');
$footerShowLogo = setting($pdo, 'footer_show_logo', '1') === '1';
$footerSlogan = tc('footer_slogan', setting($pdo, 'footer_slogan', 'Conectando pessoas, negócios e oportunidades.'));
$footerEmail = setting($pdo, 'footer_email', 'contato@bancodenegocios.com.br');
$footerPhone = setting($pdo, 'footer_phone', '');
$footerAddress = setting($pdo, 'footer_address', 'Fortaleza - CE');
$footerCtaText = tc('footer_cta_text', setting($pdo, 'footer_cta_text', '')) ?: t('footer.cta');
$footerCtaLink = setting($pdo, 'footer_cta_link', 'cadastro.php');
$footerBottom = tc('footer_bottom_text', setting($pdo, 'footer_bottom_text', 'Conteúdo institucional gerenciado pelo administrador'));
$footerCopyright = tc('footer_copyright', setting($pdo, 'footer_copyright', ''));
$footerTitles = [
    'links' => tc('footer_title_links', setting($pdo, 'footer_title_links', '')) ?: t('footer.links'),
    'social' => tc('footer_title_social', setting($pdo, 'footer_title_social', '')) ?: t('footer.social'),
    'contact' => tc('footer_title_contact', setting($pdo, 'footer_title_contact', '')) ?: t('footer.contact'),
];
// Links institucionais: uma linha por link no formato "Título|URL". Vazio = páginas publicadas.
$footerLinks = [];
foreach (preg_split('/\R/', setting($pdo, 'footer_links', '')) as $i => $line) {
    $parts = array_map('trim', explode('|', $line, 2));
    if (($parts[0] ?? '') !== '' && ($parts[1] ?? '') !== '') $footerLinks[] = ['titulo' => tc('footer_link.' . $i, $parts[0]), 'url' => $parts[1]];
}
if (!$footerLinks) {
    $footerLinks[] = ['titulo' => t('nav.home'), 'url' => 'index.php'];
    $footerLinks[] = ['titulo' => t('news.title'), 'url' => 'noticias.php'];
    foreach (page_settings($pdo) as $page) $footerLinks[] = ['titulo' => $page['titulo'], 'url' => 'pagina.php?slug=' . urlencode($page['slug'])];
}
$socials = get_social_links($pdo);
$cookieText = tc('cookie_text', setting($pdo, 'cookie_text', '')) ?: t('cookie.text');
$cookiePolicy = setting($pdo, 'cookie_policy_url', 'pagina.php?slug=politica-de-privacidade');
$cookieVersion = setting($pdo, 'cookie_version', '1');
?>
<footer class="site-footer">
  <div class="container footer-grid">
    <div>
      <?php if ($footerLogo && $footerShowLogo): ?>
        <img class="site-logo footer-logo" src="<?= e($footerLogo) ?>" alt="<?= e($footerName) ?>">
      <?php else: ?>
        <a class="brand brand-light" href="index.php"><strong><?= e(mb_strtoupper($footerName)) ?></strong><small><?= e(tc('site_tagline', setting($pdo, 'site_tagline', 'Conectando oportunidades'))) ?></small></a>
      <?php endif; ?>
      <p><?= nl2br(e($footerSlogan)) ?></p>
    </div>
    <div>
      <h4><?= e($footerTitles['links']) ?></h4>
      <?php foreach ($footerLinks as $link): ?><a href="<?= e($link['url']) ?>"><?= e($link['titulo']) ?></a><?php endforeach; ?>
    </div>
    <div>
      <h4><?= e($footerTitles['social']) ?></h4>
      <div class="socials">
        <?php foreach ($socials as $s): ?>
        <a href="<?= e($s['url']) ?>" target="_blank" rel="noopener" aria-label="<?= e($s['nome']) ?>" title="<?= e($s['nome']) ?>"><?= svg_icon($s['rede'], 'icon-social') ?></a>
        <?php endforeach; ?>
      </div>
    </div>
    <div>
      <h4><?= e($footerTitles['contact']) ?></h4>
      <?php if ($footerEmail): ?><p class="footer-contact"><?= svg_icon('email', 'icon-sm') ?> <a href="mailto:<?= e($footerEmail) ?>"><?= e($footerEmail) ?></a></p><?php endif; ?>
      <?php if ($footerPhone): ?><p class="footer-contact"><?= svg_icon('telefone', 'icon-sm') ?> <a href="tel:<?= e(preg_replace('/[^\d+]/', '', $footerPhone)) ?>"><?= e($footerPhone) ?></a></p><?php endif; ?>
      <?php if ($footerAddress): ?><p class="footer-contact"><?= svg_icon('mapa', 'icon-sm') ?> <span><?= nl2br(e($footerAddress)) ?></span></p><?php endif; ?>
      <?php if ($footerCtaLink): ?><a class="footer-cta" href="<?= e($footerCtaLink) ?>"<?= basename(strtok($footerCtaLink, '?')) === 'cadastro.php' ? ' data-signup-open' : '' ?>><?= e($footerCtaText) ?></a><?php endif; ?>
    </div>
  </div>
  <div class="container footer-bottom">
    <span><?= e($footerBottom) ?> · <button type="button" class="link-button" data-cookie-open><?= e(t('footer.cookie_prefs')) ?></button></span>
    <span><?= $footerCopyright !== '' ? e(str_replace('{ano}', date('Y'), $footerCopyright)) : '© ' . date('Y') . ' ' . e($footerName) . '. ' . e(t('footer.rights')) ?></span>
  </div>
</footer>

<?php include __DIR__ . '/signup_modal.php'; ?>

<!-- LGPD: banner de consentimento (sempre no final da página) -->
<section class="cookie-banner" data-cookie-banner data-version="<?= e($cookieVersion) ?>" role="region" aria-label="<?= e(t('cookie.title')) ?>" hidden>
  <div class="cookie-copy">
    <span class="cookie-icon"><?= svg_icon('cookie', 'icon-cookie') ?></span>
    <div>
      <strong><?= e(t('cookie.title')) ?></strong>
      <p><?= e($cookieText) ?> <a href="<?= e($cookiePolicy) ?>"><?= e(t('cookie.policy')) ?></a></p>
    </div>
  </div>
  <div class="cookie-actions">
    <button class="btn btn-outline" type="button" data-cookie-action="customize"><?= e(t('cookie.customize')) ?></button>
    <button class="btn btn-outline" type="button" data-cookie-action="reject"><?= e(t('cookie.reject')) ?></button>
    <button class="btn btn-primary" type="button" data-cookie-action="accept"><?= e(t('cookie.accept')) ?></button>
  </div>
</section>

<div class="modal fade" id="cookieModal" tabindex="-1" aria-labelledby="cookieModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content cookie-modal">
      <div class="modal-header">
        <h2 class="modal-title fs-5" id="cookieModalLabel"><?= e(t('cookie.modal_title')) ?></h2>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= e(t('signup.close')) ?>"></button>
      </div>
      <div class="modal-body">
        <?php foreach (['necessary' => true, 'preferences' => false, 'statistics' => false, 'marketing' => false] as $cat => $locked): ?>
        <div class="cookie-category">
          <div>
            <strong><?= e(t('cookie.' . $cat)) ?></strong>
            <p><?= e(t('cookie.' . $cat . '_text')) ?></p>
          </div>
          <?php if ($locked): ?>
            <span class="badge-always"><?= e(t('cookie.always_on')) ?></span>
          <?php else: ?>
            <div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" data-cookie-cat="<?= e($cat) ?>" aria-label="<?= e(t('cookie.' . $cat)) ?>"></div>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline" type="button" data-cookie-action="reject"><?= e(t('cookie.reject')) ?></button>
        <button class="btn btn-primary" type="button" data-cookie-action="save"><?= e(t('cookie.save')) ?></button>
      </div>
    </div>
  </div>
</div>

<div class="sr-only" aria-live="polite" data-a11y-live></div>
<script>window.BN_I18N=<?= json_encode([
    'readerOn' => t('a11y.reader_on'), 'readerOff' => t('a11y.reader_off'),
    'pause' => t('hero.pause'), 'play' => t('hero.play'),
    'err_confirm' => t('signup.err_confirm'),
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/main.js?v=6"></script>
<script src="assets/js/acessibilidade.js?v=6"></script>
</body>
</html>
