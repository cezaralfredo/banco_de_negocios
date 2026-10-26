<?php
declare(strict_types=1);

/* ==========================================================================
   Logs de auditoria: quem fez o quê, quando e de onde.
   audit('excluir', 'noticias', 12, ['titulo' => 'Feira']);
   ========================================================================== */

function audit_actions(): array
{
    return [
        'login' => 'Login', 'login_falhou' => 'Tentativa de login falhou', 'login_bloqueado' => 'Login bloqueado', 'logout' => 'Logout',
        'sessao_expirada' => 'Sessão expirada', 'senha_redefinida' => 'Senha redefinida', 'senha_reset_solicitado' => 'Redefinição de senha solicitada',
        'criar' => 'Criação', 'editar' => 'Edição', 'excluir' => 'Exclusão (lixeira)', 'restaurar' => 'Restauração da lixeira',
        'excluir_definitivo' => 'Exclusão definitiva', 'restaurar_versao' => 'Versão restaurada', 'workflow' => 'Mudança de estado editorial',
        'permissoes' => 'Alteração de permissões', 'papel_usuario' => 'Alteração de papel de usuário',
        'manutencao_ativada' => 'Manutenção ativada', 'manutencao_desativada' => 'Manutenção desativada', 'manutencao_config' => 'Manutenção configurada',
        'configuracoes' => 'Configurações alteradas', 'tema' => 'Tema alterado', 'plugin' => 'Plugin ativado/desativado',
        'backup_criado' => 'Backup gerado', 'backup_baixado' => 'Backup baixado', 'backup_restaurado' => 'Backup restaurado', 'backup_excluido' => 'Backup excluído',
        'midia_enviada' => 'Mídia enviada', 'midia_editada' => 'Mídia editada', 'cliente' => 'Cliente (multi-cliente)',
        'acesso_negado' => 'Acesso negado', 'email_teste' => 'E-mail de teste',
    ];
}

function audit(string $acao, string $recurso = '', $recursoId = null, array $detalhes = []): void
{
    global $pdo;
    if (!$pdo) return;
    $u = $GLOBALS['__bn_audit_user'] ?? (function_exists('current_user') ? current_user() : null);
    try {
        $pdo->prepare('INSERT INTO auditoria (usuario_id,usuario_nome,acao,recurso,recurso_id,detalhes,ip,user_agent) VALUES (?,?,?,?,?,?,?,?)')->execute([
            $u['id'] ?? null,
            $u['nome'] ?? ($detalhes['email'] ?? 'Sistema'),
            $acao,
            $recurso,
            $recursoId === null ? null : (string)$recursoId,
            $detalhes ? json_encode($detalhes, JSON_UNESCAPED_UNICODE) : null,
            client_ip(),
            mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? (PHP_SAPI === 'cli' ? 'cli' : '')), 0, 255),
        ]);
    } catch (Throwable $e) {
        error_log('[CMS] Falha ao gravar auditoria: ' . $e->getMessage());
    }
}
