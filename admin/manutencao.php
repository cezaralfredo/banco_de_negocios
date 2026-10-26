<?php
$admin_title = 'Modo de manutenção';
require __DIR__ . '/includes/init.php';
$s = fn(string $k, string $d = '') => setting($pdo, $k, $d);

if (isset($_GET['preview'])) {
    $GLOBALS['__bn_maintenance_preview'] = true;
    maintenance_render(true);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) { flash('error', 'Sessão expirada.'); redirect('manutencao.php'); }
    $act = $_POST['acao'] ?? 'salvar';
    if ($act === 'ligar' || $act === 'desligar') {
        $on = $act === 'ligar';
        save_setting($pdo, 'manutencao_ativa', $on ? '1' : '0');
        if ($on && $s('manutencao_fim') !== '' && strtotime($s('manutencao_fim')) <= time()) save_setting($pdo, 'manutencao_fim', '');
        audit($on ? 'manutencao_ativada' : 'manutencao_desativada', 'manutencao');
        do_action('maintenance_changed', $on);
        flash('success', $on ? 'Modo de manutenção ATIVADO. Visitantes verão a página de manutenção.' : 'Modo de manutenção desativado. O site voltou ao ar.');
        redirect('manutencao.php');
    }
    if ($act === 'novo_link') {
        save_setting($pdo, 'manutencao_token', bin2hex(random_bytes(10)));
        audit('manutencao_config', 'manutencao', null, ['link_secreto' => 'renovado']);
        flash('success', 'Novo link de visualização gerado. O link anterior deixou de funcionar.');
        redirect('manutencao.php');
    }
    $toDt = fn($v) => preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', (string)$v) ? str_replace('T', ' ', $v) . ':00' : '';
    $inicio = $toDt($_POST['inicio'] ?? ''); $fim = $toDt($_POST['fim'] ?? '');
    if ($inicio && $fim && strtotime($fim) <= strtotime($inicio)) { flash('error', 'O término deve ser depois do início.'); redirect('manutencao.php'); }
    $ips = implode("\n", array_filter(array_map('trim', preg_split('/[\s,]+/', (string)($_POST['ips'] ?? ''))), fn($ip) => filter_var($ip, FILTER_VALIDATE_IP)));
    $vals = [
        'manutencao_titulo' => trim((string)($_POST['titulo'] ?? '')) ?: 'Estamos em manutenção',
        'manutencao_mensagem' => trim((string)($_POST['mensagem'] ?? '')),
        'manutencao_contato' => filter_var(trim((string)($_POST['contato'] ?? '')), FILTER_VALIDATE_EMAIL) ?: '',
        'manutencao_inicio' => $inicio, 'manutencao_fim' => $fim,
        'manutencao_auto_desligar' => isset($_POST['auto_desligar']) ? '1' : '0',
        'manutencao_contagem' => isset($_POST['contagem']) ? '1' : '0',
        'manutencao_ips' => $ips,
    ];
    try { $img = media_field_value('imagem', 'manutencao', 1200, 900); }
    catch (Throwable $ex) { flash('error', $ex->getMessage()); redirect('manutencao.php'); }
    $vals['manutencao_imagem'] = $img;
    foreach ($vals as $k => $v) save_setting($pdo, $k, $v);
    audit('manutencao_config', 'manutencao', null, ['inicio' => $inicio, 'fim' => $fim, 'ips_liberados' => count(array_filter(explode("\n", $ips)))]);
    flash('success', 'Configurações da manutenção salvas.');
    redirect('manutencao.php');
}
$active = maintenance_active();
$scheduled = !$active && $s('manutencao_ativa') === '1';
$link = site_base_url() . '?preview=' . $s('manutencao_token');
$bypassRoles = array_filter(roles_all(), fn($r) => $r['slug'] === 'administrador' || db_one($pdo, "SELECT 1 x FROM papel_permissoes WHERE papel_id=? AND permissao='acessar:manutencao'", [$r['id']]));
$dt = fn($v) => $v ? date('Y-m-d\TH:i', strtotime($v)) : '';
include __DIR__ . '/includes/header.php';
?>
<main class="admin-content">
  <div class="admin-heading">
    <div><h1>Modo de manutenção</h1><p class="muted">Tire o site do ar temporariamente com uma página personalizada. O painel continua funcionando normalmente.</p></div>
    <a class="btn btn-outline-secondary" href="?preview=1" target="_blank">👁 Pré-visualizar página</a>
  </div>
  <div class="admin-panel mb-4 maint-status <?= $active ? 'is-on' : '' ?>">
    <div class="d-flex align-items-center gap-3 flex-wrap">
      <div style="font-size:34px" aria-hidden="true"><?= $active ? '🔧' : '✅' ?></div>
      <div>
        <h2 class="mb-1"><?= $active ? 'O site está EM MANUTENÇÃO' : ($scheduled ? 'Manutenção agendada' : 'O site está no ar') ?></h2>
        <p class="muted mb-0"><?php if ($active): ?>Visitantes recebem a página de manutenção (código HTTP 503, que orienta os buscadores a voltar depois).<?php elseif ($scheduled): ?>Começa em <?= e(date('d/m/Y H:i', strtotime($s('manutencao_inicio')))) ?>.<?php else: ?>Todos os visitantes estão vendo o site normalmente.<?php endif; ?>
        <?php if ($s('manutencao_fim') && strtotime($s('manutencao_fim')) > time()): ?> Término previsto: <?= e(date('d/m/Y H:i', strtotime($s('manutencao_fim')))) ?><?= $s('manutencao_auto_desligar', '1') === '1' ? ' (desliga sozinho)' : '' ?>.<?php endif; ?></p>
      </div>
      <form method="post" class="ms-auto">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <?php if ($s('manutencao_ativa') === '1'): ?>
          <button class="btn btn-success" name="acao" value="desligar" data-confirm="Desativar o modo de manutenção e colocar o site de volta no ar?" data-confirm-style="primary" data-confirm-button="Colocar no ar">Desativar manutenção</button>
        <?php else: ?>
          <button class="btn btn-danger" name="acao" value="ligar" data-confirm="Ativar o modo de manutenção? Os visitantes deixarão de ver o site até você desativar." data-confirm-word="MANUTENÇÃO" data-confirm-button="Ativar manutenção">Ativar manutenção</button>
        <?php endif; ?>
      </form>
    </div>
  </div>

  <div class="split">
    <div class="admin-panel">
      <h2>Página de manutenção</h2>
      <form class="admin-form" method="post" enctype="multipart/form-data" data-dirty-check>
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="acao" value="salvar">
        <label>Título<input name="titulo" maxlength="120" value="<?= e($s('manutencao_titulo', 'Estamos em manutenção')) ?>"></label>
        <label>Mensagem<textarea name="mensagem"><?= e($s('manutencao_mensagem', 'Estamos realizando melhorias no site. Voltamos em breve!')) ?></textarea></label>
        <?= media_field('imagem', $s('manutencao_imagem'), 'Imagem ilustrativa (opcional)', 'Sem imagem, é exibido um ícone. O logo e as cores seguem a identidade do cliente e o tema ativo.') ?>
        <label>E-mail de contato exibido <small class="muted">Vazio = e-mail do rodapé.</small><input type="email" name="contato" value="<?= e($s('manutencao_contato')) ?>"></label>
        <h3 class="form-section">Agendamento (opcional)</h3>
        <div class="form-grid-2">
          <label>Início<input type="datetime-local" name="inicio" value="<?= e($dt($s('manutencao_inicio'))) ?>"><small class="muted">Com a manutenção ativada, ela só passa a valer a partir deste horário.</small></label>
          <label>Término previsto<input type="datetime-local" name="fim" value="<?= e($dt($s('manutencao_fim'))) ?>"></label>
        </div>
        <label class="check-row"><input type="checkbox" name="auto_desligar" value="1" <?= $s('manutencao_auto_desligar', '1') === '1' ? 'checked' : '' ?>> Desativar automaticamente no horário de término</label>
        <label class="check-row"><input type="checkbox" name="contagem" value="1" <?= $s('manutencao_contagem', '1') === '1' ? 'checked' : '' ?>> Mostrar contagem regressiva até o término</label>
        <h3 class="form-section">Acesso durante a manutenção</h3>
        <label>IPs liberados <small class="muted">Um por linha. Esses endereços veem o site normalmente (seu IP atual: <?= e(client_ip()) ?>).</small><textarea name="ips" style="min-height:70px"><?= e($s('manutencao_ips')) ?></textarea></label>
        <div><button class="btn btn-primary">Salvar configurações</button></div>
      </form>
    </div>
    <aside>
      <div class="side-card">
        <h3>Quem vê o site durante a manutenção</h3>
        <p class="muted small">Usuários logados no painel com a permissão <code>acessar:manutencao</code>:</p>
        <ul class="small"><?php foreach ($bypassRoles as $r): ?><li><?= e($r['nome']) ?></li><?php endforeach; ?></ul>
        <?php if (can('gerenciar:papeis')): ?><a class="btn-action" href="papeis.php">Ajustar papéis</a><?php endif; ?>
      </div>
      <div class="side-card">
        <h3>Link secreto de visualização</h3>
        <p class="muted small">Compartilhe com quem precisa ver o site sem login (vale por 24 h no navegador de quem abrir).</p>
        <input class="form-control form-control-sm mb-2" readonly value="<?= e($link) ?>" onclick="this.select()" aria-label="Link secreto">
        <form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><button class="btn-action" name="acao" value="novo_link" data-confirm="Gerar um novo link? O link atual deixará de funcionar." data-confirm-style="primary">Gerar novo link</button></form>
      </div>
    </aside>
  </div>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
