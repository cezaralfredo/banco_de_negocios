<?php
$admin_title = 'Promoção da Home';
include __DIR__ . '/includes/header.php';
$msg = '';
$erro = '';
$promo = $pdo ? db_one($pdo, 'SELECT * FROM home_promocao WHERE id=1') : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo) {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $erro = 'Token de segurança inválido.';
    } else {
        $titulo = trim($_POST['titulo'] ?? '') ?: 'Amplie suas oportunidades!';
        $texto = trim($_POST['texto'] ?? '');
        $beneficios = trim(str_replace("\r", '', $_POST['beneficios'] ?? ''));
        $textoBotao = trim($_POST['texto_botao'] ?? '') ?: 'Criar Conta Agora';
        $link = trim($_POST['link'] ?? '') ?: 'cadastro.php';
        $ativo = isset($_POST['ativo']) ? 1 : 0;
        $imagem = $promo['imagem'] ?? '';
        try {
            $imagem = upload_image_file($_FILES['imagem'] ?? [], 'home', 900, 900) ?? $imagem;
            if (isset($_POST['remover_imagem'])) $imagem = '';
        } catch (Throwable $ex) {
            $erro = $ex->getMessage();
        }
        if (!$erro) {
            $pdo->prepare('INSERT INTO home_promocao(id,titulo,texto,beneficios,texto_botao,imagem,link,ativo) VALUES(1,?,?,?,?,?,?,?)
                ON DUPLICATE KEY UPDATE titulo=VALUES(titulo),texto=VALUES(texto),beneficios=VALUES(beneficios),texto_botao=VALUES(texto_botao),imagem=VALUES(imagem),link=VALUES(link),ativo=VALUES(ativo)')
                ->execute([$titulo, $texto, $beneficios, $textoBotao, $imagem, $link, $ativo]);
            $msg = 'Área promocional atualizada. As alterações já aparecem na home.';
            $promo = db_one($pdo, 'SELECT * FROM home_promocao WHERE id=1');
        }
    }
}
?>
<main class="admin-content">
  <div class="admin-heading">
    <div><h1>Banner promocional da home</h1><p class="muted">Conteúdo da seção “Amplie suas oportunidades!”, exibida abaixo dos Negócios em destaque.</p></div>
    <a class="btn btn-outline-secondary" href="../index.php#amplie" target="_blank">Ver no site ↗</a>
  </div>
  <?php if ($msg): ?><div class="notice success"><?= e($msg) ?></div><?php endif; ?>
  <?php if ($erro): ?><div class="notice error"><?= e($erro) ?></div><?php endif; ?>
  <div class="admin-panel">
    <form class="admin-form" method="post" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <label>Título<input name="titulo" value="<?= e($promo['titulo'] ?? 'Amplie suas oportunidades!') ?>"></label>
      <label>Texto<textarea name="texto"><?= e($promo['texto'] ?? '') ?></textarea></label>
      <label>Benefícios <small class="muted">Um por linha — exibidos com ✓.</small><textarea name="beneficios"><?= e($promo['beneficios'] ?? '') ?></textarea></label>
      <div class="form-grid-2">
        <label>Texto do botão<input name="texto_botao" value="<?= e($promo['texto_botao'] ?? 'Criar Conta Agora') ?>"></label>
        <label>Link do botão<input name="link" value="<?= e($promo['link'] ?? 'cadastro.php') ?>"><small class="muted">cadastro.php abre o mesmo modal “Criar conta” da barra superior.</small></label>
      </div>
      <label>Imagem da área promocional <small class="muted">Opcional — substitui o celular ilustrativo.</small><input type="file" name="imagem" accept="image/jpeg,image/png,image/webp"><small class="muted">Deixe vazio para manter a imagem atual. JPG, PNG ou WebP · até 5 MB.</small></label>
      <?php if (!empty($promo['imagem'])): ?>
        <div class="upload-previews"><figure><img class="promo-preview" src="../<?= e($promo['imagem']) ?>" alt="Imagem promocional atual"><figcaption><label class="check-row"><input type="checkbox" name="remover_imagem" value="1"> remover imagem</label></figcaption></figure></div>
      <?php endif; ?>
      <label class="check-row"><input type="checkbox" name="ativo" value="1" <?= (($promo['ativo'] ?? 1) ? 'checked' : '') ?>> Exibir esta área no site</label>
      <div><button class="btn btn-primary">Salvar banner promocional</button></div>
    </form>
  </div>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
