<?php
$admin_title = 'Biblioteca de mídia';
require __DIR__ . '/includes/init.php';
$pasta = (int)($_GET['pasta'] ?? 0);
$back = 'midia.php' . ($pasta ? '?pasta=' . $pasta : '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) { flash('error', 'Sessão expirada.'); redirect($back); }
    $act = $_POST['acao'] ?? '';
    if ($act === 'enviar') {
        deny_unless('enviar:midia', $back);
        $files = $_FILES['arquivos'] ?? null;
        $ok = 0; $errs = [];
        if ($files && is_array($files['name'])) {
            foreach ($files['name'] as $i => $n) {
                $f = ['name' => $n, 'type' => $files['type'][$i], 'tmp_name' => $files['tmp_name'][$i], 'error' => $files['error'][$i], 'size' => $files['size'][$i]];
                try { if (media_store_upload($f, 'arquivo', 2400, 2400, $pasta ?: null, false)) $ok++; } catch (Throwable $e) { $errs[] = "$n: " . $e->getMessage(); }
            }
        }
        flash($errs ? 'error' : 'success', ($ok ? "$ok arquivo(s) enviado(s). " : '') . implode(' ', $errs));
        redirect($back);
    }
    if ($act === 'pasta') {
        deny_unless('enviar:midia', $back);
        $nome = trim((string)($_POST['nome'] ?? ''));
        if ($nome !== '') { $pdo->prepare('INSERT INTO midia_pastas (nome) VALUES (?)')->execute([mb_substr($nome, 0, 100)]); flash('success', "Pasta “{$nome}” criada."); redirect('midia.php?pasta=' . (int)$pdo->lastInsertId()); }
        redirect($back);
    }
    if ($act === 'renomear_pasta') {
        deny_unless('enviar:midia', $back);
        $pdo->prepare('UPDATE midia_pastas SET nome=? WHERE id=?')->execute([mb_substr(trim((string)$_POST['nome']), 0, 100), $pasta]);
        flash('success', 'Pasta renomeada.'); redirect($back);
    }
    if ($act === 'mover' || $act === 'excluir') {
        $ids = array_map('intval', (array)($_POST['ids'] ?? []));
        if (!$ids) { flash('error', 'Selecione ao menos um arquivo.'); redirect($back); }
        if ($act === 'mover') {
            deny_unless('enviar:midia', $back);
            $dest = (int)($_POST['destino'] ?? 0) ?: null;
            $in = implode(',', array_fill(0, count($ids), '?'));
            $pdo->prepare("UPDATE midias SET pasta_id=? WHERE id IN ($in)")->execute([$dest, ...$ids]);
            flash('success', count($ids) . ' arquivo(s) movido(s).');
        } else {
            deny_unless('excluir:midia', $back);
            $last = null; $inUse = 0;
            foreach ($ids as $id) {
                $m = db_one($pdo, 'SELECT * FROM midias WHERE id=?', [$id]);
                if (!$m) continue;
                if (media_usage($m['arquivo'])) $inUse++;
                $last = trash_delete('midias', $id, $m['nome'], 'midia');
            }
            flash('success', count($ids) . ' arquivo(s) enviado(s) para a lixeira.' . ($inUse ? " Atenção: $inUse estava(m) em uso e deixará(ão) de aparecer no site." : ''), count($ids) === 1 ? undo_link($last, $back) : '');
        }
        redirect($back);
    }
    if ($act === 'editar') {
        deny_unless('enviar:midia', $back);
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare('UPDATE midias SET alt=?, titulo=?, pasta_id=? WHERE id=?')->execute([mb_substr(trim((string)$_POST['alt']), 0, 255), mb_substr(trim((string)$_POST['titulo']), 0, 190), (int)$_POST['pasta_id'] ?: null, $id]);
        $crop = ['x' => (int)($_POST['crop_x'] ?? 0), 'y' => (int)($_POST['crop_y'] ?? 0), 'w' => (int)($_POST['crop_w'] ?? 0), 'h' => (int)($_POST['crop_h'] ?? 0)];
        $width = (int)($_POST['largura'] ?? 0);
        $m = db_one($pdo, 'SELECT largura FROM midias WHERE id=?', [$id]);
        $resize = $width > 0 && $width !== (int)($m['largura'] ?? 0);
        if ($crop['w'] > 0 || $resize) {
            [$ok, $msg, $newId] = media_edit_image($id, $crop, $resize ? $width : null, !empty($_POST['como_copia'])) + [2 => $id];
            flash($ok ? 'success' : 'error', $msg);
            redirect('midia.php?ver=' . $newId);
        }
        audit('midia_editada', 'midia', $id, ['campos' => 'alt/título/pasta']);
        flash('success', 'Informações salvas.');
        redirect('midia.php?ver=' . $id);
    }
}
if (isset($_GET['excluir_pasta']) && verify_csrf($_GET['token'] ?? null)) {
    deny_unless('excluir:midia', 'midia.php');
    $f = db_one($pdo, 'SELECT * FROM midia_pastas WHERE id=?', [(int)$_GET['excluir_pasta']]);
    if ($f) {
        $pdo->prepare('UPDATE midias SET pasta_id=NULL WHERE pasta_id=?')->execute([$f['id']]);
        $tid = trash_delete('midia_pastas', (int)$f['id'], 'Pasta ' . $f['nome'], 'midia_pastas');
        flash('success', "Pasta “{$f['nome']}” excluída. Os arquivos foram mantidos em “Sem pasta”.", undo_link($tid, 'midia.php'));
    }
    redirect('midia.php');
}

