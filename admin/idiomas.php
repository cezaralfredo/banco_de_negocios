<?php
$admin_title = 'Idiomas e traduções';
include __DIR__ . '/includes/header.php';
$msg = '';
$erro = '';

/* ---------- Idiomas ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo) {
    $action = $_POST['action'] ?? '';
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $erro = 'Token de segurança inválido.';
    } elseif ($action === 'language') {
        $id = (int)($_POST['id'] ?? 0);
        $codigo = strtolower(trim($_POST['codigo'] ?? ''));
        $nome = trim($_POST['nome'] ?? '');
        $bandeira = mb_substr(trim($_POST['bandeira'] ?? ''), 0, 8);
        $ordem = max(0, (int)($_POST['ordem'] ?? 0));
        $ativo = isset($_POST['ativo']) ? 1 : 0;
        if (!preg_match('/^[a-z]{2}(-[a-z]{2})?$/', $codigo)) $erro = 'Código inválido. Use o padrão ISO, ex.: en, es, fr, it, de ou pt-pt.';
        elseif ($nome === '') $erro = 'Informe o nome do idioma.';
        elseif (db_one($pdo, 'SELECT id FROM idiomas WHERE codigo=? AND id<>?', [$codigo, $id])) $erro = 'Este código de idioma já está cadastrado.';
        else {
            $old = $id ? db_one($pdo, 'SELECT * FROM idiomas WHERE id=?', [$id]) : null;
            if ($old && (int)$old['padrao'] === 1) $ativo = 1; // o idioma padrão não pode ser desativado
            if ($id) {
                $pdo->prepare('UPDATE idiomas SET codigo=?,nome=?,bandeira=?,ordem=?,ativo=? WHERE id=?')->execute([$codigo, $nome, $bandeira, $ordem, $ativo, $id]);
                if ($old && $old['codigo'] !== $codigo) $pdo->prepare('UPDATE traducoes SET idioma=? WHERE idioma=?')->execute([$codigo, $old['codigo']]);
            } else {
                $pdo->prepare('INSERT INTO idiomas(codigo,nome,bandeira,ordem,ativo,padrao) VALUES(?,?,?,?,?,0)')->execute([$codigo, $nome, $bandeira, $ordem, $ativo]);
            }
            $msg = 'Idioma salvo.';
        }
    } elseif ($action === 'default') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->exec('UPDATE idiomas SET padrao=0');
        $pdo->prepare('UPDATE idiomas SET padrao=1, ativo=1 WHERE id=?')->execute([$id]);
        $msg = 'Idioma padrão atualizado.';
    } elseif ($action === 'translations') {
        $lang = (string)($_POST['lang'] ?? '');
        if (!db_one($pdo, 'SELECT id FROM idiomas WHERE codigo=?', [$lang])) {
            $erro = 'Idioma inválido.';
        } else {
            $up = $pdo->prepare('INSERT INTO traducoes(chave,idioma,valor) VALUES(?,?,?) ON DUPLICATE KEY UPDATE valor=VALUES(valor)');
            $del = $pdo->prepare('DELETE FROM traducoes WHERE chave=? AND idioma=?');
            $n = 0;
            foreach ((array)($_POST['tr'] ?? []) as $key => $value) {
                $key = (string)$key;
                if (!preg_match('/^[a-z0-9_.]+$/i', $key) || strlen($key) > 190) continue;
                $value = trim(str_replace("\r", '', (string)$value));
                if ($value === '') $del->execute([$key, $lang]);
                else { $up->execute([$key, $lang, $value]); $n++; }
            }
            $msg = "Traduções salvas ({$n} textos preenchidos).";
        }
    }
}
if (isset($_GET['delete']) && $pdo && verify_csrf($_GET['token'] ?? null)) {
    $l = db_one($pdo, 'SELECT * FROM idiomas WHERE id=?', [(int)$_GET['delete']]);
    if ($l && !(int)$l['padrao']) {
        $pdo->prepare('DELETE FROM traducoes WHERE idioma=?')->execute([$l['codigo']]);
        $pdo->prepare('DELETE FROM idiomas WHERE id=?')->execute([$l['id']]);
        $msg = 'Idioma e suas traduções foram excluídos.';
    } else {
        $erro = 'O idioma padrão não pode ser excluído.';
    }
}
$langs = db_rows($pdo, 'SELECT i.*, (SELECT COUNT(*) FROM traducoes t WHERE t.idioma=i.codigo) total FROM idiomas i ORDER BY ordem,id');
$defaultLang = '';
foreach ($langs as $l) if ((int)$l['padrao']) $defaultLang = $l['codigo'];
$editLang = isset($_GET['edit']) ? db_one($pdo, 'SELECT * FROM idiomas WHERE id=?', [(int)$_GET['edit']]) : null;

/* ---------- Textos traduzíveis ---------- */
$trLang = (string)($_GET['lang'] ?? '');
if (!in_array($trLang, array_column($langs, 'codigo'), true)) {
    $trLang = '';
    foreach ($langs as $l) if (!(int)$l['padrao']) { $trLang = $l['codigo']; break; }
    if ($trLang === '') $trLang = $defaultLang;
}
$base = require __DIR__ . '/../includes/i18n.php';
$groupNames = ['top' => 'Barra superior', 'nav' => 'Navegação', 'a11y' => 'Acessibilidade', 'hero' => 'Banner', 'search' => 'Busca', 'home' => 'Home', 'card' => 'Cards de anúncio', 'list' => 'Listagens', 'page' => 'Páginas internas', 'profile' => 'Perfil', 'news' => 'Notícias', 'cta' => 'Promoção', 'signup' => 'Cadastro', 'footer' => 'Rodapé', 'cookie' => 'Cookies (LGPD)', 'conteudo' => 'Conteúdo do site'];
$rows = [];
foreach ($base as $k => $txt) $rows[] = ['key' => $k, 'group' => strtok($k, '.'), 'base' => $txt, 'long' => mb_strlen($txt) > 90];

