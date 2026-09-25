<?php
$admin_title = 'Gerenciar Anúncios';
include __DIR__ . '/includes/header.php';
$msg = '';
$erro = '';
$edit = isset($_GET['edit']) && $pdo ? db_one($pdo, 'SELECT a.*, d.ordem destaque_ordem, d.ativo destaque_ativo FROM anuncios a LEFT JOIN anuncio_destaques d ON d.anuncio_id=a.id WHERE a.id=?', [(int)$_GET['edit']]) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo) {
    $id = (int)($_POST['id'] ?? 0);
    $old = $id ? db_one($pdo, 'SELECT * FROM anuncios WHERE id=?', [$id]) : null;
    $titulo = trim($_POST['titulo'] ?? '');
    $descricao = trim($_POST['descricao'] ?? '');
    $categoria = (int)($_POST['categoria_id'] ?? 0) ?: null;
    $tipo = in_array($_POST['tipo'] ?? '', ['produto', 'servico', 'ambos'], true) ? $_POST['tipo'] : 'servico';
    $whatsapp = preg_replace('/\D+/', '', $_POST['whatsapp'] ?? '');
    $local = trim($_POST['localizacao'] ?? '');
    $link = trim($_POST['link'] ?? '');
    $destaque = isset($_POST['destaque']) ? 1 : 0;
    $destaqueOrdem = max(0, (int)($_POST['destaque_ordem'] ?? 0));
    $avaliacao = min(5, max(0, (float)str_replace(',', '.', (string)($_POST['avaliacao'] ?? 0))));
    $status = ($_POST['status'] ?? 'ativo') === 'inativo' ? 'inativo' : 'ativo';
    $capa = $old['imagem_destaque'] ?? null;
    $logo = $old['logo'] ?? null;

    if (!verify_csrf($_POST['csrf_token'] ?? null)) $erro = 'Token de segurança inválido.';
    elseif ($titulo === '' || $descricao === '') $erro = 'Preencha o título e a descrição.';
    else {
        try {
            $capa = upload_image_file($_FILES['imagem'] ?? [], 'capa', 1400, 700) ?? $capa;
            $logo = upload_image_file($_FILES['logo'] ?? [], 'logo', 500, 500) ?? $logo;
            if (isset($_POST['remover_logo'])) $logo = null;
        } catch (Throwable $ex) {
            $erro = $ex->getMessage();
        }
    }
    if (!$erro) {
        if ($id) {
            $pdo->prepare('UPDATE anuncios SET titulo=?,descricao=?,categoria_id=?,tipo=?,imagem_destaque=?,logo=?,whatsapp=?,localizacao=?,link=?,avaliacao=?,status=? WHERE id=?')
                ->execute([$titulo, $descricao, $categoria, $tipo, $capa, $logo, $whatsapp, $local, $link, $avaliacao, $status, $id]);
        } else {
            $pdo->prepare('INSERT INTO anuncios(titulo,descricao,categoria_id,tipo,imagem_destaque,logo,whatsapp,localizacao,link,avaliacao,status) VALUES(?,?,?,?,?,?,?,?,?,?,?)')
                ->execute([$titulo, $descricao, $categoria, $tipo, $capa, $logo, $whatsapp, $local, $link, $avaliacao, $status]);
            $id = (int)$pdo->lastInsertId(); // guardado ANTES de qualquer outro INSERT
        }
        $pdo->prepare('DELETE FROM anuncio_destaques WHERE anuncio_id=?')->execute([$id]);
        if ($destaque) {
            $pdo->prepare('INSERT INTO anuncio_destaques(anuncio_id,ordem,ativo) VALUES(?,?,1)')->execute([$id, $destaqueOrdem]);
        }
        $msg = 'Anúncio salvo com sucesso.';
        $edit = null;
        unset($_GET['novo']);
    }
}
if (isset($_GET['delete']) && $pdo && verify_csrf($_GET['token'] ?? null)) {
    $delId = (int)$_GET['delete'];
    $pdo->prepare('DELETE FROM anuncio_destaques WHERE anuncio_id=?')->execute([$delId]);
    $pdo->prepare('DELETE FROM anuncios WHERE id=?')->execute([$delId]);
    $msg = 'Anúncio excluído.';
}
$cats = get_categories($pdo);
$term = trim($_GET['q'] ?? '');
$filter = trim($_GET['status'] ?? '');
$sql = 'SELECT a.*, c.nome categoria, d.ordem destaque_ordem FROM anuncios a LEFT JOIN categorias c ON c.id=a.categoria_id LEFT JOIN anuncio_destaques d ON d.anuncio_id=a.id AND d.ativo=1 WHERE 1=1';
$params = [];
if ($term !== '') { $sql .= ' AND (a.titulo LIKE ? OR a.descricao LIKE ?)'; $params = ["%$term%", "%$term%"]; }
if (in_array($filter, ['ativo', 'inativo'], true)) { $sql .= ' AND a.status=?'; $params[] = $filter; }
$sql .= ' ORDER BY a.data_criacao DESC';
$ads = $pdo ? db_rows($pdo, $sql, $params) : [];
$showForm = $edit || isset($_GET['novo']) || ($erro && $_SERVER['REQUEST_METHOD'] === 'POST');
if ($erro && !$edit && $_SERVER['REQUEST_METHOD'] === 'POST') $edit = array_merge($_POST, ['id' => (int)($_POST['id'] ?? 0), 'imagem_destaque' => $capa ?? null, 'logo' => $logo ?? null, 'destaque_ativo' => isset($_POST['destaque']) ? 1 : 0]);
$tipos = ['servico' => 'Serviço', 'produto' => 'Produto', 'ambos' => 'Produto e serviço'];
?>
<main class="admin-content">
  <div class="admin-heading">
    <div><h1>Gerenciar Anúncios</h1><p class="muted">Capa e logotipo são processados e armazenados em assets/img/uploads.</p></div>
    <a class="btn btn-primary" href="?novo=1">+ Novo anúncio</a>
  </div>
  <?php if ($msg): ?><div class="notice success"><?= e($msg) ?></div><?php endif; ?>
  <?php if ($erro): ?><div class="notice error"><?= e($erro) ?></div><?php endif; ?>
  <div class="admin-panel">
  <?php if ($showForm): $editId = (int)($edit['id'] ?? 0); ?>
    <div class="edit-panel<?= $editId ? ' is-editing' : '' ?>">
      <div class="edit-panel-head">
        <?php if ($editId): ?><span class="edit-badge">✎ Editando</span><h2><?= e($edit['titulo'] ?? '') ?></h2><?php else: ?><h2>Novo anúncio</h2><?php endif; ?>
      </div>
      <form class="admin-form" method="post" action="gerenciar_anuncios.php" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="id" value="<?= $editId ?>">
        <label>Título<input name="titulo" required value="<?= e($edit['titulo'] ?? '') ?>"></label>
        <div class="form-grid-2">
          <label>Categoria<select name="categoria_id" required><?php foreach ($cats as $c): ?><option value="<?= (int)$c['id'] ?>" <?= ((int)($edit['categoria_id'] ?? 0) === (int)$c['id']) ? 'selected' : '' ?>><?= e($c['nome']) ?></option><?php endforeach; ?></select></label>
          <label>Tipo <small class="muted">Usado no filtro “Tipo” da busca</small><select name="tipo"><?php foreach ($tipos as $k => $l): ?><option value="<?= $k ?>" <?= ($edit['tipo'] ?? 'servico') === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></label>
        </div>
        <label>Descrição<textarea name="descricao" required><?= e($edit['descricao'] ?? '') ?></textarea></label>
        <div class="form-grid-2">
          <label>Imagem de capa<input type="file" name="imagem" accept="image/jpeg,image/png,image/webp"><small class="muted">JPG, PNG ou WebP · até 5 MB. Redimensionada para o card.</small></label>
          <label>Logotipo / avatar<input type="file" name="logo" accept="image/jpeg,image/png,image/webp"><small class="muted">Imagem quadrada · até 5 MB. Aparece à esquerda nos cards sobre o banner.</small></label>
        </div>
        <?php if (!empty($edit['imagem_destaque']) || !empty($edit['logo'])): ?>
        <div class="upload-previews">
          <?php if (!empty($edit['imagem_destaque'])): ?><figure><img src="../<?= e($edit['imagem_destaque']) ?>" alt="Capa atual"><figcaption>Capa atual</figcaption></figure><?php endif; ?>
          <?php if (!empty($edit['logo'])): ?><figure><img src="../<?= e($edit['logo']) ?>" alt="Logo atual"><figcaption><label class="check-row"><input type="checkbox" name="remover_logo" value="1"> remover logo</label></figcaption></figure><?php endif; ?>
        </div>
        <?php endif; ?>
        <label>Link externo (site do negócio) <small class="muted">Opcional — exibido no perfil. Os cards da home sempre abrem o perfil (mesmo link do “Ver perfil”).</small><input name="link" value="<?= e($edit['link'] ?? '') ?>" placeholder="https://..."></label>
        <div class="form-grid-2">
          <label class="check-row"><input type="checkbox" name="destaque" value="1" <?= !empty($edit['destaque_ativo']) ? 'checked' : '' ?>> Exibir em “Negócios em destaque” e sobre o banner</label>
          <label>Ordem do destaque<input type="number" name="destaque_ordem" min="0" value="<?= (int)($edit['destaque_ordem'] ?? 0) ?>"></label>
        </div>
        <div class="form-grid-2">
          <label>WhatsApp<input name="whatsapp" inputmode="tel" value="<?= e($edit['whatsapp'] ?? '') ?>" placeholder="5585999999999"></label>
          <label>Localização<input name="localizacao" value="<?= e($edit['localizacao'] ?? 'Fortaleza - CE') ?>"></label>
        </div>
        <div class="form-grid-2">
          <label>Avaliação<input type="number" step="0.1" min="0" max="5" name="avaliacao" value="<?= e((string)($edit['avaliacao'] ?? 4.9)) ?>"></label>
          <label>Status<select name="status"><option value="ativo" <?= (($edit['status'] ?? 'ativo') === 'ativo') ? 'selected' : '' ?>>Ativo</option><option value="inativo" <?= (($edit['status'] ?? '') === 'inativo') ? 'selected' : '' ?>>Inativo</option></select></label>
        </div>
        <div><button class="btn btn-primary">Salvar anúncio</button> <a class="btn btn-outline-secondary" href="gerenciar_anuncios.php">Cancelar</a></div>
      </form>
    </div>
  <?php else: ?>
    <form class="admin-filter" method="get">
      <input name="q" value="<?= e($term) ?>" placeholder="Buscar anúncio">
      <select name="status"><option value="">Todos os status</option><option value="ativo" <?= $filter === 'ativo' ? 'selected' : '' ?>>Ativos</option><option value="inativo" <?= $filter === 'inativo' ? 'selected' : '' ?>>Inativos</option></select>
      <button class="btn btn-outline-secondary">Filtrar</button>
    </form>
    <div class="table-responsive">
      <table class="admin-table">
        <thead><tr><th>Negócio</th><th>Categoria</th><th>Tipo</th><th>Destaque</th><th>Avaliação</th><th>Status</th><th>Ações</th></tr></thead>
        <tbody>
        <?php foreach ($ads as $ad): ?>
          <tr>
            <td><strong><?= e($ad['titulo']) ?></strong><br><small class="muted"><?= e(mb_strimwidth($ad['descricao'], 0, 65, '…')) ?></small></td>
            <td><?= e($ad['categoria'] ?? '-') ?></td>
            <td><?= e($tipos[$ad['tipo'] ?? 'servico'] ?? '-') ?></td>
            <td><?= $ad['destaque_ordem'] !== null ? '★ ordem ' . (int)$ad['destaque_ordem'] : '—' ?></td>
            <td>★ <?= e((string)$ad['avaliacao']) ?></td>
            <td><span class="status status-<?= e($ad['status']) ?>"><?= e(ucfirst($ad['status'])) ?></span></td>
            <td class="actions"><a class="btn-action" href="?edit=<?= (int)$ad['id'] ?>">Editar</a> <a class="btn-action danger" href="?delete=<?= (int)$ad['id'] ?>&token=<?= e(csrf_token()) ?>" onclick="return confirm('Excluir este anúncio?')">Excluir</a></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$ads): ?><tr><td colspan="7" class="muted">Nenhum anúncio encontrado.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
  </div>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
