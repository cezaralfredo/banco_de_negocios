<?php
$admin_title = 'Configurações globais';
require __DIR__ . '/includes/init.php';
$tabs = [
    'geral' => 'Geral', 'identidade' => 'Identidade visual', 'seo' => 'SEO', 'email' => 'E-mail (SMTP)',
    'analytics' => 'Analytics e scripts', 'integracoes' => 'Chaves de API', 'seguranca' => 'Segurança', 'conteudo' => 'Conteúdo e arquivos',
];
$tab = isset($tabs[$_GET['aba'] ?? '']) ? $_GET['aba'] : 'geral';
$s = fn(string $k, string $d = '') => setting($pdo, $k, $d);
$tz = ['America/Sao_Paulo', 'America/Fortaleza', 'America/Recife', 'America/Bahia', 'America/Belem', 'America/Manaus', 'America/Cuiaba', 'America/Porto_Velho', 'America/Rio_Branco', 'America/Noronha', 'UTC', 'Europe/Lisbon'];

/* Campos simples por aba: chave => tipo */
$fields = [
    'geral' => ['site_name' => 'text', 'site_tagline' => 'text', 'site_url' => 'url', 'topbar_text' => 'text', 'footer_email' => 'email', 'footer_phone' => 'text', 'fuso_horario' => 'tz', 'feed_rss' => 'bool'],
    'seo' => ['seo_titulo_padrao' => 'text', 'seo_descricao' => 'textarea', 'seo_indexar_site' => 'bool', 'urls_amigaveis' => 'bool', 'seo_google_verificacao' => 'text', 'seo_robots_extra' => 'textarea'],
    'email' => ['smtp_host' => 'text', 'smtp_porta' => 'int', 'smtp_seguranca' => 'sec', 'smtp_usuario' => 'text', 'smtp_remetente' => 'email', 'smtp_remetente_nome' => 'text'],
    'analytics' => ['analytics_ga4' => 'text', 'analytics_gtm' => 'text', 'analytics_matomo_url' => 'url', 'analytics_matomo_id' => 'int', 'scripts_head' => 'code', 'scripts_head_categoria' => 'cat'],
    'seguranca' => ['seg_tentativas_login' => 'int', 'seg_bloqueio_minutos' => 'int', 'seg_sessao_minutos' => 'int', 'seg_senha_min' => 'int', 'seg_senha_forte' => 'bool', 'auditoria_dias' => 'int'],
    'conteudo' => ['lixeira_dias' => 'int', 'versoes_max' => 'int', 'midia_max_mb' => 'int'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) { flash('error', 'Sessão expirada.'); redirect('configuracoes_gerais.php?aba=' . $tab); }
    $changed = [];
    if ($tab === 'email' && ($_POST['acao'] ?? '') === 'testar') {
        $to = filter_var(trim((string)($_POST['teste_para'] ?? '')), FILTER_VALIDATE_EMAIL) ?: $me['email'];
        [$ok, $msg] = send_mail($to, 'Teste de e-mail — ' . $s('site_name', 'CMS'), mail_template('Teste de envio', '<p>Se você recebeu esta mensagem, o envio de e-mails do painel está funcionando. ✅</p>'));
        audit('email_teste', 'configuracoes', null, ['para' => $to, 'ok' => $ok]);
        flash($ok ? 'success' : 'error', $ok ? "E-mail de teste enviado para $to." : $msg);
        redirect('configuracoes_gerais.php?aba=email');
    }
    foreach ($fields[$tab] ?? [] as $k => $type) {
        $v = $_POST[$k] ?? '';
        $v = match ($type) {
            'bool' => isset($_POST[$k]) ? '1' : '0',
            'int' => (string)max(0, (int)$v),
            'email' => filter_var(trim((string)$v), FILTER_VALIDATE_EMAIL) ?: '',
            'url' => preg_match('#^https?://#', trim((string)$v)) ? rtrim(trim((string)$v), '/') : '',
            'tz' => in_array($v, $tz, true) ? $v : 'America/Sao_Paulo',
            'sec' => in_array($v, ['tls', 'ssl', 'none'], true) ? $v : 'tls',
            'cat' => in_array($v, ['statistics', 'marketing', 'preferences'], true) ? $v : 'statistics',
            default => trim(str_replace("\r", '', (string)$v)),
        };
        if ($k === 'site_name' && $v === '') { flash('error', 'O nome do site é obrigatório.'); redirect('configuracoes_gerais.php?aba=geral'); }
        if (in_array($k, ['seg_tentativas_login', 'seg_senha_min'], true)) $v = (string)max($k === 'seg_senha_min' ? 8 : 3, (int)$v);
        if ($s($k) !== $v) $changed[] = $k;
        save_setting($pdo, $k, $v);
    }
    if ($tab === 'email' && ($_POST['smtp_senha'] ?? '') !== '') { save_secret_setting('smtp_senha', (string)$_POST['smtp_senha']); $changed[] = 'smtp_senha'; }
    if ($tab === 'email' && isset($_POST['limpar_senha'])) { save_setting($pdo, 'smtp_senha', ''); $changed[] = 'smtp_senha'; }
    if ($tab === 'identidade') {
        try {
            foreach (['site_logo' => 'logo', 'site_logo_compacto' => 'logo-compacto', 'site_favicon' => 'favicon', 'seo_og_imagem' => 'og'] as $k => $prefix) {
                $v = media_field_value($k, $prefix, $k === 'site_favicon' ? 256 : 1200, $k === 'site_favicon' ? 256 : 1200);
                if ($v !== $s($k)) { save_setting($pdo, $k, $v); $changed[] = $k; }
            }
        } catch (Throwable $ex) { flash('error', $ex->getMessage()); redirect('configuracoes_gerais.php?aba=identidade'); }
    }
    if ($tab === 'integracoes') {
        $list = [];
        foreach ((array)($_POST['api'] ?? []) as $row) {
            $nome = trim((string)($row['nome'] ?? '')); $chave = preg_replace('/[^a-z0-9_]/', '_', strtolower(trim((string)($row['chave'] ?? ''))));
            if ($nome === '' || $chave === '') continue;
            $old = json_decode($s('api_chaves', '[]'), true) ?: [];
            $prev = '';
            foreach ($old as $o) if ($o['chave'] === $chave) $prev = $o['valor'];
            $valor = (string)($row['valor'] ?? '');
            $list[] = ['nome' => mb_substr($nome, 0, 80), 'chave' => $chave, 'valor' => $valor !== '' ? secret_encrypt($valor) : $prev];
        }
        save_setting($pdo, 'api_chaves', json_encode($list));
        $changed[] = 'api_chaves';
    }
    // Configurações de plugins/integrações extras
    do_action('settings_saved', $tab, $_POST);
    if ($changed) audit('configuracoes', 'configuracoes', null, ['aba' => $tabs[$tab], 'campos' => $changed]);
    flash('success', 'Configurações salvas.');
    redirect('configuracoes_gerais.php?aba=' . $tab);
}
$apiKeys = json_decode($s('api_chaves', '[]'), true) ?: [];
$in = fn(string $k, string $type = 'text', string $ph = '', string $extra = '') => '<input type="' . $type . '" name="' . $k . '" value="' . e($s($k)) . '" placeholder="' . e($ph) . '" ' . $extra . '>';
$chk = fn(string $k, string $label, string $def = '0') => '<label class="check-row"><input type="checkbox" name="' . $k . '" value="1"' . ($s($k, $def) === '1' ? ' checked' : '') . '> ' . $label . '</label>';
include __DIR__ . '/includes/header.php';
?>
<main class="admin-content">
  <h1>Configurações globais</h1>
  <p class="muted">Ajustes que valem para todo o site deste cliente. Redes sociais e conteúdo do rodapé ficam em <a href="rodape.php">Rodapé e redes sociais</a>; cookies (LGPD) também.</p>
  <nav class="admin-tabs" aria-label="Seções"><?php foreach ($tabs as $k => $l): ?><a class="<?= $tab === $k ? 'active' : '' ?>" href="?aba=<?= $k ?>"<?= $tab === $k ? ' aria-current="page"' : '' ?>><?= e($l) ?></a><?php endforeach; ?></nav>
  <div class="admin-panel">
    <form class="admin-form" method="post" enctype="multipart/form-data" action="?aba=<?= e($tab) ?>" data-dirty-check>
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <?php if ($tab === 'geral'): ?>
        <div class="form-grid-2"><label>Nome do site<?= $in('site_name', 'text', '', 'required maxlength="120"') ?></label><label>Slogan<?= $in('site_tagline') ?></label></div>
        <label>Endereço oficial do site <small class="muted">Usado em e-mails, sitemap e Open Graph. Vazio = detectado automaticamente.</small><?= $in('site_url', 'url', 'https://www.seusite.com.br') ?></label>
        <label>Texto da barra superior<?= $in('topbar_text') ?></label>
        <div class="form-grid-2"><label>E-mail de contato<?= $in('footer_email', 'email') ?></label><label>Telefone / WhatsApp<?= $in('footer_phone') ?></label></div>
        <label>Fuso horário<select name="fuso_horario"><?php foreach ($tz as $z): ?><option <?= $s('fuso_horario', 'America/Sao_Paulo') === $z ? 'selected' : '' ?>><?= e($z) ?></option><?php endforeach; ?></select></label>
        <?= $chk('feed_rss', 'Publicar feed RSS das notícias', '1') ?>
        <p class="muted">Idiomas: <a href="idiomas.php">Idiomas e traduções</a> · Redes sociais: <a href="rodape.php#redes">Rodapé</a> · Cores e fontes: <a href="temas.php">Temas</a></p>
      <?php elseif ($tab === 'identidade'): ?>
        <p class="muted">A identidade é do cliente: nome (aba Geral), logos, favicon e imagem padrão de compartilhamento. Cores e fontes ficam em <a href="temas.php">Temas e layout</a>.</p>
        <?= media_field('site_logo', $s('site_logo'), 'Logo principal', 'PNG ou SVG convertido em PNG, fundo transparente, até 2 MB.') ?>
        <?= media_field('site_logo_compacto', $s('site_logo_compacto'), 'Logo compacto (celular)', 'Versão menor/quadrada usada em telas pequenas.') ?>
        <?= media_field('site_favicon', $s('site_favicon'), 'Favicon', 'Ícone da aba do navegador — quadrado, ao menos 64×64.') ?>
        <?= media_field('seo_og_imagem', $s('seo_og_imagem'), 'Imagem padrão para redes sociais (Open Graph)', 'Usada quando a página não tem imagem própria. Recomendado 1200×630.') ?>
      <?php elseif ($tab === 'seo'): ?>
        <label>Modelo do título das páginas <small class="muted">Use %titulo% e %site%.</small><?= $in('seo_titulo_padrao', 'text', '%titulo% | %site%') ?></label>
        <label>Descrição padrão do site (meta description)<textarea name="seo_descricao" maxlength="200" data-count="160" style="min-height:80px"><?= e($s('seo_descricao')) ?></textarea></label>
        <?= $chk('seo_indexar_site', 'Permitir que buscadores indexem o site (desmarque em sites de teste/homologação)', '1') ?>
        <?= $chk('urls_amigaveis', 'Usar URLs amigáveis (ex.: /noticia/minha-noticia). Requer Apache com mod_rewrite e o arquivo .htaccess.') ?>
        <label>Código de verificação do Google Search Console <small class="muted">Somente o valor de content="…"</small><?= $in('seo_google_verificacao') ?></label>
        <label>Regras extras no robots.txt<textarea name="seo_robots_extra" style="min-height:70px" placeholder="Disallow: /pasta-privada/"><?= e($s('seo_robots_extra')) ?></textarea></label>
        <p class="muted">Sitemap: <a href="../sitemap.php" target="_blank"><?= e(site_base_url()) ?>sitemap.php</a> · robots: <a href="../robots.php" target="_blank">robots.php</a>. Cada notícia, página e anúncio tem campos próprios de SEO e Open Graph.</p>
      <?php elseif ($tab === 'email'): ?>
        <p class="muted">Usado para recuperação de senha, boas-vindas de usuários e avisos do fluxo editorial. Sem SMTP, o servidor tenta enviar pela função mail() do PHP.</p>
        <div class="form-grid-2"><label>Servidor SMTP<?= $in('smtp_host', 'text', 'smtp.seuprovedor.com') ?></label><label>Porta<?= $in('smtp_porta', 'number', '587') ?></label></div>
        <div class="form-grid-2"><label>Segurança<select name="smtp_seguranca"><?php foreach (['tls' => 'STARTTLS (porta 587)', 'ssl' => 'SSL/TLS (porta 465)', 'none' => 'Nenhuma (não recomendado)'] as $k => $l): ?><option value="<?= $k ?>" <?= $s('smtp_seguranca', 'tls') === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></label><label>Usuário<?= $in('smtp_usuario', 'text', '', 'autocomplete="off"') ?></label></div>
        <label>Senha <small class="muted"><?= secret_setting('smtp_senha') !== '' ? 'Salva e criptografada (' . e(mask_secret(secret_setting('smtp_senha'))) . '). Deixe vazio para manter.' : 'Guardada de forma criptografada.' ?></small><input type="password" name="smtp_senha" autocomplete="new-password"></label>
        <?php if (secret_setting('smtp_senha') !== ''): ?><label class="check-row"><input type="checkbox" name="limpar_senha" value="1"> Remover a senha salva</label><?php endif; ?>
        <div class="form-grid-2"><label>E-mail do remetente<?= $in('smtp_remetente', 'email', 'nao-responda@seusite.com.br') ?></label><label>Nome do remetente<?= $in('smtp_remetente_nome', 'text', $s('site_name')) ?></label></div>
      <?php elseif ($tab === 'analytics'): ?>
        <p class="muted">Os scripts só são carregados <strong>depois do consentimento</strong> do visitante no banner de cookies (LGPD).</p>
        <div class="form-grid-2"><label>Google Analytics 4 — ID de medição<?= $in('analytics_ga4', 'text', 'G-XXXXXXXXXX') ?></label><label>Google Tag Manager — ID do contêiner<?= $in('analytics_gtm', 'text', 'GTM-XXXXXXX') ?></label></div>
        <div class="form-grid-2"><label>Matomo — endereço<?= $in('analytics_matomo_url', 'url', 'https://analytics.seusite.com.br') ?></label><label>Matomo — ID do site<?= $in('analytics_matomo_id', 'number') ?></label></div>
        <label>Script personalizado no &lt;head&gt; <small class="muted">Ex.: Pixel do Meta, Hotjar. Cole o código completo com &lt;script&gt;.</small><textarea name="scripts_head" style="min-height:120px;font-family:monospace;font-size:12px"><?= e($s('scripts_head')) ?></textarea></label>
        <label>Categoria de consentimento do script personalizado<select name="scripts_head_categoria"><?php foreach (['statistics' => 'Estatísticas', 'marketing' => 'Marketing', 'preferences' => 'Preferências'] as $k => $l): ?><option value="<?= $k ?>" <?= $s('scripts_head_categoria', 'statistics') === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></label>
      <?php elseif ($tab === 'integracoes'): ?>
        <p class="muted">Guarde aqui chaves de API e tokens de serviços externos (CRM, mapas, chat…). Os valores são criptografados e ficam disponíveis para plugins por <code>api_key('chave')</code>.</p>
        <table class="admin-table" id="apiTable"><thead><tr><th>Serviço</th><th>Identificador</th><th>Valor</th><th></th></tr></thead><tbody>
          <?php foreach (array_merge($apiKeys, [['nome' => '', 'chave' => '', 'valor' => '']]) as $i => $k): $dec = $k['valor'] !== '' ? secret_decrypt($k['valor']) : ''; ?>
          <tr><td><input class="form-control form-control-sm" name="api[<?= $i ?>][nome]" value="<?= e($k['nome']) ?>" placeholder="Ex.: Google Maps"></td>
              <td><input class="form-control form-control-sm" name="api[<?= $i ?>][chave]" value="<?= e($k['chave']) ?>" placeholder="google_maps" pattern="[a-z0-9_]*"></td>
              <td><input class="form-control form-control-sm" type="password" name="api[<?= $i ?>][valor]" placeholder="<?= $dec !== '' ? e(mask_secret($dec)) . ' (manter)' : 'valor' ?>" autocomplete="new-password"></td>
              <td><?php if ($k['chave'] !== ''): ?><button type="button" class="btn-action danger" onclick="const r=this.closest('tr');r.querySelectorAll('input').forEach(i=>i.value='');r.style.opacity=.4">Remover</button><?php endif; ?></td></tr>
          <?php endforeach; ?>
        </tbody></table>
        <p class="muted small">Salve para adicionar uma nova linha.</p>
      <?php elseif ($tab === 'seguranca'): ?>
        <div class="form-grid-2"><label>Tentativas de login antes do bloqueio<?= $in('seg_tentativas_login', 'number', '5', 'min="3" max="20"') ?></label><label>Tempo de bloqueio (minutos)<?= $in('seg_bloqueio_minutos', 'number', '15', 'min="1" max="1440"') ?></label></div>
        <div class="form-grid-2"><label>Encerrar sessão após inatividade (minutos)<?= $in('seg_sessao_minutos', 'number', '120', 'min="5"') ?></label><label>Tamanho mínimo da senha<?= $in('seg_senha_min', 'number', '8', 'min="8" max="64"') ?></label></div>
        <?= $chk('seg_senha_forte', 'Exigir letras e números nas senhas', '1') ?>
        <label>Manter logs de auditoria por (dias)<?= $in('auditoria_dias', 'number', '365', 'min="30"') ?></label>
        <div class="muted-box small">Proteções sempre ativas: senhas com hash bcrypt/argon, sessões isoladas por cliente e regeneradas no login, tokens CSRF em todos os formulários, cabeçalhos de segurança HTTP, bloqueio de scripts nas pastas de mídia e arquivos sensíveis fora do acesso web.</div>
      <?php elseif ($tab === 'conteudo'): ?>
        <div class="form-grid-2"><label>Dias na lixeira antes da exclusão definitiva<?= $in('lixeira_dias', 'number', '30', 'min="1" max="365"') ?></label><label>Versões guardadas por conteúdo<?= $in('versoes_max', 'number', '50', 'min="5" max="500"') ?></label></div>
        <label>Tamanho máximo de upload (MB)<?= $in('midia_max_mb', 'number', '8', 'min="1" max="64"') ?><small class="muted">O limite do servidor (upload_max_filesize) é <?= e(ini_get('upload_max_filesize')) ?>.</small></label>
      <?php endif; ?>
      <?php do_action('settings_tab', $tab); ?>
      <div class="d-flex gap-2 flex-wrap align-items-center"><button class="btn btn-primary">Salvar</button>
      <?php if ($tab === 'email'): ?><span class="ms-3">Enviar teste para</span> <input type="email" name="teste_para" class="form-control form-control-sm" style="max-width:240px" value="<?= e($me['email']) ?>"> <button class="btn btn-outline-secondary btn-sm" name="acao" value="testar" formnovalidate>Enviar e-mail de teste</button><?php endif; ?></div>
    </form>
  </div>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