// Conteúdo cadastrado no painel (traduzido pela função tc())
$content = [];
$addContent = function (string $key, ?string $text, string $label) use (&$content) { if (trim((string)$text) !== '') $content[] = ['key' => 'conteudo.' . $key, 'group' => 'conteudo', 'base' => (string)$text, 'label' => $label, 'long' => mb_strlen((string)$text) > 90 || str_contains((string)$text, "\n")]; };
$addContent('topbar_text', setting($pdo, 'topbar_text', 'Bem-vindo ao Banco de Negócios!'), 'Barra superior');
$addContent('site_tagline', setting($pdo, 'site_tagline', 'Conectando oportunidades'), 'Slogan do site');
foreach (get_menu_items($pdo) as $m) $addContent('menu.' . $m['id'], $m['titulo'], 'Menu');
foreach (db_rows($pdo, 'SELECT * FROM banners ORDER BY ordem,id') as $b) {
    $addContent("banner.{$b['id']}.titulo", $b['titulo'], 'Banner — título');
    $addContent("banner.{$b['id']}.subtitulo", $b['subtitulo'], 'Banner — subtítulo');
    $addContent("banner.{$b['id']}.texto_botao", $b['texto_botao'], 'Banner — botão');
}
foreach (get_benefits($pdo, false) as $b) {
    $addContent("beneficio.{$b['id']}.titulo", $b['titulo'], 'Texto do banner');
    $addContent("beneficio.{$b['id']}.subtitulo", $b['subtitulo'], 'Texto do banner — complemento');
}
$promo = db_one($pdo, 'SELECT * FROM home_promocao WHERE id=1') ?: [];
$addContent('promo.titulo', $promo['titulo'] ?? '', 'Promoção — título');
$addContent('promo.texto', $promo['texto'] ?? '', 'Promoção — texto');
$addContent('promo.beneficios', $promo['beneficios'] ?? '', 'Promoção — benefícios (um por linha)');
$addContent('promo.texto_botao', $promo['texto_botao'] ?? '', 'Promoção — botão');
foreach (['footer_slogan' => 'Rodapé — slogan', 'footer_title_links' => 'Rodapé — título links', 'footer_title_social' => 'Rodapé — título redes', 'footer_title_contact' => 'Rodapé — título contato', 'footer_cta_text' => 'Rodapé — link de chamada', 'footer_bottom_text' => 'Rodapé — barra inferior', 'footer_copyright' => 'Rodapé — direitos autorais', 'cookie_text' => 'Aviso de cookies'] as $k => $label) $addContent($k, setting($pdo, $k, ''), $label);
foreach (preg_split('/\R/', setting($pdo, 'footer_links', '')) as $i => $line) { $p = explode('|', $line, 2); if (count($p) === 2) $addContent('footer_link.' . $i, trim($p[0]), 'Rodapé — link'); }
foreach (db_rows($pdo, 'SELECT id,titulo,conteudo FROM paginas ORDER BY ordem,id') as $pg) {
    $addContent("pagina.{$pg['id']}.titulo", $pg['titulo'], 'Página — título');
    $addContent("pagina.{$pg['id']}.conteudo", $pg['conteudo'], 'Página — conteúdo (HTML)');
}
$showContent = $trLang !== $defaultLang;
if ($showContent) $rows = array_merge($rows, $content);

