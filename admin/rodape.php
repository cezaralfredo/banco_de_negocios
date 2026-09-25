<?php
$admin_title = 'Rodapé e redes sociais';
include __DIR__ . '/includes/header.php';
$msg = '';
$erro = '';
$networks = social_network_options();
$footerKeys = ['footer_slogan', 'footer_email', 'footer_phone', 'footer_address', 'footer_title_links', 'footer_title_social', 'footer_title_contact', 'footer_links', 'footer_cta_text', 'footer_cta_link', 'footer_bottom_text', 'footer_copyright'];
$editSocial = isset($_GET['edit_social']) && $pdo ? db_one($pdo, 'SELECT * FROM redes_sociais WHERE id=?', [(int)$_GET['edit_social']]) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo) {
    $action = $_POST['action'] ?? '';
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $erro = 'Token de segurança inválido.';
    } elseif ($action === 'footer') {
        foreach ($footerKeys as $k) save_setting($pdo, $k, trim(str_replace("\r", '', (string)($_POST[$k] ?? ''))));
        save_setting($pdo, 'footer_show_logo', isset($_POST['footer_show_logo']) ? '1' : '0');
        $msg = 'Conteúdo do rodapé atualizado.';
    } elseif ($action === 'social') {
        $id = (int)($_POST['id'] ?? 0);
        $rede = isset($networks[$_POST['rede'] ?? '']) ? $_POST['rede'] : 'site';
        $nome = trim($_POST['nome'] ?? '') ?: $networks[$rede];
        $url = trim($_POST['url'] ?? '');
        $ordem = max(0, (int)($_POST['ordem'] ?? 0));
        $ativo = isset($_POST['ativo']) ? 1 : 0;
        if (!preg_match('#^(https?://|mailto:|tel:)#i', $url)) {
            $erro = 'Informe um endereço completo, começando com https://';
        } else {
            if ($id) $pdo->prepare('UPDATE redes_sociais SET rede=?,nome=?,url=?,ordem=?,ativo=? WHERE id=?')->execute([$rede, $nome, $url, $ordem, $ativo, $id]);
            else $pdo->prepare('INSERT INTO redes_sociais(rede,nome,url,ordem,ativo) VALUES(?,?,?,?,?)')->execute([$rede, $nome, $url, $ordem, $ativo]);
            $msg = $id ? 'Rede social atualizada.' : 'Rede social incluída.';
            $editSocial = null;
        }
    } elseif ($action === 'cookies') {
        save_setting($pdo, 'cookie_text', trim($_POST['cookie_text'] ?? ''));
        save_setting($pdo, 'cookie_policy_url', trim($_POST['cookie_policy_url'] ?? '') ?: 'pagina.php?slug=politica-de-privacidade');
        if (isset($_POST['cookie_reset'])) {
            save_setting($pdo, 'cookie_version', (string)((int)setting($pdo, 'cookie_version', '1') + 1));
        }
        $msg = isset($_POST['cookie_reset']) ? 'Aviso de cookies atualizado. Todos os visitantes verão o banner novamente.' : 'Aviso de cookies atualizado.';
    }
}
if (isset($_GET['delete_social']) && $pdo && verify_csrf($_GET['token'] ?? null)) {
    $pdo->prepare('DELETE FROM redes_sociais WHERE id=?')->execute([(int)$_GET['delete_social']]);
    $msg = 'Rede social excluída.';
}
$socials = get_social_links($pdo, false);
$v = fn(string $k, string $d = '') => setting($pdo, $k, $d);
$consentStats = db_rows($pdo, 'SELECT escolha, COUNT(*) n FROM consentimentos_cookies GROUP BY escolha');
$editSocialId = (int)($editSocial['id'] ?? 0);
?>
<main class="admin-content">
  <div class="admin-heading">
    <div><h1>Rodapé e redes sociais</h1><p class="muted">Edite o conteúdo do rodapé, inclua ou exclua redes sociais e configure o aviso de cookies (LGPD).</p></div>
    <a class="btn btn-outline-secondary" href="../index.php#conteudo" target="_blank">Ver site ↗</a>
  </div>
  <?php if ($msg): ?><div class="notice success"><?= e($msg) ?></div><?php endif; ?>
  <?php if ($erro): ?><div class="notice error"><?= e($erro) ?></div><?php endif; ?>

  <div class="admin-panel mb-4">
    <h2>Conteúdo do rodapé</h2>
    <form class="admin-form" method="post">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="footer">
      <h3 class="form-section">Coluna 1 — marca</h3>
      <label class="check-row"><input type="checkbox" name="footer_show_logo" value="1" <?= $v('footer_show_logo', '1') === '1' ? 'checked' : '' ?>> Exibir o logo do site (definido em Identidade do site)</label>
      <label>Texto / slogan<textarea name="footer_slogan" style="min-height:80px"><?= e($v('footer_slogan', 'Conectando pessoas, negócios e oportunidades.')) ?></textarea></label>

      <h3 class="form-section">Coluna 2 — links institucionais</h3>
      <label>Título da coluna <small class="muted">Vazio = “Links Institucionais”</small><input name="footer_title_links" value="<?= e($v('footer_title_links')) ?>"></label>
      <label>Links <small class="muted">Um por linha no formato <code>Título|endereço</code>. Deixe vazio para listar automaticamente Início, Notícias e as páginas publicadas.</small>
        <textarea name="footer_links" placeholder="Início|index.php&#10;Notícias|noticias.php&#10;Sobre Nós|pagina.php?slug=sobre-nos"><?= e($v('footer_links')) ?></textarea></label>

      <h3 class="form-section">Coluna 3 — redes sociais</h3>
      <label>Título da coluna <small class="muted">Vazio = “Redes Sociais”. As redes são gerenciadas logo abaixo.</small><input name="footer_title_social" value="<?= e($v('footer_title_social')) ?>"></label>

      <h3 class="form-section">Coluna 4 — contato</h3>
      <label>Título da coluna <small class="muted">Vazio = “Fale Conosco”</small><input name="footer_title_contact" value="<?= e($v('footer_title_contact')) ?>"></label>
      <div class="form-grid-2">
        <label>E-mail<input type="email" name="footer_email" value="<?= e($v('footer_email', 'contato@bancodenegocios.com.br')) ?>"></label>
        <label>Telefone / WhatsApp<input name="footer_phone" value="<?= e($v('footer_phone')) ?>" placeholder="(85) 99999-9999"></label>
      </div>
      <label>Endereço<textarea name="footer_address" style="min-height:70px"><?= e($v('footer_address', 'Fortaleza - CE')) ?></textarea></label>
      <div class="form-grid-2">
        <label>Texto do link de chamada<input name="footer_cta_text" value="<?= e($v('footer_cta_text', 'Cadastre seu negócio →')) ?>"></label>
        <label>Endereço do link <small class="muted">cadastro.php abre o modal · vazio oculta</small><input name="footer_cta_link" value="<?= e($v('footer_cta_link', 'cadastro.php')) ?>"></label>
      </div>

      <h3 class="form-section">Barra inferior</h3>
      <label>Texto à esquerda<input name="footer_bottom_text" value="<?= e($v('footer_bottom_text', 'Conteúdo institucional gerenciado pelo administrador')) ?>"></label>
      <label>Texto de direitos autorais <small class="muted">Vazio = “© ano Nome do site. Todos os direitos reservados.” Use {ano} para o ano atual.</small><input name="footer_copyright" value="<?= e($v('footer_copyright')) ?>" placeholder="© {ano} Banco de Negócios. Todos os direitos reservados."></label>
      <div><button class="btn btn-primary">Salvar rodapé</button></div>
    </form>
  </div>

  <div class="admin-panel mb-4" id="redes">
    <h2>Redes sociais</h2>
    <div class="edit-panel<?= $editSocialId ? ' is-editing' : '' ?>">
      <div class="edit-panel-head"><?php if ($editSocialId): ?><span class="edit-badge">✎ Editando</span><h3 class="h6 m-0"><?= e($editSocial['nome']) ?></h3><a class="btn btn-sm btn-outline-secondary ms-auto" href="rodape.php#redes">Cancelar edição</a><?php else: ?><h3 class="h6 m-0">Incluir rede social</h3><?php endif; ?></div>
      <form class="admin-form" method="post" action="rodape.php#redes">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="social">
        <input type="hidden" name="id" value="<?= $editSocialId ?>">
        <div class="form-grid-2">
          <label>Rede<select name="rede"><?php foreach ($networks as $k => $l): ?><option value="<?= $k ?>" <?= ($editSocial['rede'] ?? '') === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
          <label>Nome exibido (acessibilidade)<input name="nome" value="<?= e($editSocial['nome'] ?? '') ?>" placeholder="Ex.: Instagram"></label>
        </div>
        <label>Endereço (URL)<input name="url" required value="<?= e($editSocial['url'] ?? '') ?>" placeholder="https://instagram.com/seuperfil"></label>
        <div class="form-grid-2">
          <label>Ordem<input type="number" name="ordem" min="0" value="<?= (int)($editSocial['ordem'] ?? (count($socials) + 1)) ?>"></label>
          <label class="check-row align-self-end"><input type="checkbox" name="ativo" value="1" <?= ($editSocial['ativo'] ?? 1) ? 'checked' : '' ?>> Visível no rodapé</label>
        </div>
        <div><button class="btn btn-primary"><?= $editSocialId ? 'Salvar alterações' : 'Incluir rede' ?></button></div>
      </form>
    </div>
    <div class="table-responsive mt-3">
      <table class="admin-table">
        <thead><tr><th>Ordem</th><th>Ícone</th><th>Rede</th><th>URL</th><th>Status</th><th>Ações</th></tr></thead>
        <tbody>
        <?php if (!$socials): ?><tr><td colspan="6" class="muted">Nenhuma rede social cadastrada — a coluna ficará vazia.</td></tr><?php endif; ?>
        <?php foreach ($socials as $s): $active = (int)$s['id'] === $editSocialId; ?>
          <tr class="<?= $active ? 'is-active-row' : '' ?>">
            <td><?= (int)$s['ordem'] ?></td>
            <td><span class="social-chip"><?= svg_icon($s['rede'], 'icon-pick') ?></span></td>
            <td><strong><?= e($s['nome']) ?></strong></td>
            <td class="text-truncate" style="max-width:260px"><a href="<?= e($s['url']) ?>" target="_blank" rel="noopener"><?= e($s['url']) ?></a></td>
            <td><span class="status status-<?= $s['ativo'] ? 'ativo' : 'inativo' ?>"><?= $s['ativo'] ? 'Visível' : 'Oculta' ?></span></td>
            <td class="actions"><a class="btn-action<?= $active ? ' is-active' : '' ?>" href="?edit_social=<?= (int)$s['id'] ?>#redes"><?= $active ? '✎ Editando' : 'Editar' ?></a> <a class="btn-action danger" href="?delete_social=<?= (int)$s['id'] ?>&token=<?= e(csrf_token()) ?>#redes" onclick="return confirm('Excluir esta rede social?')">Excluir</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="admin-panel" id="cookies">
    <h2>Aviso de cookies (LGPD)</h2>
    <p class="muted">O banner aparece no primeiro acesso, no final da página, com as opções Aceitar, Recusar e Personalizar. A escolha fica registrada (sem guardar o IP em texto puro) e pode ser alterada pelo visitante no link “Preferências de cookies” do rodapé.</p>
    <form class="admin-form" method="post" action="rodape.php#cookies">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="cookies">
      <label>Texto do aviso <small class="muted">Vazio = texto padrão (traduzível em Idiomas e traduções).</small><textarea name="cookie_text" style="min-height:90px" placeholder="<?= e(t('cookie.text')) ?>"><?= e($v('cookie_text')) ?></textarea></label>
      <label>Link da Política de Privacidade<input name="cookie_policy_url" value="<?= e($v('cookie_policy_url', 'pagina.php?slug=politica-de-privacidade')) ?>"></label>
      <label class="check-row"><input type="checkbox" name="cookie_reset" value="1"> Mudei a política: pedir o consentimento novamente a todos os visitantes (versão atual: <?= e($v('cookie_version', '1')) ?>)</label>
      <div><button class="btn btn-primary">Salvar aviso de cookies</button></div>
    </form>
    <?php if ($consentStats): ?>
    <p class="muted mt-3 mb-0">Consentimentos registrados:
      <?php foreach ($consentStats as $cs): ?><span class="status status-ativo ms-1"><?= e(ucfirst($cs['escolha'])) ?>: <?= (int)$cs['n'] ?></span><?php endforeach; ?>
    </p>
    <?php endif; ?>
  </div>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
