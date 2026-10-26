<?php
declare(strict_types=1);

/* ==========================================================================
   Fluxo editorial (notícias e páginas)
   rascunho → em revisão → aprovado → publicado → arquivado
   - Editor: salva rascunho e envia para revisão.
   - Quem tem "aprovar:<tipo>": aprova ou devolve para ajustes (com comentário).
   - Quem tem "publicar:<tipo>": publica, despublica e arquiva.
   A coluna "status" (1 = visível no site) é mantida em sincronia com o estado.
   ========================================================================== */

function workflow_states(): array
{
    return [
        'rascunho' => ['Rascunho', 'secondary'],
        'revisao' => ['Em revisão', 'warning'],
        'aprovado' => ['Aprovado', 'info'],
        'publicado' => ['Publicado', 'success'],
        'arquivado' => ['Arquivado', 'dark'],
    ];
}

function workflow_badge(string $state): string
{
    [$label, $color] = workflow_states()[$state] ?? [$state, 'secondary'];
    return '<span class="wf-badge wf-' . e($state) . '">' . e($label) . '</span>';
}

/** Ações disponíveis para o usuário atual a partir de um estado. */
function workflow_actions(string $tipo, string $state, bool $isNew = false): array
{
    $acts = [];
    $canEdit = can(($isNew ? 'criar:' : 'editar:') . $tipo);
    $canApprove = can("aprovar:$tipo");
    $canPublish = can("publicar:$tipo");
    if (in_array($state, ['rascunho', 'arquivado', 'revisao'], true) && $canEdit) $acts['rascunho'] = $state === 'revisao' ? 'Salvar e voltar para rascunho' : 'Salvar rascunho';
    if (in_array($state, ['rascunho', 'arquivado'], true) && $canEdit) $acts['revisao'] = 'Enviar para revisão';
    if ($state === 'revisao' && $canEdit && !$canApprove) $acts['revisao'] = 'Salvar (continua em revisão)';
    if ($canApprove && in_array($state, ['revisao', 'rascunho'], true)) $acts['aprovado'] = 'Aprovar';
    if ($canPublish) {
        if ($state !== 'publicado') $acts['publicado'] = 'Publicar';
        else $acts['publicado'] = 'Atualizar publicação';
        if ($state === 'publicado') { $acts['rascunho'] = 'Despublicar (voltar a rascunho)'; $acts['arquivado'] = 'Arquivar'; }
        if ($state === 'aprovado') $acts['arquivado'] = 'Arquivar';
    }
    if ($state === 'aprovado' && $canApprove && !$canPublish) $acts['aprovado'] = 'Salvar (aprovado)';
    return $acts;
}

/** Usuários sem permissão de publicar não podem editar itens já aprovados/publicados. */
function workflow_can_edit(string $tipo, string $state): bool
{
    if (in_array($state, ['publicado', 'aprovado'], true)) return can("publicar:$tipo") || ($state === 'aprovado' && can("aprovar:$tipo"));
    return can("editar:$tipo");
}

/** Registra a transição, comentário e notifica por e-mail os envolvidos. */
function workflow_transition(string $tipo, int $id, string $from, string $to, string $comment, string $title): void
{
    global $pdo;
    if ($from === $to && $comment === '') return;
    $u = current_user();
    $pdo->prepare('INSERT INTO workflow_eventos (recurso,recurso_id,de,para,comentario,usuario_id,usuario_nome) VALUES (?,?,?,?,?,?,?)')
        ->execute([$tipo, $id, $from, $to, $comment, $u['id'] ?? null, $u['nome'] ?? 'Sistema']);
    if ($from !== $to) {
        audit('workflow', $tipo, $id, ['de' => $from, 'para' => $to, 'titulo' => $title, 'comentario' => $comment]);
        do_action('workflow_changed', $tipo, $id, $from, $to);
    }
    // Notificações
    $labels = workflow_states();
    $link = (trusted_base_url() ?? site_base_url()) . 'admin/' . (content_types()[$tipo]['admin'] ?? 'dashboard.php') . '?edit=' . $id;
    if ($to === 'revisao' && $from !== 'revisao') {
        $emails = users_with_perm("aprovar:$tipo");
        foreach ($emails as $em) send_mail($em, "Conteúdo aguardando revisão: $title", mail_template('Conteúdo aguardando revisão', '<p><strong>' . e($u['nome'] ?? '') . '</strong> enviou “' . e($title) . '” para revisão.</p>' . ($comment ? '<p>Comentário: ' . e($comment) . '</p>' : ''), $link, 'Revisar agora'));
    }
    if (in_array($to, ['rascunho', 'aprovado', 'publicado'], true) && $from === 'revisao') {
        $author = db_one($pdo, 'SELECT u.email FROM workflow_eventos w JOIN usuarios u ON u.id=w.usuario_id WHERE w.recurso=? AND w.recurso_id=? AND w.para=\'revisao\' ORDER BY w.id DESC LIMIT 1', [$tipo, $id]);
        if ($author && $author['email'] !== ($u['email'] ?? '')) {
            $msg = $to === 'rascunho' ? 'foi devolvido para ajustes' : 'foi ' . mb_strtolower($labels[$to][0]);
            send_mail($author['email'], "“{$title}” {$msg}", mail_template('Atualização do fluxo editorial', '<p>Seu conteúdo “' . e($title) . "” $msg por <strong>" . e($u['nome'] ?? '') . '</strong>.</p>' . ($comment ? '<p>Comentário: ' . e($comment) . '</p>' : ''), $link, 'Abrir conteúdo'));
        }
    }
}

function workflow_history(string $tipo, int $id): array
{
    global $pdo;
    return db_rows($pdo, 'SELECT * FROM workflow_eventos WHERE recurso=? AND recurso_id=? ORDER BY id DESC LIMIT 30', [$tipo, $id]);
}

/** E-mails dos usuários ativos que possuem a permissão (inclui administradores). */
function users_with_perm(string $perm): array
{
    global $pdo;
    $rows = db_rows($pdo, "SELECT DISTINCT u.email FROM usuarios u JOIN papeis p ON p.id=u.papel_id LEFT JOIN papel_permissoes pp ON pp.papel_id=p.id WHERE u.status='ativo' AND (p.slug='administrador' OR pp.permissao=?)", [$perm]);
    return array_column($rows, 'email');
}

/** Itens aguardando ação do usuário (painel). */
function workflow_queue(): array
{
    global $pdo;
    $out = [];
    foreach (['noticias' => 'Notícia', 'paginas' => 'Página'] as $t => $label) {
        if (can("aprovar:$t")) foreach (db_rows($pdo, "SELECT id,titulo,estado FROM $t WHERE estado='revisao' ORDER BY id DESC LIMIT 20") as $r) $out[] = $r + ['tipo' => $t, 'rotulo' => $label];
        if (can("publicar:$t")) foreach (db_rows($pdo, "SELECT id,titulo,estado FROM $t WHERE estado='aprovado' ORDER BY id DESC LIMIT 20") as $r) $out[] = $r + ['tipo' => $t, 'rotulo' => $label];
    }
    return $out;
}
