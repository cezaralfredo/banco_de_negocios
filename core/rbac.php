<?php
declare(strict_types=1);

/* ==========================================================================
   Controle de acesso baseado em papéis (RBAC)

   Permissões no formato "acao:recurso" (ex.: ler:noticias, publicar:noticias).
   Cada usuário tem um papel; cada papel tem um conjunto de permissões.
   O papel "administrador" (sistema) possui todas as permissões.
   Princípio do menor privilégio: usuários sem papel não acessam o painel.
   ========================================================================== */

/** Catálogo de permissões: chave => [grupo, descrição]. Plugins podem estender via filtro "permissions". */
function permission_catalog(): array
{
    static $cat = null;
    if ($cat !== null) return $cat;
    $content = [
        'anuncios' => 'anúncios', 'noticias' => 'notícias', 'paginas' => 'páginas',
    ];
    $cat = ['acessar:painel' => ['Painel', 'Acessar o painel administrativo']];
    foreach ($content as $r => $label) {
        $g = ucfirst($label);
        $cat["ler:$r"] = [$g, "Ver $label no painel"];
        $cat["criar:$r"] = [$g, "Criar $label"];
        $cat["editar:$r"] = [$g, "Editar $label"];
        if ($r !== 'anuncios') $cat["aprovar:$r"] = [$g, "Aprovar ou devolver $label em revisão"];
        $cat["publicar:$r"] = [$g, "Publicar, despublicar e arquivar $label"];
        $cat["excluir:$r"] = [$g, "Excluir $label (vão para a lixeira)"];
    }
    $simple = [
        'categorias' => 'Categorias', 'banners' => 'Banners e textos do banner', 'promocao' => 'Promoção da home',
        'menu' => 'Menu principal', 'rodape' => 'Rodapé e redes sociais', 'idiomas' => 'Idiomas e traduções',
    ];
    foreach ($simple as $r => $g) {
        $cat["ler:$r"] = [$g, "Ver $g"];
        $cat["editar:$r"] = [$g, "Criar e editar $g"];
        $cat["excluir:$r"] = [$g, "Excluir itens de $g"];
    }
    $cat += [
        'ler:midia' => ['Mídia', 'Ver a biblioteca de mídia'],
        'enviar:midia' => ['Mídia', 'Enviar, organizar, cortar e redimensionar arquivos'],
        'excluir:midia' => ['Mídia', 'Excluir arquivos da biblioteca'],
        'editar:seo' => ['SEO', 'Editar título SEO, descrição, Open Graph e indexação'],
        'ler:versoes' => ['Versões', 'Ver histórico de alterações e comparar versões'],
        'restaurar:versoes' => ['Versões', 'Restaurar versões anteriores'],
        'ler:lixeira' => ['Lixeira', 'Ver itens excluídos'],
        'restaurar:lixeira' => ['Lixeira', 'Restaurar itens excluídos'],
        'esvaziar:lixeira' => ['Lixeira', 'Excluir definitivamente itens da lixeira'],
        'gerenciar:temas' => ['Aparência', 'Trocar e personalizar o tema do site'],
        'gerenciar:configuracoes' => ['Sistema', 'Configurações globais, identidade e integrações'],
        'gerenciar:usuarios' => ['Sistema', 'Criar, editar e desativar usuários'],
        'gerenciar:papeis' => ['Sistema', 'Criar papéis e alterar permissões'],
        'gerenciar:manutencao' => ['Sistema', 'Ativar e configurar o modo de manutenção'],
        'acessar:manutencao' => ['Sistema', 'Ver o site enquanto estiver em manutenção (bypass)'],
        'gerenciar:backup' => ['Sistema', 'Gerar, baixar e restaurar backups'],
        'ler:auditoria' => ['Sistema', 'Ver os logs de auditoria'],
        'gerenciar:plugins' => ['Sistema', 'Ativar e desativar plugins'],
        'gerenciar:clientes' => ['Plataforma', 'Gerenciar clientes (multi-cliente) — somente na instalação principal'],
    ];
    $cat = apply_filters('permissions', $cat);
    return $cat;
}