$q = trim((string)($_GET['q'] ?? ''));
$tipo = (string)($_GET['tipo'] ?? '');
$sql = 'SELECT m.*, u.nome autor FROM midias m LEFT JOIN usuarios u ON u.id=m.usuario_id WHERE 1=1';
$p = [];
if ($pasta === -1) $sql .= ' AND m.pasta_id IS NULL';
elseif ($pasta) { $sql .= ' AND m.pasta_id=?'; $p[] = $pasta; }
if ($q !== '') { $sql .= ' AND (m.nome LIKE ? OR m.alt LIKE ? OR m.titulo LIKE ?)'; array_push($p, "%$q%", "%$q%", "%$q%"); }
if ($tipo === 'imagem') $sql .= " AND m.mime LIKE 'image/%'";
if ($tipo === 'documento') $sql .= " AND m.mime NOT LIKE 'image/%'";
$items = db_rows($pdo, $sql . ' ORDER BY m.id DESC LIMIT 300', $p);
$folders = media_folders();
$folderName = '';
foreach ($folders as $f) if ((int)$f['id'] === $pasta) $folderName = $f['nome'];
$total = (int)(db_one($pdo, 'SELECT COUNT(*) n FROM midias')['n'] ?? 0);
$view = isset($_GET['ver']) ? db_one($pdo, 'SELECT m.*, u.nome autor FROM midias m LEFT JOIN usuarios u ON u.id=m.usuario_id WHERE m.id=?', [(int)$_GET['ver']]) : null;
$usage = $view ? media_usage($view['arquivo']) : [];
include __DIR__ . '/includes/header.php';
?>
<main class="admin-content">
  <div class="admin-heading">
    <div><h1>Biblioteca de mídia</h1><p class="muted"><?= $total ?> arquivo(s). Organize em pastas, edite textos alternativos (acessibilidade), corte e redimensione imagens e veja onde cada arquivo é usado.</p></div>
  </div>

  <?php if ($view): $isImg = str_starts_with($view['mime'], 'image/') && $view['mime'] !== 'image/gif'; ?>
  <div class="admin-panel mb-4 edit-panel is-editing">
    <div class="edit-panel-head"><span class="edit-badge">✎ Editando</span><h2><?= e($view['nome']) ?></h2><a class="btn btn-sm btn-outline-secondary ms-auto" href="<?= e($back) ?>">Fechar</a></div>
    <div class="split">
      <div>
        <?php if ($isImg): ?>
        <div class="crop-stage" data-crop-stage data-w="<?= (int)$view['largura'] ?>" data-h="<?= (int)$view['altura'] ?>">
          <img src="../<?= e($view['arquivo']) ?>?v=<?= (int)$view['tamanho'] ?>" alt="<?= e($view['alt']) ?>" data-crop-img>
          <div class="crop-box" data-crop-box hidden><span class="crop-handle" data-crop-handle></span></div>
        </div>
        <p class="muted small mt-2">Para cortar: clique em “Cortar”, ajuste a área arrastando (o canto inferior direito muda o tamanho) e salve.</p>
        <?php elseif (str_starts_with($view['mime'], 'image/')): ?><img src="../<?= e($view['arquivo']) ?>" alt="" style="max-width:100%">
        <?php else: ?><p><a class="btn btn-outline-secondary" href="../<?= e($view['arquivo']) ?>" target="_blank">Abrir arquivo</a></p><?php endif; ?>
      </div>
      <form method="post" class="admin-form" style="padding:0">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="acao" value="editar"><input type="hidden" name="id" value="<?= (int)$view['id'] ?>">
        <?php foreach (['x', 'y', 'w', 'h'] as $c): ?><input type="hidden" name="crop_<?= $c ?>" data-crop-<?= $c ?>><?php endforeach; ?>
        <p class="small mb-0"><strong>Arquivo:</strong> <code><?= e($view['arquivo']) ?></code><br><?= e($view['mime']) ?> · <?= e(format_bytes((int)$view['tamanho'])) ?><?= $view['largura'] ? ' · ' . (int)$view['largura'] . '×' . (int)$view['altura'] . ' px' : '' ?><br>Enviado por <?= e($view['autor'] ?: '—') ?> em <?= e(date('d/m/Y H:i', strtotime($view['criado_em']))) ?></p>
        <label>Texto alternativo (alt) <small class="muted">Descreva a imagem para leitores de tela.</small><input name="alt" value="<?= e($view['alt']) ?>" maxlength="255"></label>
        <label>Título<input name="titulo" value="<?= e($view['titulo']) ?>" maxlength="190"></label>
        <label>Pasta<select name="pasta_id"><option value="">Sem pasta</option><?php foreach ($folders as $f): ?><option value="<?= (int)$f['id'] ?>" <?= (int)$view['pasta_id'] === (int)$f['id'] ? 'selected' : '' ?>><?= e($f['nome']) ?></option><?php endforeach; ?></select></label>
        <?php if ($isImg && can('enviar:midia')): ?>
        <label>Largura (px) <small class="muted">Reduz mantendo a proporção.</small><input type="number" name="largura" min="16" max="<?= (int)$view['largura'] ?>" value="<?= (int)$view['largura'] ?>"></label>
        <div class="d-flex gap-2 flex-wrap"><button type="button" class="btn btn-sm btn-outline-secondary" data-crop-start>✂ Cortar</button><button type="button" class="btn btn-sm btn-outline-secondary" data-crop-ratio="1">1:1</button><button type="button" class="btn btn-sm btn-outline-secondary" data-crop-ratio="1.7778">16:9</button><button type="button" class="btn btn-sm btn-outline-secondary" data-crop-ratio="1.905">Open Graph</button><button type="button" class="btn btn-sm btn-link" data-crop-cancel hidden>Cancelar corte</button></div>
        <label class="check-row"><input type="checkbox" name="como_copia" value="1" checked> Salvar corte/redimensionamento como uma cópia (mantém o original)</label>
        <?php endif; ?>
        <input class="form-control form-control-sm" readonly value="<?= e(site_base_url() . $view['arquivo']) ?>" onclick="this.select()" aria-label="Endereço do arquivo">
        <?php if (can('enviar:midia')): ?><button class="btn btn-primary">Salvar</button><?php endif; ?>
        <div class="muted-box small">
          <strong>Onde é usado (<?= count($usage) ?>)</strong>
          <?php if ($usage): ?><ul class="mb-0 mt-1"><?php foreach ($usage as $u): ?><li><?= e($u['tipo']) ?>: <a href="<?= e($u['link']) ?>"><?= e($u['titulo']) ?></a></li><?php endforeach; ?></ul><?php else: ?><br>Não está sendo usado em nenhum conteúdo.<?php endif; ?>
        </div>
      </form>
    </div>
  </div>
  <?php endif; ?>

  <div class="split" style="grid-template-columns:230px minmax(0,1fr)">
    <aside class="side-card" style="position:static">
      <h3>Pastas</h3>
      <ul class="folder-list">
        <li><a class="<?= !$pasta ? 'active' : '' ?>" href="midia.php">Todos os arquivos <span><?= $total ?></span></a></li>
        <li><a class="<?= $pasta === -1 ? 'active' : '' ?>" href="?pasta=-1">Sem pasta</a></li>
        <?php foreach ($folders as $f): ?><li><a class="<?= $pasta === (int)$f['id'] ? 'active' : '' ?>" href="?pasta=<?= (int)$f['id'] ?>">📁 <?= e($f['nome']) ?> <span><?= (int)$f['total'] ?></span></a></li><?php endforeach; ?>
      </ul>
      <?php if (can('enviar:midia')): ?>
      <form method="post" class="mt-3 d-flex gap-1"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="acao" value="pasta"><input class="form-control form-control-sm" name="nome" placeholder="Nova pasta" required aria-label="Nome da nova pasta"><button class="btn btn-sm btn-outline-secondary">+</button></form>
      <?php endif; ?>
      <?php if ($pasta > 0): ?>
      <hr><form method="post" class="d-flex gap-1"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="acao" value="renomear_pasta"><input class="form-control form-control-sm" name="nome" value="<?= e($folderName) ?>" aria-label="Renomear pasta"><button class="btn btn-sm btn-outline-secondary">✓</button></form>
      <?php if (can('excluir:midia')): ?><a class="btn-action danger mt-2" href="?excluir_pasta=<?= $pasta ?>&token=<?= e(csrf_token()) ?>" data-confirm="Excluir a pasta “<?= e($folderName) ?>”? Os arquivos não serão apagados.">Excluir pasta</a><?php endif; ?>
      <?php endif; ?>
    </aside>
    <div class="admin-panel">
      <?php if (can('enviar:midia')): ?>
      <form method="post" enctype="multipart/form-data" class="upload-drop mb-3" data-drop>
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="acao" value="enviar">
        <label class="mb-0"><strong>⬆ Arraste arquivos aqui ou clique para escolher</strong><br><span class="muted small">JPG, PNG, WebP, GIF ou PDF · até <?= (int)setting($pdo, 'midia_max_mb', '8') ?> MB cada<?= $folderName ? ' · enviando para “' . e($folderName) . '”' : '' ?></span>
          <input type="file" name="arquivos[]" multiple accept="image/jpeg,image/png,image/webp,image/gif,application/pdf" hidden onchange="this.form.submit()"></label>
      </form>
      <?php endif; ?>
      <form class="admin-filter" method="get">
        <?php if ($pasta): ?><input type="hidden" name="pasta" value="<?= $pasta ?>"><?php endif; ?>
        <input name="q" value="<?= e($q) ?>" placeholder="Buscar por nome, alt ou título">
        <select name="tipo"><option value="">Todos os tipos</option><option value="imagem" <?= $tipo === 'imagem' ? 'selected' : '' ?>>Imagens</option><option value="documento" <?= $tipo === 'documento' ? 'selected' : '' ?>>Documentos</option></select>
        <button class="btn btn-outline-secondary">Buscar</button>
      </form>
      <form method="post" id="bulkMedia">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <div class="d-flex gap-2 flex-wrap align-items-center mb-3">
          <?php if (can('enviar:midia')): ?><select name="destino" class="form-select form-select-sm" style="max-width:200px" aria-label="Mover para"><option value="">Mover para: Sem pasta</option><?php foreach ($folders as $f): ?><option value="<?= (int)$f['id'] ?>">Mover para: <?= e($f['nome']) ?></option><?php endforeach; ?></select><button class="btn btn-sm btn-outline-secondary" name="acao" value="mover">Mover selecionados</button><?php endif; ?>
          <?php if (can('excluir:midia')): ?><button class="btn btn-sm btn-outline-danger" name="acao" value="excluir" data-confirm="Enviar os arquivos selecionados para a lixeira? Se estiverem em uso, deixarão de aparecer no site.">Excluir selecionados</button><?php endif; ?>
        </div>
        <div class="media-grid">
          <?php foreach ($items as $m): $isImage = str_starts_with($m['mime'], 'image/'); ?>
          <div class="media-card<?= $view && (int)$view['id'] === (int)$m['id'] ? ' selected' : '' ?>">
            <input class="sel" type="checkbox" name="ids[]" value="<?= (int)$m['id'] ?>" aria-label="Selecionar <?= e($m['nome']) ?>">
            <a class="thumb" href="?ver=<?= (int)$m['id'] ?><?= $pasta ? '&pasta=' . $pasta : '' ?>"><?php if ($isImage): ?><img src="../<?= e($m['arquivo']) ?>" alt="<?= e($m['alt']) ?>" loading="lazy"><?php else: ?><span class="mp-doc" style="width:100%">PDF</span><?php endif; ?></a>
            <div class="info"><strong title="<?= e($m['nome']) ?>"><?= e($m['nome']) ?></strong><span class="muted"><?= $m['largura'] ? (int)$m['largura'] . '×' . (int)$m['altura'] . ' · ' : '' ?><?= e(format_bytes((int)$m['tamanho'])) ?></span><?php if ($isImage && trim((string)$m['alt']) === ''): ?><span class="badge-soft warn" title="Adicione um texto alternativo">sem alt</span><?php endif; ?></div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php if (!$items): ?><p class="muted">Nenhum arquivo aqui ainda.</p><?php endif; ?>
      </form>
    </div>
  </div>
