<?php
$admin_title = 'Histórico de versões';
$admin_perm = 'ler:versoes';
$admin_help = 'versoes';
require __DIR__ . '/includes/init.php';
$tipo = (string)($_GET['tipo'] ?? '');
$id = (int)($_GET['id'] ?? 0);
$ct = content_types()[$tipo] ?? null;
if (!$ct || !$id) { flash('error', 'Conteúdo não informado.'); redirect('dashboard.php'); }
deny_unless('ler:' . $ct['perm'], 'dashboard.php');

if (isset($_GET['restaurar']) && verify_csrf($_GET['token'] ?? null)) {
    deny_unless('restaurar:versoes', "versoes.php?tipo=$tipo&id=$id");
    deny_unless('editar:' . $ct['perm'], "versoes.php?tipo=$tipo&id=$id");
    $cur = db_one($pdo, "SELECT * FROM `{$ct['tabela']}` WHERE id=?", [$id]);
    if ($cur && isset($cur['estado']) && !workflow_can_edit($tipo, (string)$cur['estado'])) { flash('error', 'Este conteúdo está ' . workflow_states()[$cur['estado']][0] . ': somente quem pode publicar consegue restaurar versões.'); redirect("versoes.php?tipo=$tipo&id=$id"); }
    [$ok, $msg] = revision_restore((int)$_GET['restaurar'], $tipo, $id);
    flash($ok ? 'success' : 'error', $msg . ($ok && in_array($tipo, ['noticias', 'paginas'], true) ? ' O estado editorial não foi alterado.' : ''));
    redirect("versoes.php?tipo=$tipo&id=$id");
}
$current = db_one($pdo, "SELECT * FROM `{$ct['tabela']}` WHERE id=?", [$id]);
$list = revisions_list($tipo, $id);
$a = (int)($_GET['a'] ?? ($list[1]['id'] ?? 0)); // versão antiga
$b = (int)($_GET['b'] ?? ($list[0]['id'] ?? 0)); // versão nova
$revA = $a ? db_one($pdo, 'SELECT * FROM revisoes WHERE id=? AND recurso=? AND recurso_id=?', [$a, $tipo, $id]) : null;
$revB = $b ? db_one($pdo, 'SELECT * FROM revisoes WHERE id=? AND recurso=? AND recurso_id=?', [$b, $tipo, $id]) : null;
$dataA = $revA ? (json_decode((string)$revA['dados'], true) ?: []) : [];
$dataB = $revB ? (json_decode((string)$revB['dados'], true) ?: []) : [];
$fields = $ct['campos'] + ['estado' => 'Estado editorial'];
$title = $current[$ct['titulo']] ?? ($dataB[$ct['titulo']] ?? 'Conteúdo excluído');
include __DIR__ . '/includes/header.php';
?>
<main class="admin-content">
  <div class="admin-heading">
    <div><h1>Versões: <?= e($title) ?></h1><p class="muted"><?= e($ct['rotulo']) ?> #<?= $id ?> · <?= count($list) ?> versão(ões) registrada(s). Compare duas versões e restaure a que desejar.</p></div>
    <?php if ($current): ?><a class="btn btn-outline-secondary" href="<?= e($ct['admin']) ?>?edit=<?= $id ?>">← Voltar à edição</a><?php endif; ?>
  </div>
  <div class="split">
    <div class="admin-panel">
      <?php if ($revA && $revB && $revA['id'] !== $revB['id']): ?>
      <h2>Comparando versão <?= (int)$revA['numero'] ?> → <?= (int)$revB['numero'] ?></h2>
      <p class="muted"><del style="background:#fde2e2">texto removido</del> · <ins style="background:#d7f5dd;text-decoration:none">texto adicionado</ins></p>
      <div class="table-responsive">
        <table class="admin-table diff-table">
          <thead><tr><th style="width:170px">Campo</th><th>Alteração</th></tr></thead>
          <tbody>
          <?php $changes = 0; foreach ($fields as $k => $label):
              $va = (string)($dataA[$k] ?? ''); $vb = (string)($dataB[$k] ?? '');
              if ($va === $vb) continue; $changes++; ?>
            <tr><td><strong><?= e($label) ?></strong></td><td><?= text_diff($va, $vb) ?></td></tr>
          <?php endforeach; ?>
          <?php if (!$changes): ?><tr><td colspan="2" class="diff-same">As duas versões têm o mesmo conteúdo.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
      <?php elseif ($revB): ?>
      <h2>Versão <?= (int)$revB['numero'] ?></h2>
      <table class="admin-table diff-table"><tbody><?php foreach ($fields as $k => $label): if (!isset($dataB[$k]) || $dataB[$k] === '') continue; ?><tr><td style="width:170px"><strong><?= e($label) ?></strong></td><td><?= nl2br(e(mb_strimwidth((string)$dataB[$k], 0, 2000, '…'))) ?></td></tr><?php endforeach; ?></tbody></table>
      <?php else: ?>
      <p class="muted">Ainda não há versões registradas. Elas são criadas automaticamente a cada vez que o conteúdo é salvo.</p>
      <?php endif; ?>
    </div>
    <aside class="side-card">
      <h3>Versões</h3>
      <form method="get" class="mb-2">
        <input type="hidden" name="tipo" value="<?= e($tipo) ?>"><input type="hidden" name="id" value="<?= $id ?>">
        <table class="admin-table" style="font-size:12px">
          <thead><tr><th title="Versão antiga">A</th><th title="Versão nova">B</th><th>Versão</th></tr></thead>
          <tbody>
          <?php foreach ($list as $i => $r): ?>
            <tr>
              <td><input type="radio" name="a" value="<?= (int)$r['id'] ?>" <?= $a === (int)$r['id'] ? 'checked' : '' ?> aria-label="Comparar como versão A"></td>
              <td><input type="radio" name="b" value="<?= (int)$r['id'] ?>" <?= $b === (int)$r['id'] ? 'checked' : '' ?> aria-label="Comparar como versão B"></td>
              <td style="white-space:normal"><strong>v<?= (int)$r['numero'] ?></strong> <?= $i === 0 ? '<span class="badge-soft ok">atual</span>' : '' ?><br><?= e($r['resumo']) ?><br><small class="muted"><?= e($r['usuario_nome']) ?> · <?= e(date('d/m/Y H:i', strtotime($r['criado_em']))) ?></small>
                <?php if ($i > 0 && can('restaurar:versoes') && $current): ?><br><a class="btn-action mt-1" href="?tipo=<?= e($tipo) ?>&id=<?= $id ?>&restaurar=<?= (int)$r['id'] ?>&token=<?= e(csrf_token()) ?>" data-confirm="Restaurar a versão <?= (int)$r['numero'] ?>? O conteúdo atual continuará salvo no histórico." data-confirm-style="primary" data-confirm-button="Restaurar">Restaurar esta versão</a><?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        <?php if (count($list) > 1): ?><button class="btn btn-sm btn-primary w-100">Comparar A → B</button><?php endif; ?>
      </form>
    </aside>
  </div>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
