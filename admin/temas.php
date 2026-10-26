<?php
$admin_title = 'Temas e layout';
require __DIR__ . '/includes/init.php';
$themes = themes_available();
$schema = theme_var_schema();
$current = setting($pdo, 'tema', 'classico');
$edit = isset($_GET['tema'], $themes[$_GET['tema']]) ? (string)$_GET['tema'] : (isset($themes[$current]) ? $current : array_key_first($themes));
$navOptions = ['light' => 'Clara com texto escuro (padrão)', 'primary-light' => 'Cor principal com texto claro', 'primary-dark' => 'Cor principal com texto escuro'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) { flash('error', 'Sessão expirada.'); redirect('temas.php'); }
    $slug = (string)($_POST['tema'] ?? '');
    if (!isset($themes[$slug])) { flash('error', 'Tema inválido.'); redirect('temas.php'); }
    $act = $_POST['acao'] ?? '';
    if ($act === 'ativar') {
        save_setting($pdo, 'tema', $slug);
        audit('tema', 'tema', null, ['de' => $current, 'para' => $slug]);
        flash('success', "Tema “{$themes[$slug]['nome']}” ativado. O site já está usando o novo visual.", 'temas.php?desfazer_tema=' . urlencode($current) . '&token=' . urlencode(csrf_token()));
        redirect('temas.php?tema=' . $slug);
    }
    if ($act === 'restaurar') {
        save_setting($pdo, 'tema_vars_' . $slug, '{}');
        audit('tema', 'tema', null, ['tema' => $slug, 'personalizacao' => 'restaurada ao padrão']);
        flash('success', 'Personalizações removidas: o tema voltou às cores e fontes originais.');
        redirect('temas.php?tema=' . $slug);
    }
    $vars = [];
    $defaults = $themes[$slug]['variaveis'];
    foreach ($schema as $k => [$label, $type]) {
        $v = trim((string)($_POST['v'][$k] ?? ''));
        if ($type === 'color' && !preg_match('/^#[0-9a-f]{6}$/i', $v)) continue;
        if ($type === 'font' && !in_array($v, theme_fonts(), true)) continue;
        if ($k === 'raio') $v = (string)max(0, min(32, (int)$v));
        if ($k === 'botoes' && !in_array($v, ['pilula', 'arredondado', 'reto'], true)) continue;
        if ($k === 'largura' && !in_array($v, ['compacta', 'larga', 'extra'], true)) continue;
        if ((string)($defaults[$k] ?? '') !== $v) $vars[$k] = $v; // guarda só o que difere do padrão
    }
    save_setting($pdo, 'tema_vars_' . $slug, json_encode($vars));
    if (isset($navOptions[$_POST['nav_theme'] ?? ''])) save_setting($pdo, 'nav_theme', $_POST['nav_theme']);
    audit('tema', 'tema', null, ['tema' => $slug, 'personalizado' => array_keys($vars)]);
    flash('success', 'Personalização salva.');
    redirect('temas.php?tema=' . $slug);
}
if (isset($_GET['desfazer_tema']) && verify_csrf($_GET['token'] ?? null) && isset($themes[$_GET['desfazer_tema']])) {
    save_setting($pdo, 'tema', (string)$_GET['desfazer_tema']);
    audit('tema', 'tema', null, ['desfeito_para' => $_GET['desfazer_tema']]);
    flash('success', 'Troca de tema desfeita.');
    redirect('temas.php');
}
$vals = theme_vars($edit);
include __DIR__ . '/includes/header.php';
?>
<main class="admin-content">
  <div class="admin-heading">
    <div><h1>Temas e layout</h1><p class="muted">Escolha um tema pronto e personalize cores, fontes e formato. As mudanças valem só para este cliente e não exigem alteração de código.</p></div>
  </div>
  <div class="theme-cards mb-4">
    <?php foreach ($themes as $slug => $t): $isActive = $slug === $current; ?>
    <article class="theme-card<?= $isActive ? ' active' : '' ?>">
      <div class="theme-swatch" aria-hidden="true"><?php foreach ((array)($t['preview'] ?? []) as $c): ?><span style="background:<?= e($c) ?>"></span><?php endforeach; ?></div>
      <div class="theme-card-body">
        <h3><?= e($t['nome']) ?> <?= $isActive ? '<span class="badge-soft ok">ativo</span>' : '' ?></h3>
        <p><?= e($t['descricao']) ?></p>
        <p class="small"><strong>Fontes:</strong> <?= e(($t['variaveis']['fonte_titulos'] ?? '') . ' / ' . ($t['variaveis']['fonte_texto'] ?? '')) ?></p>
        <div class="theme-card-actions">
          <a class="btn btn-sm btn-outline-secondary" href="../index.php?preview_tema=<?= e($slug) ?>" target="_blank">Pré-visualizar</a>
          <?php if (!$isActive): ?><form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="tema" value="<?= e($slug) ?>"><button class="btn btn-sm btn-primary" name="acao" value="ativar" data-confirm="Ativar o tema “<?= e($t['nome']) ?>”? O visual do site muda imediatamente (você poderá desfazer)." data-confirm-style="primary" data-confirm-button="Ativar">Ativar</button></form><?php endif; ?>
          <a class="btn btn-sm btn-link" href="?tema=<?= e($slug) ?>#personalizar">Personalizar</a>
        </div>
      </div>
    </article>
    <?php endforeach; ?>
  </div>

  <div class="admin-panel" id="personalizar">
    <div class="edit-panel-head"><h2>Personalizar: <?= e($themes[$edit]['nome']) ?></h2><?= $edit !== $current ? '<span class="badge-soft warn">este tema não está ativo</span>' : '' ?></div>
    <form method="post" class="admin-form" style="max-width:none" data-dirty-check>
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="tema" value="<?= e($edit) ?>">
      <div class="theme-vars">
        <?php foreach ($schema as $k => [$label, $type]): $v = (string)($vals[$k] ?? ''); ?>
        <label><?= e($label) ?>
          <?php if ($type === 'color'): ?><input type="color" name="v[<?= e($k) ?>]" value="<?= e($v ?: '#000000') ?>">
          <?php elseif ($type === 'font'): ?><select name="v[<?= e($k) ?>]"><?php foreach (theme_fonts() as $font): ?><option <?= $v === $font ? 'selected' : '' ?>><?= e($font) ?></option><?php endforeach; ?></select>
          <?php elseif ($type === 'range'): ?><span class="d-flex gap-2 align-items-center"><input type="range" min="0" max="32" name="v[<?= e($k) ?>]" value="<?= (int)$v ?>" oninput="this.nextElementSibling.textContent=this.value+'px'"><output><?= (int)$v ?>px</output></span>
          <?php elseif ($k === 'botoes'): ?><select name="v[botoes]"><?php foreach (['pilula' => 'Pílula (bem arredondado)', 'arredondado' => 'Cantos arredondados', 'reto' => 'Retos'] as $o => $ol): ?><option value="<?= $o ?>" <?= $v === $o ? 'selected' : '' ?>><?= $ol ?></option><?php endforeach; ?></select>
          <?php elseif ($k === 'largura'): ?><select name="v[largura]"><?php foreach (['compacta' => 'Compacta (1040px)', 'larga' => 'Larga (1180px)', 'extra' => 'Extra larga (1320px)'] as $o => $ol): ?><option value="<?= $o ?>" <?= $v === $o ? 'selected' : '' ?>><?= $ol ?></option><?php endforeach; ?></select>
          <?php endif; ?>
        </label>
        <?php endforeach; ?>
        <label>Barra de navegação<select name="nav_theme"><?php foreach ($navOptions as $k => $l): ?><option value="<?= e($k) ?>" <?= setting($pdo, 'nav_theme', 'light') === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
      </div>
      <div class="d-flex gap-2 flex-wrap">
        <button class="btn btn-primary" name="acao" value="salvar">Salvar personalização</button>
        <a class="btn btn-outline-secondary" href="../index.php?preview_tema=<?= e($edit) ?>" target="_blank">Ver no site</a>
        <button class="btn btn-link text-danger" name="acao" value="restaurar" formnovalidate data-confirm="Remover todas as personalizações deste tema e voltar ao padrão?">Restaurar padrão do tema</button>
      </div>
      <p class="muted mb-0">Logo, nome e favicon ficam em <a href="configuracoes_gerais.php?aba=identidade">Configurações globais › Identidade</a>.</p>
    </form>
  </div>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
