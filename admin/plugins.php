<?php
$admin_title = 'Plugins';
require __DIR__ . '/includes/init.php';
$all = plugins_available();
$on = plugins_enabled();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) { flash('error', 'Sessão expirada.'); redirect('plugins.php'); }
    $slug = (string)($_POST['plugin'] ?? '');
    if (isset($all[$slug])) {
        $enable = ($_POST['acao'] ?? '') === 'ativar';
        $on = $enable ? array_values(array_unique([...$on, $slug])) : array_values(array_diff($on, [$slug]));
        save_setting($pdo, 'plugins_ativos', json_encode($on));
        audit('plugin', 'plugins', null, ['plugin' => $slug, 'ativo' => $enable]);
        flash('success', "Plugin “{$all[$slug]['nome']}” " . ($enable ? 'ativado.' : 'desativado.'));
    }
    redirect('plugins.php');
}
include __DIR__ . '/includes/header.php';
?>
<main class="admin-content">
  <h1>Plugins</h1>
  <p class="muted">Funcionalidades opcionais que estendem o CMS sem alterar o núcleo. Cada cliente escolhe quais usar. Para criar um plugin, veja o <a href="ajuda.php#plugins">guia do desenvolvedor</a>.</p>
  <div class="theme-cards mt-3">
    <?php foreach ($all as $slug => $p): $active = in_array($slug, $on, true); ?>
    <article class="theme-card<?= $active ? ' active' : '' ?>">
      <div class="theme-card-body">
        <h3><?= e($p['nome']) ?> <?= $active ? '<span class="badge-soft ok">ativo</span>' : '' ?></h3>
        <p><?= e($p['descricao']) ?></p>
        <p class="small muted">Versão <?= e($p['versao']) ?><?= $p['autor'] ? ' · ' . e($p['autor']) : '' ?> · <code><?= e($slug) ?></code></p>
        <form method="post" class="theme-card-actions"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="plugin" value="<?= e($slug) ?>">
          <?php if ($active): ?><button class="btn btn-sm btn-outline-secondary" name="acao" value="desativar" data-confirm="Desativar o plugin “<?= e($p['nome']) ?>”? As configurações dele são mantidas.">Desativar</button>
          <?php if (!empty($p['configuracao'])): ?><a class="btn btn-sm btn-link" href="<?= e($p['configuracao']) ?>">Configurar</a><?php endif; ?>
          <?php else: ?><button class="btn btn-sm btn-primary" name="acao" value="ativar">Ativar</button><?php endif; ?>
        </form>
      </div>
    </article>
    <?php endforeach; ?>
    <?php if (!$all): ?><p class="muted">Nenhum plugin instalado na pasta plugins/.</p><?php endif; ?>
  </div>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
