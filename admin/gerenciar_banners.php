<?php
$admin_title = 'Gerenciar Banners';
include __DIR__ . '/includes/header.php';
$msg = '';
$erro = '';
$efeitos = ['padrao' => 'Usar o efeito padrão do slider', 'fade' => 'Fade suave', 'slide' => 'Deslizar (lateral)', 'zoom' => 'Zoom', 'none' => 'Sem efeito (troca direta)'];
$efeitosGlobais = array_diff_key($efeitos, ['padrao' => 1]);
$edit = isset($_GET['edit']) && $pdo ? db_one($pdo, 'SELECT * FROM banners WHERE id=?', [(int)$_GET['edit']]) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo) {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $erro = 'Token de segurança inválido.';
    } elseif (($_POST['action'] ?? '') === 'slider') {
        // Configurações gerais do slider: setas, indicadores, reprodução automática e efeito padrão
        save_setting($pdo, 'slider_arrows', isset($_POST['slider_arrows']) ? '1' : '0');
        save_setting($pdo, 'slider_dots', isset($_POST['slider_dots']) ? '1' : '0');
        save_setting($pdo, 'slider_autoplay', isset($_POST['slider_autoplay']) ? '1' : '0');
        $ef = $_POST['slider_effect'] ?? 'fade';
        save_setting($pdo, 'slider_effect', isset($efeitosGlobais[$ef]) ? $ef : 'fade');
        $msg = 'Configurações do slider salvas.';
    } else {
        $id = (int)($_POST['id'] ?? 0);
        $old = $id ? db_one($pdo, 'SELECT * FROM banners WHERE id=?', [$id]) : null;
        $titulo = trim($_POST['titulo'] ?? '');
        $subtitulo = trim($_POST['subtitulo'] ?? '');
        $imagem = trim($_POST['imagem_fundo'] ?? '');
        $texto = trim($_POST['texto_botao'] ?? '');
        $link = trim($_POST['link_botao'] ?? '');
        $duracao = max(2, min(60, (int)($_POST['duracao_segundos'] ?? 5)));
        $efeito = isset($efeitos[$_POST['efeito'] ?? '']) ? $_POST['efeito'] : 'padrao';
        $ordem = max(0, (int)($_POST['ordem'] ?? 0));
        $status = isset($_POST['status']) ? 1 : 0;
        if ($titulo === '') {
            $erro = 'Informe o título do banner.';
        } else {
            try {
                $up = upload_image_file($_FILES['imagem'] ?? [], 'banner', 2200, 1200);
                if ($up) $imagem = $up;
                elseif ($imagem === '' && $old) $imagem = $old['imagem_fundo'] ?? '';
            } catch (Throwable $ex) {
                $erro = $ex->getMessage();
            }
        }
        if (!$erro) {
            if ($id) {
                $pdo->prepare('UPDATE banners SET titulo=?,subtitulo=?,imagem_fundo=?,texto_botao=?,link_botao=?,status=?,duracao_segundos=?,efeito=?,ordem=? WHERE id=?')->execute([$titulo, $subtitulo, $imagem, $texto, $link, $status, $duracao, $efeito, $ordem, $id]);
            } else {
                $pdo->prepare('INSERT INTO banners(titulo,subtitulo,imagem_fundo,texto_botao,link_botao,status,duracao_segundos,efeito,ordem) VALUES(?,?,?,?,?,?,?,?,?)')->execute([$titulo, $subtitulo, $imagem, $texto, $link, $status, $duracao, $efeito, $ordem]);
            }
            $msg = $id ? 'Banner atualizado com sucesso.' : 'Banner cadastrado com sucesso.';
            $edit = null;
            unset($_GET['novo']);
        }
    }
}
if (isset($_GET['delete']) && $pdo && verify_csrf($_GET['token'] ?? null)) {
    $pdo->prepare('DELETE FROM banners WHERE id=?')->execute([(int)$_GET['delete']]);
    $msg = 'Banner excluído.';
}
$banners = $pdo ? db_rows($pdo, 'SELECT * FROM banners ORDER BY ordem ASC,id DESC') : [];
$showForm = $edit || isset($_GET['novo']);
?>
<main class="admin-content">
  <div class="admin-heading">
    <div><h1>Banners e slider</h1><p class="muted">Controle o conteúdo, o tempo, o efeito de transição, a ordem e a navegação do slider principal.</p></div>
    <a class="btn btn-primary" href="?novo=1">+ Novo banner</a>
  </div>
  <?php if ($msg): ?><div class="notice success"><?= e($msg) ?></div><?php endif; ?>
  <?php if ($erro): ?><div class="notice error"><?= e($erro) ?></div><?php endif; ?>

  <?php if (!$showForm): ?>
  <div class="admin-panel mb-4">
    <h2>Navegação e transição do slider</h2>
    <form class="admin-form slider-settings" method="post">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="slider">
      <div class="form-grid-2">
        <div class="d-grid gap-2">
          <label class="check-row"><input type="checkbox" name="slider_arrows" value="1" <?= setting($pdo, 'slider_arrows', '1') === '1' ? 'checked' : '' ?>> Exibir setas de navegação (anterior / próximo)</label>
          <label class="check-row"><input type="checkbox" name="slider_dots" value="1" <?= setting($pdo, 'slider_dots', '1') === '1' ? 'checked' : '' ?>> Exibir indicadores (bolinhas)</label>
          <label class="check-row"><input type="checkbox" name="slider_autoplay" value="1" <?= setting($pdo, 'slider_autoplay', '1') === '1' ? 'checked' : '' ?>> Troca automática (com botão pausar)</label>
        </div>
        <label>Efeito de transição padrão
          <select name="slider_effect"><?php foreach ($efeitosGlobais as $k => $l): ?><option value="<?= $k ?>" <?= setting($pdo, 'slider_effect', 'fade') === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select>
          <small class="muted">Aplicado aos banners configurados como “Usar o efeito padrão”. Cada banner pode ter um efeito próprio.</small>
        </label>
      </div>
      <div><button class="btn btn-primary">Salvar configurações do slider</button></div>
    </form>
    <p class="muted mb-0 mt-2">As setas e os indicadores aparecem somente quando há dois ou mais banners ativos.</p>
  </div>
  <?php endif; ?>

  <div class="admin-panel">
  <?php if ($showForm): $editId = (int)($edit['id'] ?? 0); ?>
    <div class="edit-panel<?= $editId ? ' is-editing' : '' ?>">
      <div class="edit-panel-head"><?php if ($editId): ?><span class="edit-badge">✎ Editando</span><h2><?= e($edit['titulo']) ?></h2><?php else: ?><h2>Novo banner</h2><?php endif; ?></div>
      <form class="admin-form" method="post" action="gerenciar_banners.php" enctype="multipart/form-data">
        <input type="hidden" name="id" value="<?= $editId ?>">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <label>Título<textarea name="titulo" rows="2" required style="min-height:70px"><?= e($edit['titulo'] ?? '') ?></textarea><small class="muted">Quebras de linha são respeitadas.</small></label>
        <label>Subtítulo<textarea name="subtitulo"><?= e($edit['subtitulo'] ?? '') ?></textarea></label>
        <div class="form-grid-2">
          <label>Upload da imagem<input type="file" name="imagem" accept="image/jpeg,image/png,image/webp"><small class="muted"><?= $editId ? 'Deixe vazio para manter a imagem atual. ' : '' ?>JPG, PNG ou WebP · máximo 5 MB</small></label>
          <label>URL da imagem<input name="imagem_fundo" value="<?= e($edit['imagem_fundo'] ?? '') ?>" placeholder="https://... ou assets/img/banner.png"></label>
        </div>
        <?php if (!empty($edit['imagem_fundo'])): ?><img class="promo-preview" src="<?= preg_match('#^https?://#', $edit['imagem_fundo']) ? '' : '../' ?><?= e($edit['imagem_fundo']) ?>" alt="Imagem atual"><?php endif; ?>
        <div class="form-grid-2">
          <label>Tempo na tela (segundos)<input type="number" name="duracao_segundos" min="2" max="60" value="<?= (int)($edit['duracao_segundos'] ?? 5) ?>"></label>
          <label>Efeito de transição<select name="efeito"><?php foreach ($efeitos as $k => $l): ?><option value="<?= $k ?>" <?= ($edit['efeito'] ?? 'padrao') === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
        </div>
        <div class="form-grid-2">
          <label>Texto do botão<input name="texto_botao" value="<?= e($edit['texto_botao'] ?? 'Explorar anúncios') ?>"></label>
          <label>Link do botão<input name="link_botao" value="<?= e($edit['link_botao'] ?? 'categoria.php') ?>"><small class="muted">Use cadastro.php para abrir o modal de cadastro.</small></label>
        </div>
        <label>Ordem de exibição<input type="number" name="ordem" min="0" max="999" value="<?= (int)($edit['ordem'] ?? 0) ?>"><small class="muted">Menor número aparece primeiro.</small></label>
        <label class="check-row"><input type="checkbox" name="status" value="1" <?= (($edit['status'] ?? 1) ? 'checked' : '') ?>> Banner ativo e visível no site</label>
        <div><button class="btn btn-primary">Salvar banner</button> <a class="btn btn-outline-secondary" href="gerenciar_banners.php">Cancelar</a></div>
      </form>
    </div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="admin-table">
        <thead><tr><th>Ordem</th><th>Título</th><th>Imagem</th><th>Tempo</th><th>Efeito</th><th>Status</th><th>Ações</th></tr></thead>
        <tbody>
        <?php if (!$banners): ?><tr><td colspan="7" class="muted">Nenhum banner cadastrado.</td></tr><?php endif; ?>
        <?php foreach ($banners as $b): ?>
          <tr>
            <td><?= (int)($b['ordem'] ?? 0) ?></td>
            <td><strong><?= e(str_replace("\n", ' ', $b['titulo'])) ?></strong></td>
            <td><?= $b['imagem_fundo'] ? 'Configurada' : 'Sem imagem' ?></td>
            <td><?= (int)($b['duracao_segundos'] ?? 5) ?>s</td>
            <td><?= e($efeitos[$b['efeito'] ?? 'padrao'] ?? ucfirst((string)$b['efeito'])) ?></td>
            <td><span class="status status-<?= $b['status'] ? 'ativo' : 'inativo' ?>"><?= $b['status'] ? 'Ativo' : 'Inativo' ?></span></td>
            <td class="actions"><a class="btn-action" href="?edit=<?= (int)$b['id'] ?>">Editar</a> <a class="btn-action danger" href="?delete=<?= (int)$b['id'] ?>&token=<?= e(csrf_token()) ?>" onclick="return confirm('Excluir este banner?')">Excluir</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
  </div>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
