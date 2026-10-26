<?php
/* Página de configuração do plugin (incluída por admin/plugin.php, já com login e permissão verificados). */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST['csrf_token'] ?? null)) {
    save_setting($pdo, 'plugin_whatsapp_numero', preg_replace('/\D+/', '', (string)($_POST['numero'] ?? '')));
    save_setting($pdo, 'plugin_whatsapp_mensagem', trim((string)($_POST['mensagem'] ?? '')));
    save_setting($pdo, 'plugin_whatsapp_rotulo', trim((string)($_POST['rotulo'] ?? '')) ?: 'Fale conosco');
    save_setting($pdo, 'plugin_whatsapp_lado', ($_POST['lado'] ?? '') === 'esquerda' ? 'esquerda' : 'direita');
    audit('configuracoes', 'plugin', null, ['plugin' => 'whatsapp-flutuante']);
    flash('success', 'Configurações do WhatsApp flutuante salvas.');
    redirect('plugin.php?p=whatsapp-flutuante');
}
include BN_ROOT . '/admin/includes/header.php';
?>
<main class="admin-content">
  <h1>WhatsApp flutuante</h1>
  <p class="muted">Botão fixo no canto da tela, em todas as páginas do site.</p>
  <div class="admin-panel">
    <form class="admin-form" method="post">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <label>Número com DDI e DDD <small class="muted">Vazio = telefone das Configurações globais.</small><input name="numero" value="<?= e(setting($pdo, 'plugin_whatsapp_numero', '')) ?>" placeholder="5585999999999"></label>
      <label>Mensagem inicial<input name="mensagem" value="<?= e(setting($pdo, 'plugin_whatsapp_mensagem', 'Olá! Vim pelo site.')) ?>"></label>
      <div class="form-grid-2">
        <label>Texto do botão<input name="rotulo" value="<?= e(setting($pdo, 'plugin_whatsapp_rotulo', 'Fale conosco')) ?>"></label>
        <label>Posição<select name="lado"><option value="direita">Direita</option><option value="esquerda" <?= setting($pdo, 'plugin_whatsapp_lado', '') === 'esquerda' ? 'selected' : '' ?>>Esquerda</option></select></label>
      </div>
      <div><button class="btn btn-primary">Salvar</button></div>
    </form>
  </div>
</main>
<?php include BN_ROOT . '/admin/includes/footer.php'; ?>
