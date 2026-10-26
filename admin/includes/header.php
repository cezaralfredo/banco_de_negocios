<?php
require_once __DIR__ . '/init.php';
$siteNameAdmin = setting($pdo, 'site_name', 'CMS');
$adminLogo = setting($pdo, 'site_logo', '');
$help = $admin_help ?? preg_replace('/\.php$/', '', $current_admin);
$flashes = flash_take();
?><!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?= e($admin_title ?? 'Painel') ?> | <?= e($siteNameAdmin) ?></title>
<?php if ($fav = setting($pdo, 'site_favicon', '')): ?><link rel="icon" href="../<?= e($fav) ?>"><?php endif; ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Hanken+Grotesk:wght@400;500;600;700&family=Poppins:wght@500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset('assets/css/admin.css', '../')) ?>">
<style>:root{--wine:<?= e(theme_var('cor_primaria', '#9f1549')) ?>}</style>
<?php do_action('admin_head'); ?>
</head>
<body data-csrf="<?= e(csrf_token()) ?>">
<a class="visually-hidden-focusable admin-skip" href="#admin-main">Pular para o conteúdo</a>
<div class="admin-shell">
  <aside class="admin-side" id="adminSide" aria-label="Menu do painel">
    <a href="dashboard.php" class="brand">
      <?php if ($adminLogo): ?><img src="../<?= e($adminLogo) ?>" alt="<?= e($siteNameAdmin) ?>" class="admin-logo"><?php else: ?><strong><?= e(mb_strtoupper($siteNameAdmin)) ?></strong><?php endif; ?>
      <small><?= e(BN_TENANT_DATA['nome'] ?? '') !== e($siteNameAdmin) && !is_primary_tenant() ? 'Cliente: ' . e(BN_TENANT_DATA['nome'] ?? BN_TENANT) : 'Painel administrativo' ?></small>
    </a>
    <?php
    // Menu em accordion: cada seção abre ao clicar e fecha as demais. A seção da página atual começa aberta.
    $sectionIcons = ['Conteúdo' => 'conteudo', 'Aparência' => 'aparencia', 'Sistema' => 'sistema', 'Plugins' => 'plugins', 'Ajuda' => 'ajuda'];
    $openSection = null;
    foreach ($adminNav as $section => $links) if (isset($links[$navKey])) $openSection = $section;
    $openSection = $openSection ?? 'Conteúdo';
    ?>
    <div class="side-accordion" data-side-accordion>
    <?php foreach ($adminNav as $section => $links):
        $visible = array_filter($links, fn($l) => can($l[2]));
        if (!$visible) continue;
        $sid = 'sec-' . slugify($section);
        $isOpen = $section === $openSection; ?>
      <div class="side-group<?= $isOpen ? ' is-open' : '' ?>">
        <button type="button" class="side-group-toggle" id="<?= e($sid) ?>-btn" aria-expanded="<?= $isOpen ? 'true' : 'false' ?>" aria-controls="<?= e($sid) ?>" data-side-toggle>
          <?= svg_icon($sectionIcons[$section] ?? 'conteudo', 'side-ico') ?>
          <span><?= e($section) ?></span>
          <?= svg_icon('chevron', 'side-chevron') ?>
        </button>
        <div class="side-group-body" id="<?= e($sid) ?>" role="region" aria-labelledby="<?= e($sid) ?>-btn">
          <nav class="side-group-inner" aria-label="<?= e($section) ?>">
            <?php foreach ($visible as $file => [$icon, $label, $perm]): $active = $navKey === $file; ?>
            <a class="<?= $active ? 'active' : '' ?>" href="<?= e($file) ?>"<?= $active ? ' aria-current="page"' : '' ?><?= $isOpen ? '' : ' tabindex="-1"' ?>><?= e($label) ?></a>
            <?php endforeach; ?>
          </nav>
        </div>
      </div>
    <?php endforeach; ?>
    </div>
    <nav class="side-footer" aria-label="Sessão">
      <a href="../index.php" target="_blank" rel="noopener"><?= svg_icon('site', 'side-ico') ?>Ver site</a>
      <a href="index.php?logout=1&token=<?= e(csrf_token()) ?>"><?= svg_icon('seta-dir', 'side-ico') ?>Sair</a>
    </nav>
  </aside>
  <div class="admin-backdrop" data-admin-close></div>
  <div class="admin-main">
    <header class="admin-top">
      <button class="admin-menu-btn" type="button" data-admin-toggle aria-controls="adminSide" aria-expanded="false" aria-label="Abrir menu">☰</button>
      <span class="admin-top-title"><?= e($admin_title ?? 'Painel') ?></span>
      <span class="admin-top-right">
        <a class="admin-help-link" href="ajuda.php#<?= e($help) ?>" title="Ajuda sobre esta tela">? Ajuda</a>
        <a class="admin-user" href="minha_conta.php" title="Minha conta"><?= e($me['nome'] ?? 'Admin') ?> <small><?= e($me['papel_nome'] ?? '') ?></small></a>
      </span>
    </header>
    <?php if (maintenance_active() || setting($pdo, 'manutencao_ativa', '0') === '1'): ?>
      <div class="admin-alert-bar">🔧 <strong>Modo de manutenção <?= maintenance_active() ? 'ativo' : 'agendado' ?>.</strong> Visitantes <?= maintenance_active() ? 'estão vendo' : 'verão' ?> a página de manutenção. <?php if (can('gerenciar:manutencao')): ?><a href="manutencao.php">Gerenciar</a><?php endif; ?></div>
    <?php endif; ?>
    <div class="admin-flash-area" id="admin-main" tabindex="-1">
      <?php foreach ($flashes as $f): ?>
        <div class="notice <?= $f['type'] === 'error' ? 'error' : 'success' ?> flash" role="<?= $f['type'] === 'error' ? 'alert' : 'status' ?>">
          <?= e($f['msg']) ?>
          <?php if (!empty($f['undo'])): ?> <a class="undo-link" href="<?= e($f['undo']) ?>">↶ Desfazer</a><?php endif; ?>
          <button type="button" class="flash-close" aria-label="Fechar" onclick="this.parentElement.remove()">×</button>
        </div>
      <?php endforeach; ?>
    </div>
<?php if ($forbidden): ?>
    <main class="admin-content">
      <div class="forbidden-box">
        <div class="forbidden-icon" aria-hidden="true">🔒</div>
        <h1>Acesso restrito</h1>
        <p>Seu papel (<strong><?= e($me['papel_nome'] ?? 'sem papel') ?></strong>) não tem a permissão necessária para esta tela: <code><?= e($admin_perm) ?></code> — <?= e(perm_label($admin_perm)) ?>.</p>
        <p class="muted">Se precisar desse acesso, peça a um administrador para ajustar seu papel em Sistema › Papéis e permissões.</p>
        <a class="btn btn-primary" href="dashboard.php">Voltar ao dashboard</a>
      </div>
    </main>
<?php include __DIR__ . '/footer.php'; exit; endif; ?>
