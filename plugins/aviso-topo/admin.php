<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST['csrf_token'] ?? null)) {
    foreach (['texto', 'link_texto'] as $k) save_setting($pdo, 'plugin_aviso_' . $k, trim((string)($_POST[$k] ?? '')));
    $link = trim((string)($_POST['link'] ?? ''));
    save_setting($pdo, 'plugin_aviso_link', preg_match('#^(https?://|/(?!/)|[a-z0-9_-]+\.php)#i', $link) ? $link : '');
    foreach (['cor', 'cor_texto'] as $k) if (preg_match('/^#[0-9a-f]{6}$/i', (string)($_POST[$k] ?? ''))) save_setting($pdo, 'plugin_aviso_' . $k, $_POST[$k]);
    foreach (['inicio', 'fim'] as $k) save_setting($pdo, 'plugin_aviso_' . $k, preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', (string)($_POST[$k] ?? '')) ? str_replace('T', ' ', $_POST[$k]) : '');
    audit('configuracoes', 'plugin', null, ['plugin' => 'aviso-topo']);
    flash('success', 'Barra de aviso atualizada.');
    redirect('plugin.php?p=aviso-topo');
}
$v = fn($k, $d = '') => setting($pdo, 'plugin_aviso_' . $k, $d);
$dt = fn($x) => $x ? date('Y-m-d\TH:i', strtotime($x)) : '';
include BN_ROOT . '/admin/includes/header.php';
?>
<main class="admin-content">
  <h1>Barra de aviso</h1>
  <p class="muted">Faixa exibida no topo de todas as páginas. Deixe o texto vazio para ocultar.</p>
  <div class="admin-panel">
    <form class="admin-form" method="post">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <label>Texto do aviso<input name="texto" maxlength="200" value="<?= e($v('texto')) ?>"></label>
      <div class="form-grid-2"><label>Link (opcional)<input name="link" value="<?= e($v('link')) ?>"></label><label>Texto do link<input name="link_texto" value="<?= e($v('link_texto', 'Saiba mais')) ?>"></label></div>
      <div class="form-grid-2"><label>Cor de fundo<input type="color" name="cor" value="<?= e($v('cor', '#ffb703')) ?>"></label><label>Cor do texto<input type="color" name="cor_texto" value="<?= e($v('cor_texto', '#1d1d1d')) ?>"></label></div>
      <div class="form-grid-2"><label>Exibir a partir de<input type="datetime-local" name="inicio" value="<?= e($dt($v('inicio'))) ?>"></label><label>Exibir até<input type="datetime-local" name="fim" value="<?= e($dt($v('fim'))) ?>"></label></div>
      <div><button class="btn btn-primary">Salvar</button></div>
    </form>
  </div>
</main>
<?php include BN_ROOT . '/admin/includes/footer.php'; ?>
