<?php
$admin_title = 'Clientes';
require __DIR__ . '/includes/init.php';
if (!is_primary_tenant()) { flash('error', 'A gestão de clientes só está disponível na instalação principal.'); redirect('dashboard.php'); }
$tenants = bn_tenants();

/** Cria o banco do cliente e importa a estrutura inicial. */
function provision_tenant(array &$db, string $nome, string $adminNome, string $adminEmail, string $adminSenha, bool $demo, bool $dedicatedUser): string
{
    $server = bn_connect($db, false);
    if (!$server) throw new RuntimeException('Não foi possível conectar ao servidor MySQL com as credenciais informadas.');
    $name = preg_replace('/[^a-zA-Z0-9_]/', '', $db['name']);
    if ($name === '') throw new RuntimeException('Nome de banco inválido.');
    $server->exec("CREATE DATABASE IF NOT EXISTS `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $note = '';
    if ($dedicatedUser) {
        // Isolamento: usuário MySQL exclusivo, com acesso SOMENTE ao banco deste cliente
        $u = substr(str_starts_with($name, 'cms_') ? $name : 'cms_' . $name, 0, 32);
        $pass = bin2hex(random_bytes(16));
        try {
            foreach (['localhost', '127.0.0.1', '%'] as $h) {
                $server->exec("CREATE USER IF NOT EXISTS '$u'@'$h' IDENTIFIED BY '$pass'");
                $server->exec("ALTER USER '$u'@'$h' IDENTIFIED BY '$pass'");
                $server->exec("GRANT ALL PRIVILEGES ON `$name`.* TO '$u'@'$h'");
            }
            $server->exec('FLUSH PRIVILEGES');
            $test = bn_connect(['user' => $u, 'pass' => $pass] + $db);
            if ($test) { $db['user'] = $u; $db['pass'] = $pass; $note = " Usuário MySQL exclusivo “{$u}” criado (acesso só ao banco do cliente)."; }
            else $note = ' Atenção: não foi possível validar o usuário MySQL exclusivo; o cliente usa as credenciais informadas.';
        } catch (Throwable $e) {
            $note = ' Atenção: sem permissão para criar um usuário MySQL exclusivo — o cliente usa as mesmas credenciais do principal. Recomendado criar um usuário com acesso só a este banco.';
        }
    }
    $t = bn_connect($db);
    if (!$t) throw new RuntimeException('O banco foi criado, mas não foi possível conectar a ele.');
    if (db_one($t, "SHOW TABLES LIKE 'configuracoes'")) throw new RuntimeException("O banco “{$name}” já contém dados do CMS. Escolha outro nome de banco.");
    $sql = (string)file_get_contents(BN_ROOT . '/sql/db_banco_negocios.sql');
    $sql = str_replace('Banco de Negócios', $nome, $sql);
    foreach (sql_split($sql) as $stmt) {
        if (preg_match('/^(CREATE DATABASE|USE)\b/i', $stmt)) continue;
        if (str_contains($stmt, 'admin@bancodenegocios.com.br')) continue; // não leva o admin de demonstração
        if (!$demo && preg_match('/^INSERT INTO (noticias|buscas_populares)\b/i', $stmt)) continue;
        $t->exec($stmt);
    }
    $t->prepare("INSERT INTO usuarios (nome,email,senha,nivel_acesso,status) VALUES (?,?,?,'admin','ativo')")->execute([$adminNome, strtolower($adminEmail), password_hash($adminSenha, PASSWORD_DEFAULT)]);
    $t->prepare("UPDATE configuracoes SET valor='6' WHERE chave='schema_version'")->execute(); // a v7 é aplicada no primeiro acesso
    $t->prepare("INSERT INTO configuracoes (chave,valor) VALUES ('topbar_text',?) ON DUPLICATE KEY UPDATE valor=VALUES(valor)")->execute(['Bem-vindo ao ' . $nome . '!']);
    return $note;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) { flash('error', 'Sessão expirada.'); redirect('clientes.php'); }
    $act = $_POST['acao'] ?? '';
    $slug = strtolower(trim((string)($_POST['slug'] ?? '')));
    $domains = array_values(array_filter(array_map(fn($d) => strtolower(preg_replace('#^https?://|/.*$#', '', trim($d))), preg_split('/[\s,]+/', (string)($_POST['dominios'] ?? '')))));
    try {
        if ($act === 'criar') {
            if (!preg_match('/^[a-z0-9][a-z0-9-]{1,40}$/', $slug) || isset($tenants[$slug])) throw new RuntimeException('Identificador inválido ou já usado (use letras minúsculas, números e hífen).');
            $nome = trim((string)$_POST['nome']);
            if ($nome === '') throw new RuntimeException('Informe o nome do cliente.');
            foreach ($tenants as $t) if (array_intersect($domains, (array)($t['dominios'] ?? []))) throw new RuntimeException('Um dos domínios já pertence a outro cliente.');
            $principal = bn_default_tenant()['db'];
            $db = [
                'host' => trim((string)($_POST['db_host'] ?? '')) ?: $principal['host'],
                'port' => (int)($_POST['db_port'] ?? 0) ?: (int)$principal['port'],
                'name' => trim((string)($_POST['db_name'] ?? '')) ?: 'cms_' . str_replace('-', '_', $slug),
                'user' => trim((string)($_POST['db_user'] ?? '')) ?: $principal['user'],
                'pass' => ($_POST['db_user'] ?? '') !== '' ? (string)($_POST['db_pass'] ?? '') : $principal['pass'],
            ];
            $email = filter_var(trim((string)$_POST['admin_email']), FILTER_VALIDATE_EMAIL);
            if (!$email) throw new RuntimeException('Informe o e-mail do administrador do cliente.');
            if ($p = password_problem((string)$_POST['admin_senha'])) throw new RuntimeException('Senha do administrador: ' . $p);
            $customUser = trim((string)($_POST['db_user'] ?? '')) !== '';
            $note = provision_tenant($db, $nome, trim((string)$_POST['admin_nome']) ?: 'Administrador', $email, (string)$_POST['admin_senha'], !empty($_POST['demo']), !$customUser);
            $tenants[$slug] = ['slug' => $slug, 'nome' => $nome, 'dominios' => $domains, 'ativo' => true, 'principal' => false, 'db' => $db, 'criado_em' => date('c')];
            bn_tenants_save($tenants);
            @mkdir(BN_ROOT . '/media/' . $slug, 0755, true);
            audit('cliente', 'clientes', null, ['acao' => 'criado', 'cliente' => $slug, 'banco' => $db['name']]);
            flash('success', "Cliente “{$nome}” criado com banco próprio ({$db['name']})." . $note . ' Acesse o painel dele para configurar identidade e tema.');
        } elseif ($act === 'editar' && isset($tenants[$slug])) {
            foreach ($tenants as $k => $t) if ($k !== $slug && array_intersect($domains, (array)($t['dominios'] ?? []))) throw new RuntimeException('Um dos domínios já pertence a outro cliente.');
            $tenants[$slug]['nome'] = trim((string)$_POST['nome']) ?: $tenants[$slug]['nome'];
            $tenants[$slug]['dominios'] = $domains;
            if ($slug !== 'principal') {
                $tenants[$slug]['ativo'] = !empty($_POST['ativo']);
                foreach (['host', 'name', 'user'] as $k) if (trim((string)($_POST['db_' . $k] ?? '')) !== '') $tenants[$slug]['db'][$k] = trim((string)$_POST['db_' . $k]);
                if (($_POST['db_pass'] ?? '') !== '') $tenants[$slug]['db']['pass'] = (string)$_POST['db_pass'];
            }
            bn_tenants_save($tenants);
            audit('cliente', 'clientes', null, ['acao' => 'editado', 'cliente' => $slug, 'ativo' => $tenants[$slug]['ativo'] ?? true]);
            flash('success', 'Cliente atualizado.');
        } elseif ($act === 'remover' && isset($tenants[$slug]) && $slug !== 'principal') {
            unset($tenants[$slug]);
            bn_tenants_save($tenants);
            audit('cliente', 'clientes', null, ['acao' => 'removido do cadastro', 'cliente' => $slug]);
            flash('success', 'Cliente removido do cadastro. O banco de dados e os arquivos NÃO foram apagados (remova-os manualmente no servidor se desejar).');
        }
    } catch (Throwable $ex) {
        flash('error', $ex->getMessage());
    }
    redirect('clientes.php');
}
$edit = isset($_GET['edit'], $tenants[$_GET['edit']]) ? $tenants[$_GET['edit']] : null;
$host = strtolower(preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? ''));
include __DIR__ . '/includes/header.php';
?>
<main class="admin-content">
  <div class="admin-heading">
    <div><h1>Clientes (multi-cliente)</h1><p class="muted">Uma instalação do CMS atende vários clientes. Cada um tem <strong>banco de dados e pasta de mídia próprios</strong>, identidade visual, tema, usuários e configurações independentes. O cliente é escolhido pelo domínio acessado.</p></div>
    <a class="btn btn-primary" href="?novo=1#form">+ Novo cliente</a>
  </div>
  <div class="admin-panel mb-4">
    <div class="table-responsive">
      <table class="admin-table">
        <thead><tr><th>Cliente</th><th>Identificador</th><th>Domínios</th><th>Banco</th><th>Conexão</th><th>Status</th><th>Ações</th></tr></thead>
        <tbody>
        <?php foreach ($tenants as $slug => $t): $conn = bn_connect($t['db']); $active = $edit && $edit['slug'] === $slug; ?>
          <tr class="<?= $active ? 'is-active-row' : '' ?>">
            <td><strong><?= e($t['nome']) ?></strong><?= $slug === 'principal' ? ' <span class="badge-soft">principal</span>' : '' ?><?= $slug === BN_TENANT ? ' <span class="badge-soft ok">atual</span>' : '' ?></td>
            <td><code><?= e($slug) ?></code></td>
            <td style="white-space:normal"><?= $t['dominios'] ? e(implode(', ', $t['dominios'])) : '<span class="muted">' . ($slug === 'principal' ? 'qualquer domínio não cadastrado' : 'nenhum') . '</span>' ?></td>
            <td><?= e($t['db']['name'] ?? '') ?></td>
            <td><?= $conn ? '<span class="badge-soft ok">ok</span>' : '<span class="badge-soft danger">falhou</span>' ?></td>
            <td><span class="status status-<?= !empty($t['ativo']) ? 'ativo' : 'inativo' ?>"><?= !empty($t['ativo']) ? 'Ativo' : 'Inativo' ?></span></td>
            <td class="actions">
              <a class="btn-action<?= $active ? ' is-active' : '' ?>" href="?edit=<?= e($slug) ?>#form">Editar</a>
              <?php if (bn_is_local_host($host)): ?><a class="btn-action" href="../admin/index.php?cliente=<?= e($slug) ?>" target="_blank">Abrir painel</a><a class="btn-action" href="../index.php?cliente=<?= e($slug) ?>" target="_blank">Ver site</a>
              <?php elseif ($t['dominios']): ?><a class="btn-action" href="https://<?= e($t['dominios'][0]) ?>/admin/" target="_blank">Abrir painel</a><?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php if (bn_is_local_host($host)): ?><p class="muted small mt-2">Ambiente local: alterne entre clientes com <code>?cliente=identificador</code> (fica lembrado no navegador). Em produção, aponte o domínio de cada cliente para esta mesma pasta.</p><?php endif; ?>
  </div>

  <?php if ($edit || isset($_GET['novo'])): $isNew = !$edit; ?>
  <div class="admin-panel edit-panel<?= $edit ? ' is-editing' : '' ?>" id="form">
    <div class="edit-panel-head"><?php if ($edit): ?><span class="edit-badge">✎ Editando</span><h2><?= e($edit['nome']) ?></h2><?php else: ?><h2>Novo cliente</h2><?php endif; ?><a class="btn btn-sm btn-outline-secondary ms-auto" href="clientes.php">Fechar</a></div>
    <form class="admin-form" method="post" autocomplete="off">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="acao" value="<?= $isNew ? 'criar' : 'editar' ?>">
      <div class="form-grid-2">
        <label>Nome do cliente<input name="nome" required value="<?= e($edit['nome'] ?? '') ?>" placeholder="Ex.: Associação Comercial XYZ"></label>
        <label>Identificador <small class="muted">letras minúsculas, números e hífen</small><input name="slug" required pattern="[a-z0-9][a-z0-9-]{1,40}" value="<?= e($edit['slug'] ?? '') ?>" <?= $edit ? 'readonly' : '' ?> placeholder="associacao-xyz"></label>
      </div>
      <label>Domínios <small class="muted">Um por linha, sem http:// (o “www.” é tratado automaticamente).</small><textarea name="dominios" style="min-height:70px" placeholder="associacaoxyz.com.br"><?= e(implode("\n", (array)($edit['dominios'] ?? []))) ?></textarea></label>
      <?php if (!$edit || $edit['slug'] !== 'principal'): ?>
      <h3 class="form-section">Banco de dados do cliente</h3>
      <p class="muted small"><?= $isNew ? 'Deixe em branco para criar o banco “cms_identificador” no mesmo servidor; o sistema tenta criar um usuário MySQL exclusivo para ele (isolamento entre clientes). Para isso, o usuário do principal precisa poder criar bancos e usuários. Ou informe um banco e usuário já criados.' : 'Deixe a senha vazia para manter a atual.' ?></p>
      <div class="form-grid-4">
        <label>Servidor<input name="db_host" value="<?= e($edit['db']['host'] ?? '') ?>" placeholder="127.0.0.1"></label>
        <label>Nome do banco<input name="db_name" value="<?= e($edit['db']['name'] ?? '') ?>" placeholder="cms_identificador"></label>
        <label>Usuário<input name="db_user" value="<?= e($edit['db']['user'] ?? '') ?>"></label>
        <label>Senha<input type="password" name="db_pass" autocomplete="new-password"></label>
      </div>
      <?php endif; ?>
      <?php if ($isNew): ?>
      <h3 class="form-section">Primeiro administrador do cliente</h3>
      <div class="form-grid-2"><label>Nome<input name="admin_nome" value="Administrador"></label><label>E-mail<input type="email" name="admin_email" required></label></div>
      <label>Senha inicial<input type="password" name="admin_senha" required minlength="8" autocomplete="new-password"></label>
      <label class="check-row"><input type="checkbox" name="demo" value="1" checked> Incluir conteúdo de exemplo (categorias, páginas institucionais e uma notícia)</label>
      <?php elseif ($edit['slug'] !== 'principal'): ?>
      <label class="check-row"><input type="checkbox" name="ativo" value="1" <?= !empty($edit['ativo']) ? 'checked' : '' ?>> Cliente ativo (inativo = o domínio passa a abrir o cliente principal)</label>
      <?php endif; ?>
      <div class="d-flex gap-2"><button class="btn btn-primary"><?= $isNew ? 'Criar cliente' : 'Salvar' ?></button>
      </div>
    </form>
    <?php if ($edit && $edit['slug'] !== 'principal'): ?>
    <form method="post" class="mt-3"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="acao" value="remover"><input type="hidden" name="slug" value="<?= e($edit['slug']) ?>">
      <button class="btn btn-outline-danger btn-sm" data-confirm="Remover o cliente “<?= e($edit['nome']) ?>” do cadastro? O site dele deixará de responder pelos domínios cadastrados. O banco e os arquivos não serão apagados." data-confirm-word="<?= e(strtoupper($edit['slug'])) ?>">Remover cliente</button></form>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