/** Papéis padrão criados na instalação. */
function default_roles(): array
{
    $all = array_keys(permission_catalog());
    $content = array_values(array_filter($all, fn($p) => preg_match('/:(anuncios|noticias|paginas|categorias|banners|promocao|menu|rodape|idiomas|midia|seo|versoes|lixeira)$/', $p)));
    $content = array_values(array_diff($content, ['esvaziar:lixeira']));
    $editor = array_values(array_filter($content, fn($p) => !preg_match('/^(publicar|aprovar|excluir|restaurar|esvaziar):/', $p) || $p === 'restaurar:versoes'));
    $editor = array_values(array_diff($editor, ['restaurar:versoes', 'ler:lixeira']));
    $reader = array_values(array_filter($all, fn($p) => str_starts_with($p, 'ler:') && $p !== 'ler:auditoria'));
    return [
        'administrador' => ['Administrador', 'Acesso total ao CMS.', ['*']],
        'editor-chefe' => ['Editor-chefe', 'Gerencia todo o conteúdo: aprova, publica, arquiva e exclui. Não acessa configurações do sistema.', array_merge(['acessar:painel', 'acessar:manutencao'], $content)],
        'editor' => ['Editor', 'Cria e edita conteúdo e envia para revisão. Não publica nem exclui.', array_merge(['acessar:painel'], $editor)],
        'leitor' => ['Leitor', 'Somente leitura do conteúdo no painel.', array_merge(['acessar:painel'], $reader)],
    ];
}

function current_user(): ?array
{
    global $pdo;
    start_session();
    $id = (int)($_SESSION['admin_id'] ?? 0);
    // cache por requisição, recalculado se o usuário da sessão mudar (login/logout)
    if (array_key_exists('__bn_user', $GLOBALS) && ($GLOBALS['__bn_user_id'] ?? -1) === $id) return $GLOBALS['__bn_user'];
    $GLOBALS['__bn_user_id'] = $id;
    $cache = &$GLOBALS['__bn_user'];
    if (!$id || !$pdo) return $cache = null;
    $u = db_one($pdo, 'SELECT u.id,u.nome,u.email,u.nivel_acesso,u.status,u.papel_id,p.slug papel_slug,p.nome papel_nome FROM usuarios u LEFT JOIN papeis p ON p.id=u.papel_id WHERE u.id=?', [$id]);
    if (!$u || ($u['status'] ?? 'ativo') !== 'ativo') return $cache = null;
    // Compatibilidade: contas antigas com nivel_acesso=admin e sem papel = administrador
    if (!$u['papel_id'] && $u['nivel_acesso'] === 'admin') {
        $u['papel_slug'] = 'administrador';
        $u['papel_nome'] = 'Administrador';
    }
    $u['permissoes'] = user_permissions($u);
    return $cache = $u;
}

function user_permissions(array $u): array
{
    global $pdo;
    if (($u['papel_slug'] ?? '') === 'administrador') return ['*'];
    if (empty($u['papel_id'])) return [];
    return array_column(db_rows($pdo, 'SELECT permissao FROM papel_permissoes WHERE papel_id=?', [(int)$u['papel_id']]), 'permissao');
}

/** Verifica se o usuário logado tem a permissão. */
function can(string $perm): bool
{
    $u = current_user();
    if (!$u) return false;
    if (in_array('*', $u['permissoes'], true)) return $perm !== 'gerenciar:clientes' || is_primary_tenant();
    return in_array($perm, $u['permissoes'], true);
}

function can_any(array $perms): bool
{
    foreach ($perms as $p) if (can($p)) return true;
    return false;
}

/** Para endpoints (AJAX/download): encerra com 403 se faltar a permissão.
    Páginas do painel usam $admin_perm antes de incluir admin/includes/header.php. */
function require_perm(string $perm): void
{
    require_admin();
    if (can($perm)) return;
    audit('acesso_negado', 'permissao', null, ['permissao' => $perm, 'pagina' => basename($_SERVER['SCRIPT_NAME'] ?? '')]);
    http_response_code(403);
    if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'json') || ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'Você não tem permissão para esta ação.']);
    } else {
        echo 'Acesso negado: você não tem a permissão necessária (' . e($perm) . ').';
    }
    exit;
}

/** Verificação dentro de uma ação: redireciona com aviso se faltar permissão. */
function deny_unless(string $perm, string $back = ''): void
{
    if (can($perm)) return;
    audit('acesso_negado', 'permissao', null, ['permissao' => $perm, 'pagina' => basename($_SERVER['SCRIPT_NAME'] ?? '')]);
    flash('error', 'Você não tem permissão para esta ação (' . perm_label($perm) . ').');
    header('Location: ' . ($back ?: basename($_SERVER['SCRIPT_NAME'] ?? 'dashboard.php')));
    exit;
}

function perm_label(string $perm): string
{
    return permission_catalog()[$perm][1] ?? $perm;
}