</main>
<script>
/* Arrastar e soltar para enviar */
document.querySelectorAll('[data-drop]').forEach(z => {
  ['dragenter', 'dragover'].forEach(ev => z.addEventListener(ev, e => { e.preventDefault(); z.classList.add('over'); }));
  ['dragleave', 'drop'].forEach(ev => z.addEventListener(ev, e => { e.preventDefault(); z.classList.remove('over'); }));
  z.addEventListener('drop', e => { const inp = z.querySelector('input[type=file]'); inp.files = e.dataTransfer.files; z.submit(); });
});
/* Ferramenta de corte */
(() => {
  const stage = document.querySelector('[data-crop-stage]'); if (!stage) return;
  const img = stage.querySelector('[data-crop-img]'), box = stage.querySelector('[data-crop-box]'), handle = stage.querySelector('[data-crop-handle]');
  const realW = +stage.dataset.w, realH = +stage.dataset.h;
  const f = k => document.querySelector('[data-crop-' + k + ']');
  let r = { x: 0, y: 0, w: 0, h: 0 }, ratio = 0, drag = null;
  const scale = () => realW / img.clientWidth;
  const sync = () => {
    Object.assign(box.style, { left: r.x + 'px', top: r.y + 'px', width: r.w + 'px', height: r.h + 'px' });
    const s = scale(); f('x').value = Math.round(r.x * s); f('y').value = Math.round(r.y * s); f('w').value = Math.round(r.w * s); f('h').value = Math.round(r.h * s);
  };
  const start = () => { const W = img.clientWidth, H = img.clientHeight; r.w = W * .8; r.h = ratio ? Math.min(H * .8, r.w / ratio) : H * .8; if (ratio) r.w = r.h * ratio; r.x = (W - r.w) / 2; r.y = (H - r.h) / 2; box.hidden = false; document.querySelector('[data-crop-cancel]').hidden = false; sync(); };
  document.querySelector('[data-crop-start]')?.addEventListener('click', () => { ratio = 0; start(); });
  document.querySelectorAll('[data-crop-ratio]').forEach(b => b.addEventListener('click', () => { ratio = +b.dataset.cropRatio; start(); }));
  document.querySelector('[data-crop-cancel]')?.addEventListener('click', e => { box.hidden = true; e.target.hidden = true; ['x', 'y', 'w', 'h'].forEach(k => f(k).value = ''); });
  const pt = e => { const b = img.getBoundingClientRect(); const p = e.touches ? e.touches[0] : e; return { x: p.clientX - b.left, y: p.clientY - b.top }; };
  const down = (e, mode) => { e.preventDefault(); e.stopPropagation(); drag = { mode, p: pt(e), r: { ...r } }; };
  box.addEventListener('pointerdown', e => down(e, 'move'));
  handle.addEventListener('pointerdown', e => down(e, 'resize'));
  window.addEventListener('pointermove', e => {
    if (!drag) return;
    const p = pt(e), dx = p.x - drag.p.x, dy = p.y - drag.p.y, W = img.clientWidth, H = img.clientHeight;
    if (drag.mode === 'move') { r.x = Math.max(0, Math.min(W - r.w, drag.r.x + dx)); r.y = Math.max(0, Math.min(H - r.h, drag.r.y + dy)); }
    else { r.w = Math.max(20, Math.min(W - r.x, drag.r.w + dx)); r.h = ratio ? r.w / ratio : Math.max(20, Math.min(H - r.y, drag.r.h + dy)); if (r.y + r.h > H) { r.h = H - r.y; if (ratio) r.w = r.h * ratio; } }
    sync();
  });
  window.addEventListener('pointerup', () => { drag = null; });
})();
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
