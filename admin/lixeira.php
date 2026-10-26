<?php
$admin_title = 'Lixeira';
// O link "Desfazer" pode ser usado por quem acabou de excluir, mesmo sem acesso à lixeira.
$admin_perm = 'acessar:painel';
require __DIR__ . '/includes/init.php';

$back = (string)($_GET['voltar'] ?? 'lixeira.php');
if (!preg_match('/^[a-z_]+\.php(\?[^\s]*)?$/', $back)) $back = 'lixeira.php';

if (isset($_GET['restaurar']) && verify_csrf($_GET['token'] ?? null)) {
    $item = db_one($pdo, 'SELECT * FROM lixeira WHERE id=?', [(int)$_GET['restaurar']]);
    $own = $item && (int)$item['usuario_id'] === (int)$me['id'] && strtotime($item['criado_em']) > time() - 900;
    if (!$item) flash('error', 'Item não encontrado na lixeira.');
    elseif (!can('restaurar:lixeira') && !$own) flash('error', 'Você não tem permissão para restaurar itens da lixeira.');
    else { [$ok, $msg] = trash_restore((int)$item['id']); flash($ok ? 'success' : 'error', $msg); }
    redirect($back);
}
if (!can('ler:lixeira')) { include __DIR__ . '/includes/header.php'; echo '<main class="admin-content"><div class="forbidden-box"><h1>Acesso restrito</h1><p>Você não tem permissão para ver a lixeira.</p></div></main>'; include __DIR__ . '/includes/footer.php'; exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) { flash('error', 'Sessão expirada.'); redirect('lixeira.php'); }
    $ids = array_map('intval', (array)($_POST['ids'] ?? []));
    $act = $_POST['acao'] ?? '';
    if ($act === 'esvaziar') { $ids = array_map('intval', array_column(db_rows($pdo, 'SELECT id FROM lixeira'), 'id')); $act = 'excluir'; }
    if (!$ids) { flash('error', 'Selecione ao menos um item.'); redirect('lixeira.php'); }
    if ($act === 'restaurar') {
        deny_unless('restaurar:lixeira', 'lixeira.php');
        $ok = 0; $errs = [];
        foreach ($ids as $id) { [$r, $m] = trash_restore($id); $r ? $ok++ : $errs[] = $m; }
        flash($errs ? 'error' : 'success', "$ok item(ns) restaurado(s)." . ($errs ? ' ' . implode(' ', array_unique($errs)) : ''));
    } elseif ($act === 'excluir') {
        deny_unless('esvaziar:lixeira', 'lixeira.php');
        foreach ($ids as $id) trash_purge($id);
        flash('success', count($ids) . ' item(ns) excluído(s) definitivamente.');
    }
    redirect('lixeira.php');
}
$tipo = (string)($_GET['tipo'] ?? '');
$items = db_rows($pdo, 'SELECT id,tabela,registro_id,recurso,rotulo,usuario_nome,criado_em FROM lixeira' . ($tipo !== '' ? ' WHERE recurso=?' : '') . ' ORDER BY id DESC LIMIT 500', $tipo !== '' ? [$tipo] : []);
$types = array_column(db_rows($pdo, 'SELECT recurso, COUNT(*) n FROM lixeira GROUP BY recurso ORDER BY recurso'), 'n', 'recurso');
$days = (int)setting($pdo, 'lixeira_dias', '30');
$labels = ['anuncios' => 'Anúncio', 'noticias' => 'Notícia', 'paginas' => 'Página', 'categorias' => 'Categoria', 'banners' => 'Banner', 'banner_beneficios' => 'Texto do banner', 'redes_sociais' => 'Rede social', 'menu_itens' => 'Item de menu', 'usuarios' => 'Usuário', 'papeis' => 'Papel', 'idiomas' => 'Idioma', 'midia' => 'Mídia', 'midia_pastas' => 'Pasta de mídia'];
include __DIR__ . '/includes/header.php';
?>
<main class="admin-content">
  <div class="admin-heading">
    <div><h1>Lixeira</h1><p class="muted">Tudo o que é excluído no painel fica aqui por <?= $days ?> dias e pode ser restaurado. Depois disso, é apagado definitivamente.</p></div>
  </div>
  <div class="admin-panel">
    <?php if ($types): ?>
    <div class="admin-tabs" style="margin-top:0"><a class="<?= $tipo === '' ? 'active' : '' ?>" href="lixeira.php">Todos (<?= array_sum($types) ?>)</a><?php foreach ($types as $t => $n): ?><a class="<?= $tipo === $t ? 'active' : '' ?>" href="?tipo=<?= urlencode($t) ?>"><?= e($labels[$t] ?? $t) ?> (<?= $n ?>)</a><?php endforeach; ?></div>
    <?php endif; ?>
    <form method="post" id="trashForm">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <div class="d-flex gap-2 flex-wrap mb-3">
        <?php if (can('restaurar:lixeira')): ?><button class="btn btn-primary btn-sm" name="acao" value="restaurar">↶ Restaurar selecionados</button><?php endif; ?>
        <?php if (can('esvaziar:lixeira')): ?>
          <button class="btn btn-outline-danger btn-sm" name="acao" value="excluir" data-confirm="Excluir definitivamente os itens selecionados? Esta ação NÃO pode ser desfeita.">Excluir definitivamente</button>
          <?php if ($items): ?><button class="btn btn-link text-danger btn-sm ms-auto" name="acao" value="esvaziar" data-confirm="Esvaziar a lixeira inteira? Todos os itens serão apagados para sempre." data-confirm-word="ESVAZIAR">Esvaziar lixeira</button><?php endif; ?>
        <?php endif; ?>
      </div>
      <div class="table-responsive">
        <table class="admin-table">
          <thead><tr><th style="width:30px"><input type="checkbox" aria-label="Selecionar todos" onclick="document.querySelectorAll('[name=\'ids[]\']').forEach(c=>c.checked=this.checked)"></th><th>Item</th><th>Tipo</th><th>Excluído por</th><th>Quando</th><th>Expira em</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($items as $i): $left = $days - (int)floor((time() - strtotime($i['criado_em'])) / 86400); ?>
            <tr>
              <td><input type="checkbox" name="ids[]" value="<?= (int)$i['id'] ?>" aria-label="Selecionar <?= e($i['rotulo']) ?>"></td>
              <td><strong><?= e($i['rotulo']) ?></strong></td>
              <td><?= e($labels[$i['recurso']] ?? $i['recurso']) ?></td>
              <td><?= e($i['usuario_nome']) ?></td>
              <td><?= e(date('d/m/Y H:i', strtotime($i['criado_em']))) ?></td>
              <td><span class="badge-soft <?= $left <= 3 ? 'warn' : '' ?>"><?= max(0, $left) ?> dia(s)</span></td>
              <td class="actions"><?php if (can('restaurar:lixeira')): ?><a class="btn-action" href="?restaurar=<?= (int)$i['id'] ?>&token=<?= e(csrf_token()) ?>">Restaurar</a><?php endif; ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$items): ?><tr><td colspan="7" class="muted">A lixeira está vazia.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </form>
  </div>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