/** Usado por todas as páginas do painel: exige login e a permissão de acesso ao painel. */
function require_admin(): void
{
    start_session();
    $u = current_user();
    if (!$u || !can('acessar:painel')) {
        if (!empty($_SESSION['admin_id']) && $u === null) unset($_SESSION['admin_id']);
        $back = urlencode($_SERVER['REQUEST_URI'] ?? '');
        header('Location: index.php' . ($back ? '?voltar=' . $back : ''));
        exit;
    }
    // Expiração por inatividade
    $limit = max(5, (int)setting($GLOBALS['pdo'], 'seg_sessao_minutos', '120')) * 60;
    if (!empty($_SESSION['last_activity']) && time() - $_SESSION['last_activity'] > $limit) {
        audit('sessao_expirada', 'usuario', (int)$u['id']);
        session_unset();
        header('Location: index.php?expirada=1');
        exit;
    }
    $_SESSION['last_activity'] = time();
}

function require_super_admin(): void
{
    require_perm('gerenciar:configuracoes');
}

function roles_all(): array
{
    global $pdo;
    return db_rows($pdo, 'SELECT p.*, (SELECT COUNT(*) FROM usuarios u WHERE u.papel_id=p.id) usuarios FROM papeis p ORDER BY p.sistema DESC, p.nome');
}

/** Cria os papéis padrão se ainda não existirem e associa contas antigas. */
function rbac_seed(PDO $pdo): void
{
    foreach (default_roles() as $slug => [$nome, $desc, $perms]) {
        if (db_one($pdo, 'SELECT id FROM papeis WHERE slug=?', [$slug])) continue;
        $pdo->prepare('INSERT INTO papeis (slug,nome,descricao,sistema) VALUES (?,?,?,?)')->execute([$slug, $nome, $desc, $slug === 'administrador' ? 1 : 0]);
        $id = (int)$pdo->lastInsertId();
        $ins = $pdo->prepare('INSERT IGNORE INTO papel_permissoes (papel_id,permissao) VALUES (?,?)');
        foreach ($perms as $p) if ($p !== '*') $ins->execute([$id, $p]);
    }
    $admin = db_one($pdo, "SELECT id FROM papeis WHERE slug='administrador'");
    if ($admin) $pdo->prepare("UPDATE usuarios SET papel_id=? WHERE nivel_acesso='admin' AND papel_id IS NULL")->execute([(int)$admin['id']]);
}

/* ---------------- Login com proteção contra força bruta ---------------- */

function client_ip(): string
{
    return (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}

function login_blocked(string $email): int
{
    global $pdo;
    $max = max(3, (int)setting($pdo, 'seg_tentativas_login', '5'));
    $min = max(1, (int)setting($pdo, 'seg_bloqueio_minutos', '15'));
    // por conta: N tentativas; por IP (várias contas): 4×N — evita bloquear um escritório inteiro atrás do mesmo IP
    $r = db_one($pdo, 'SELECT SUM(email=?) n_email, COUNT(*) n_ip, MAX(criado_em) ultimo FROM login_tentativas WHERE (email=? OR ip=?) AND sucesso=0 AND criado_em > (NOW() - INTERVAL ? MINUTE)', [strtolower($email), strtolower($email), client_ip(), $min]);
    if ((int)($r['n_email'] ?? 0) < $max && (int)($r['n_ip'] ?? 0) < $max * 4) return 0;
    return max(1, $min - (int)floor((time() - strtotime((string)$r['ultimo'])) / 60));
}

function login_attempt(string $email, bool $ok): void
{
    global $pdo;
    try {
        $pdo->prepare('INSERT INTO login_tentativas (email,ip,sucesso) VALUES (?,?,?)')->execute([strtolower($email), client_ip(), $ok ? 1 : 0]);
        if ($ok) $pdo->prepare('DELETE FROM login_tentativas WHERE (email=? OR ip=?) AND sucesso=0')->execute([strtolower($email), client_ip()]);
        if (random_int(1, 50) === 1) $pdo->exec('DELETE FROM login_tentativas WHERE criado_em < (NOW() - INTERVAL 7 DAY)');
    } catch (Throwable $e) { }
}

/** Política de senha configurável em Configurações globais › Segurança. */
function password_problem(string $senha): ?string
{
    global $pdo;
    $min = max(8, (int)setting($pdo, 'seg_senha_min', '8'));
    if (mb_strlen($senha) < $min) return "A senha deve ter pelo menos {$min} caracteres.";
    if (setting($pdo, 'seg_senha_forte', '1') === '1' && (!preg_match('/[A-Za-z]/', $senha) || !preg_match('/\d/', $senha))) {
        return 'A senha deve conter letras e números.';
    }
    return null;
}
