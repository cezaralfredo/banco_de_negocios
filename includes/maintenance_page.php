<?php
/* Página exibida durante o modo de manutenção (personalizável em Admin › Manutenção). */
$mSite = setting($pdo, 'site_name', 'Site');
$mTitle = tc('manutencao_titulo', setting($pdo, 'manutencao_titulo', 'Estamos em manutenção'));
$mMsg = tc('manutencao_mensagem', setting($pdo, 'manutencao_mensagem', 'Estamos realizando melhorias no site. Voltamos em breve!'));
$mImg = setting($pdo, 'manutencao_imagem', '');
$mLogo = setting($pdo, 'site_logo', '');
$mEnd = setting($pdo, 'manutencao_fim', '');
$mEmail = setting($pdo, 'manutencao_contato', '') ?: setting($pdo, 'footer_email', '');
$mShowCountdown = setting($pdo, 'manutencao_contagem', '1') === '1' && $mEnd !== '' && strtotime($mEnd) > time();
$mFonts = theme_fonts_link();
$mBase = site_base_url(); // caminhos absolutos (funciona também na pré-visualização dentro de /admin)
?><!doctype html>
<html lang="<?= e(html_lang()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?= e($mTitle) ?> | <?= e($mSite) ?></title>
<?php if ($f = setting($pdo, 'site_favicon', '')): ?><link rel="icon" href="<?= e($mBase . $f) ?>"><?php endif; ?>
<?php if ($mFonts): ?><link href="<?= e($mFonts) ?>" rel="stylesheet"><?php endif; ?>
<style>
<?= theme_css_vars() ?>
*{box-sizing:border-box}
body{margin:0;min-height:100vh;display:grid;place-items:center;font-family:var(--site-font,Arial,sans-serif);color:#fff;padding:24px;
  background:radial-gradient(circle at 80% 10%,color-mix(in srgb,var(--pink,#ef5d98) 35%,transparent),transparent 35%),linear-gradient(120deg,var(--hero-from,#081f31),var(--hero-to,#6e183b))}
.box{width:min(640px,100%);text-align:center}
.logo{max-height:64px;max-width:220px;margin-bottom:28px;background:#fff;padding:10px 16px;border-radius:12px}
.site{font-family:var(--heading-font,inherit);font-weight:800;font-size:22px;letter-spacing:.5px;margin-bottom:28px}
.illu{max-width:100%;max-height:260px;border-radius:16px;margin-bottom:24px}
.icon{width:84px;height:84px;margin:0 auto 22px;border-radius:50%;display:grid;place-items:center;background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.3)}
h1{font-family:var(--heading-font,inherit);font-size:clamp(28px,5vw,42px);margin:0 0 12px;line-height:1.15}
p{font-size:18px;line-height:1.6;opacity:.92;margin:0 auto 22px;max-width:520px}
.count{display:flex;justify-content:center;gap:12px;margin:26px 0}
.count div{background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.25);border-radius:12px;padding:12px 6px;min-width:76px}
.count b{display:block;font-size:30px}.count small{font-size:12px;opacity:.8;text-transform:uppercase;letter-spacing:1px}
a{color:#fff;font-weight:700}
.preview{position:fixed;top:0;left:0;right:0;background:#ffb703;color:#1d1d1d;font:600 13px Arial;padding:8px;text-align:center}
.preview a{color:#1d1d1d}
</style>
</head>
<body>
<?php if (!empty($GLOBALS['__bn_maintenance_preview'])): ?><div class="preview">Pré-visualização da página de manutenção · <a href="<?= e($mBase) ?>admin/manutencao.php">voltar ao painel</a></div><?php endif; ?>
<main class="box" role="main">
  <?php if ($mLogo): ?><img class="logo" src="<?= e($mBase . $mLogo) ?>" alt="<?= e($mSite) ?>"><?php else: ?><div class="site"><?= e(mb_strtoupper($mSite)) ?></div><?php endif; ?>
  <?php if ($mImg): ?><img class="illu" src="<?= e($mBase . $mImg) ?>" alt=""><?php else: ?>
  <div class="icon" aria-hidden="true"><svg width="42" height="42" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a4 4 0 0 0-5.4 5.4L3 18l3 3 6.3-6.3a4 4 0 0 0 5.4-5.4l-2.5 2.5-2.4-.6-.6-2.4z"/></svg></div>
  <?php endif; ?>
  <h1><?= e($mTitle) ?></h1>
  <p><?= nl2br(e($mMsg)) ?></p>
  <?php if ($mShowCountdown): ?>
  <div class="count" data-end="<?= e(date('c', strtotime($mEnd))) ?>" aria-live="polite">
    <div><b data-d>0</b><small>dias</small></div><div><b data-h>0</b><small>horas</small></div><div><b data-m>0</b><small>min</small></div><div><b data-s>0</b><small>seg</small></div>
  </div>
  <p style="font-size:14px">Previsão de retorno: <?= e(date('d/m/Y \à\s H:i', strtotime($mEnd))) ?></p>
  <?php endif; ?>
  <?php if ($mEmail): ?><p style="font-size:15px">Precisa falar conosco? <a href="mailto:<?= e($mEmail) ?>"><?= e($mEmail) ?></a></p><?php endif; ?>
</main>
<?php if ($mShowCountdown): ?>
<script>
(function(){var el=document.querySelector('[data-end]'),end=new Date(el.dataset.end).getTime();
function tick(){var d=Math.max(0,end-Date.now())/1000;el.querySelector('[data-d]').textContent=Math.floor(d/86400);el.querySelector('[data-h]').textContent=Math.floor(d%86400/3600);el.querySelector('[data-m]').textContent=Math.floor(d%3600/60);el.querySelector('[data-s]').textContent=Math.floor(d%60);if(d<=0){setTimeout(function(){location.reload()},3000);return}setTimeout(tick,1000)}tick()})();
</script>
<?php endif; ?>
</body>
</html>
