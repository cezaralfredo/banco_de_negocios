<?php
$admin_title = 'Backup';
require __DIR__ . '/includes/init.php';

if (isset($_GET['baixar'])) {
    $path = backup_path((string)$_GET['baixar']);
    if (!$path || !verify_csrf($_GET['token'] ?? null)) { flash('error', 'Backup não encontrado.'); redirect('backup.php'); }
    audit('backup_baixado', 'backup', null, ['arquivo' => basename($path)]);
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . basename($path) . '"');
    header('Content-Length: ' . filesize($path));
    readfile($path);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) { flash('error', 'Sessão expirada.'); redirect('backup.php'); }
    $act = $_POST['acao'] ?? '';
    try {
        if ($act === 'gerar') {
            $name = backup_create(!empty($_POST['midia']));
            flash('success', "Backup gerado: $name");
        } elseif ($act === 'excluir') {
            $path = backup_path((string)$_POST['arquivo']);
            if ($path) { @unlink($path); audit('backup_excluido', 'backup', null, ['arquivo' => basename($path)]); flash('success', 'Backup excluído.'); }
        } elseif ($act === 'restaurar') {
            backup_restore((string)$_POST['arquivo'], !empty($_POST['midia']));
            flash('success', 'Backup restaurado. Um backup do estado anterior foi criado automaticamente (marcado como “antes da restauração”).');
        } elseif ($act === 'enviar') {
            $f = $_FILES['backup'] ?? null;
            if (!$f || $f['error'] !== UPLOAD_ERR_OK || !preg_match('/\.(zip|sql)$/i', (string)$f['name'])) throw new RuntimeException('Envie um arquivo .zip ou .sql gerado por este CMS.');
            $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
            $name = 'backup-' . BN_TENANT . '-' . date('Ymd-His') . '-env.' . $ext;
            move_uploaded_file($f['tmp_name'], backup_dir() . '/' . $name);
            audit('backup_criado', 'backup', null, ['arquivo' => $name, 'origem' => 'upload']);
            flash('success', "Arquivo enviado como $name. Somente backups gerados por este cliente (com assinatura válida) podem ser restaurados.");
        } elseif ($act === 'config') {
            save_setting($pdo, 'backup_auto', in_array($_POST['backup_auto'] ?? '', ['desligado', 'diario', 'semanal'], true) ? $_POST['backup_auto'] : 'semanal');
            save_setting($pdo, 'backup_manter', (string)max(1, min(60, (int)($_POST['backup_manter'] ?? 7))));
            save_setting($pdo, 'backup_auto_midia', isset($_POST['backup_auto_midia']) ? '1' : '0');
            audit('configuracoes', 'backup', null, ['automatico' => $_POST['backup_auto'] ?? '']);
            flash('success', 'Configuração do backup automático salva.');
        } elseif ($act === 'nova_chave') {
            save_setting($pdo, 'cron_chave', bin2hex(random_bytes(12)));
            flash('success', 'Nova chave do agendador gerada. Atualize a tarefa agendada no servidor.');
        }
    } catch (Throwable $ex) {
        flash('error', 'Erro: ' . $ex->getMessage());
    }
    redirect('backup.php');
}
$list = backup_list();
$cronUrl = site_base_url() . 'cron.php?chave=' . setting($pdo, 'cron_chave', '') . (BN_TENANT !== 'principal' ? '&cliente=' . BN_TENANT : '');
$mediaSize = 0;
foreach ([BN_ROOT . '/' . media_base(), BN_ROOT . '/assets/img/uploads'] as $d) if (is_dir($d)) foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($d, FilesystemIterator::SKIP_DOTS)) as $f) $mediaSize += $f->getSize();
include __DIR__ . '/includes/header.php';
?>
<main class="admin-content">
  <div class="admin-heading">
    <div><h1>Backup</h1><p class="muted">Cópias de segurança do banco de dados e dos arquivos de mídia deste cliente. Ficam em uma pasta protegida do servidor; baixe e guarde cópias fora dele também.</p></div>
  </div>
  <div class="split">
    <div>
      <div class="admin-panel mb-4">
        <h2>Gerar backup agora</h2>
        <form method="post" class="d-flex gap-3 align-items-center flex-wrap">
          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="acao" value="gerar">
          <label class="check-row"><input type="checkbox" name="midia" value="1" checked> Incluir arquivos de mídia (<?= e(format_bytes($mediaSize)) ?>)</label>
          <button class="btn btn-primary">💾 Gerar backup</button>
        </form>
      </div>
      <div class="admin-panel">
        <h2>Backups disponíveis</h2>
        <div class="table-responsive">
          <table class="admin-table">
            <thead><tr><th>Arquivo</th><th>Data</th><th>Tamanho</th><th>Ações</th></tr></thead>
            <tbody>
            <?php foreach ($list as $b): ?>
              <tr>
                <td><strong><?= e($b['nome']) ?></strong> <?= $b['auto'] ? '<span class="badge-soft">automático</span>' : '' ?><?= $b['pre'] ? '<span class="badge-soft warn">antes da restauração</span>' : '' ?></td>
                <td><?= e(date('d/m/Y H:i', $b['data'])) ?></td>
                <td><?= e(format_bytes((int)$b['tamanho'])) ?></td>
                <td class="actions">
                  <a class="btn-action" href="?baixar=<?= urlencode($b['nome']) ?>&token=<?= e(csrf_token()) ?>">⬇ Baixar</a>
                  <form method="post" class="d-inline"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="arquivo" value="<?= e($b['nome']) ?>"><input type="hidden" name="midia" value="1">
                    <button class="btn-action" name="acao" value="restaurar" data-confirm="Restaurar este backup? TODO o conteúdo atual do banco será substituído pelo do backup de <?= e(date('d/m/Y H:i', $b['data'])) ?>. Um backup do estado atual será criado antes." data-confirm-word="RESTAURAR" data-confirm-button="Restaurar backup">Restaurar</button>
                    <button class="btn-action danger" name="acao" value="excluir" data-confirm="Excluir o arquivo de backup <?= e($b['nome']) ?>? Esta ação não pode ser desfeita.">Excluir</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$list): ?><tr><td colspan="4" class="muted">Nenhum backup ainda.</td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
        <form method="post" enctype="multipart/form-data" class="d-flex gap-2 align-items-center flex-wrap mt-3">
          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="acao" value="enviar">
          <span class="muted small">Restaurar a partir de um arquivo baixado antes:</span>
          <input type="file" name="backup" accept=".zip,.sql" class="form-control form-control-sm" style="max-width:280px" required>
          <button class="btn btn-sm btn-outline-secondary">Enviar arquivo</button>
        </form>
      </div>
    </div>
    <aside>
      <div class="side-card">
        <h3>Backup automático</h3>
        <form method="post" class="admin-form" style="padding:0">
          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="acao" value="config">
          <label>Frequência<select name="backup_auto"><?php foreach (['desligado' => 'Desligado', 'diario' => 'Diário', 'semanal' => 'Semanal'] as $k => $l): ?><option value="<?= $k ?>" <?= setting($pdo, 'backup_auto', 'semanal') === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></label>
          <label>Manter os últimos<input type="number" name="backup_manter" min="1" max="60" value="<?= (int)setting($pdo, 'backup_manter', '7') ?>"></label>
          <label class="check-row"><input type="checkbox" name="backup_auto_midia" value="1" <?= setting($pdo, 'backup_auto_midia', '1') === '1' ? 'checked' : '' ?>> Incluir mídia</label>
          <button class="btn btn-sm btn-primary">Salvar</button>
        </form>
        <p class="small muted mt-3 mb-1">Último backup: <strong><?= setting($pdo, 'backup_ultimo', '') ? e(date('d/m/Y H:i', strtotime(setting($pdo, 'backup_ultimo', '')))) : 'nunca' ?></strong></p>
        <p class="small muted mb-0">O backup automático roda quando alguém acessa o painel. Para garantir o horário, agende o endereço abaixo no cron do servidor/cPanel (ex.: 1x por dia):</p>
      </div>
      <div class="side-card">
        <h3>Tarefa agendada (cron)</h3>
        <input class="form-control form-control-sm mb-2" readonly value="<?= e($cronUrl) ?>" onclick="this.select()" aria-label="Endereço do cron">
        <code class="small d-block mb-2">wget -q -O- "<?= e($cronUrl) ?>"</code>
        <p class="small muted">Última execução: <?= setting($pdo, 'cron_ultimo', '') ? e(date('d/m/Y H:i', strtotime(setting($pdo, 'cron_ultimo', '')))) : 'nunca' ?></p>
        <form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><button class="btn-action" name="acao" value="nova_chave" data-confirm="Gerar nova chave? A tarefa agendada atual deixará de funcionar até ser atualizada." data-confirm-style="primary">Gerar nova chave</button></form>
      </div>
    </aside>
  </div>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