$existing = [];
foreach (db_rows($pdo, 'SELECT chave,valor FROM traducoes WHERE idioma=?', [$trLang]) as $r) $existing[$r['chave']] = $r['valor'];
$group = (string)($_GET['grupo'] ?? '');
$q = trim((string)($_GET['q'] ?? ''));
$onlyMissing = isset($_GET['faltando']);
$filtered = array_values(array_filter($rows, function ($r) use ($group, $q, $existing, $onlyMissing) {
    if ($group !== '' && $r['group'] !== $group) return false;
    if ($onlyMissing && ($existing[$r['key']] ?? '') !== '') return false;
    if ($q !== '' && stripos($r['key'] . ' ' . $r['base'] . ' ' . ($existing[$r['key']] ?? ''), $q) === false) return false;
    return true;
}));
$total = count($rows);
$done = count(array_filter($rows, fn($r) => ($existing[$r['key']] ?? '') !== ''));
$trLangName = '';
foreach ($langs as $l) if ($l['codigo'] === $trLang) $trLangName = trim($l['bandeira'] . ' ' . $l['nome']);
?>
<main class="admin-content">
  <div class="admin-heading">
    <div><h1>Idiomas e traduções</h1><p class="muted">Cadastre idiomas, defina o padrão e traduza os textos do site. O visitante troca o idioma pelo seletor da barra superior.</p></div>
  </div>
  <?php if ($msg): ?><div class="notice success"><?= e($msg) ?></div><?php endif; ?>
  <?php if ($erro): ?><div class="notice error"><?= e($erro) ?></div><?php endif; ?>

  <div class="admin-panel mb-4">
    <h2>Idiomas disponíveis</h2>
    <div class="table-responsive">
      <table class="admin-table">
        <thead><tr><th>Ordem</th><th>Idioma</th><th>Código</th><th>Traduções</th><th>Status</th><th>Padrão</th><th>Ações</th></tr></thead>
        <tbody>
        <?php foreach ($langs as $l): $active = $editLang && (int)$editLang['id'] === (int)$l['id']; ?>
          <tr class="<?= $active ? 'is-active-row' : '' ?>">
            <td><?= (int)$l['ordem'] ?></td>
            <td><span class="fs-5"><?= e($l['bandeira']) ?></span> <strong><?= e($l['nome']) ?></strong></td>
            <td><code><?= e($l['codigo']) ?></code></td>
            <td><?= (int)$l['total'] ?></td>
            <td><span class="status status-<?= $l['ativo'] ? 'ativo' : 'inativo' ?>"><?= $l['ativo'] ? 'Ativo' : 'Inativo' ?></span></td>
            <td>
              <?php if ((int)$l['padrao']): ?><span class="edit-badge sm">★ Padrão</span>
              <?php else: ?>
              <form method="post" class="d-inline"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="default"><input type="hidden" name="id" value="<?= (int)$l['id'] ?>"><button class="btn-action">Tornar padrão</button></form>
              <?php endif; ?>
            </td>
            <td class="actions">
              <a class="btn-action" href="?lang=<?= urlencode($l['codigo']) ?>#traducoes">Traduzir</a>
              <a class="btn-action<?= $active ? ' is-active' : '' ?>" href="?edit=<?= (int)$l['id'] ?>&lang=<?= urlencode($trLang) ?>"><?= $active ? '✎ Editando' : 'Editar' ?></a>
              <?php if (!(int)$l['padrao']): ?><a class="btn-action danger" href="?delete=<?= (int)$l['id'] ?>&token=<?= e(csrf_token()) ?>" onclick="return confirm('Excluir este idioma e todas as suas traduções?')">Excluir</a><?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="edit-panel mt-3<?= $editLang ? ' is-editing' : '' ?>">
      <div class="edit-panel-head"><?php if ($editLang): ?><span class="edit-badge">✎ Editando</span><h3 class="h6 m-0"><?= e($editLang['nome']) ?></h3><a class="btn btn-sm btn-outline-secondary ms-auto" href="idiomas.php">Cancelar edição</a><?php else: ?><h3 class="h6 m-0">Adicionar idioma</h3><?php endif; ?></div>
      <form class="admin-form" method="post" action="idiomas.php?lang=<?= urlencode($trLang) ?>">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="language">
        <input type="hidden" name="id" value="<?= (int)($editLang['id'] ?? 0) ?>">
        <div class="form-grid-4">
          <label>Código<input name="codigo" required maxlength="5" value="<?= e($editLang['codigo'] ?? '') ?>" placeholder="fr"></label>
          <label>Nome<input name="nome" required value="<?= e($editLang['nome'] ?? '') ?>" placeholder="Français"></label>
          <label>Bandeira (emoji)<input name="bandeira" value="<?= e($editLang['bandeira'] ?? '') ?>" placeholder="🇫🇷"></label>
          <label>Ordem<input type="number" name="ordem" min="0" value="<?= (int)($editLang['ordem'] ?? (count($langs) + 1)) ?>"></label>
        </div>
        <label class="check-row"><input type="checkbox" name="ativo" value="1" <?= ($editLang['ativo'] ?? 1) ? 'checked' : '' ?>> Ativo (aparece no seletor do site)</label>
        <div><button class="btn btn-primary"><?= $editLang ? 'Salvar idioma' : 'Adicionar idioma' ?></button></div>
      </form>
    </div>
  </div>

  <div class="admin-panel" id="traducoes">
    <div class="admin-heading">
      <div><h2 class="mb-1">Traduções — <?= e($trLangName) ?></h2><p class="muted mb-0"><?= $done ?> de <?= $total ?> textos traduzidos. Campos vazios usam o texto do idioma padrão.</p></div>
      <div class="lang-tabs">
        <?php foreach ($langs as $l): ?><a class="<?= $l['codigo'] === $trLang ? 'active' : '' ?>" href="?lang=<?= urlencode($l['codigo']) ?>#traducoes"><?= e($l['bandeira']) ?> <?= e(strtoupper($l['codigo'])) ?></a><?php endforeach; ?>
      </div>
    </div>
    <div class="progress my-3" style="height:8px" role="progressbar" aria-valuenow="<?= $total ? round($done / $total * 100) : 0 ?>" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar" style="width:<?= $total ? round($done / $total * 100) : 0 ?>%;background:#9f1549"></div></div>
    <?php if (!$showContent): ?><p class="notice success">Este é o idioma padrão: aqui você pode ajustar os textos da interface. O conteúdo cadastrado (banners, páginas, rodapé…) é editado diretamente em cada módulo.</p><?php endif; ?>
    <form class="admin-filter" method="get" action="idiomas.php#traducoes">
      <input type="hidden" name="lang" value="<?= e($trLang) ?>">
      <select name="grupo"><option value="">Todas as seções</option><?php foreach ($groupNames as $g => $gl): if ($g === 'conteudo' && !$showContent) continue; ?><option value="<?= $g ?>" <?= $group === $g ? 'selected' : '' ?>><?= e($gl) ?></option><?php endforeach; ?></select>
      <input name="q" value="<?= e($q) ?>" placeholder="Buscar texto">
      <label class="check-row"><input type="checkbox" name="faltando" value="1" <?= $onlyMissing ? 'checked' : '' ?>> Só não traduzidos</label>
      <button class="btn btn-outline-secondary">Filtrar</button>
    </form>
    <form method="post" action="idiomas.php?<?= e(http_build_query(array_filter(['lang' => $trLang, 'grupo' => $group, 'q' => $q, 'faltando' => $onlyMissing ? 1 : null]))) ?>#traducoes">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="translations">
      <input type="hidden" name="lang" value="<?= e($trLang) ?>">
      <div class="translation-list">
        <?php $lastGroup = null; foreach ($filtered as $r): ?>
          <?php if ($r['group'] !== $lastGroup): $lastGroup = $r['group']; ?><h3 class="form-section"><?= e($groupNames[$r['group']] ?? $r['group']) ?></h3><?php endif; ?>
          <div class="translation-row<?= ($existing[$r['key']] ?? '') !== '' ? ' is-done' : '' ?>">
            <div class="tr-source"><small class="tr-key"><?= e($r['label'] ?? $r['key']) ?></small><span><?= nl2br(e(mb_strimwidth($r['base'], 0, 400, '…'))) ?></span></div>
            <?php if ($r['long']): ?>
              <textarea name="tr[<?= e($r['key']) ?>]" rows="3" aria-label="Tradução de <?= e($r['key']) ?>"><?= e($existing[$r['key']] ?? '') ?></textarea>
            <?php else: ?>
              <input name="tr[<?= e($r['key']) ?>]" value="<?= e($existing[$r['key']] ?? '') ?>" aria-label="Tradução de <?= e($r['key']) ?>">
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
        <?php if (!$filtered): ?><p class="muted">Nenhum texto encontrado para este filtro.</p><?php endif; ?>
      </div>
      <?php if ($filtered): ?><div class="sticky-save"><button class="btn btn-primary">Salvar traduções</button> <span class="muted">Placeholders como {n}, {link} e {autor} devem ser mantidos.</span></div><?php endif; ?>
    </form>
  </div>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
