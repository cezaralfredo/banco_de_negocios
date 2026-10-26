<?php
declare(strict_types=1);

/* ==========================================================================
   Lixeira e "Desfazer"
   Toda exclusão feita pelo painel guarda uma cópia do registro (e dos registros
   relacionados) antes de apagar. A mensagem de sucesso traz o link "Desfazer";
   os itens ficam em Admin › Lixeira até serem restaurados ou expirarem.
   ========================================================================== */

/**
 * Move um registro para a lixeira.
 * $related: [['tabela', 'coluna_fk'], ...] registros dependentes que também serão guardados/apagados.
 * Retorna o ID do item na lixeira (para o link "Desfazer").
 */
function trash_delete(string $table, int $id, string $label, string $recurso = '', array $related = []): ?int
{
    global $pdo;
    if (!preg_match('/^[a-z_]+$/', $table)) return null;
    $row = db_one($pdo, "SELECT * FROM `$table` WHERE id=?", [$id]);
    if (!$row && $table === 'anuncio_destaques') return null;
    if (!$row) return null;
    $rel = [];
    foreach ($related as $r) {
        [$rt, $col] = $r;
        $val = $r[2] ?? $id; // valor da chave (padrão: o id do registro)
        if (!preg_match('/^[a-z_]+$/', $rt . $col)) continue;
        $rel[$rt] = ['coluna' => $col, 'valor' => $val, 'linhas' => db_rows($pdo, "SELECT * FROM `$rt` WHERE `$col`=?", [$val])];
    }
    $file = null;
    if ($table === 'midias' && !empty($row['arquivo'])) {
        // o arquivo físico vai para storage/<cliente>/lixeira até a exclusão definitiva
        $src = BN_ROOT . '/' . $row['arquivo'];
        if (is_file($src)) {
            $dst = storage_path('lixeira') . '/' . $id . '_' . basename($src);
            if (@rename($src, $dst)) $file = $dst;
        }
    }
    $pdo->beginTransaction();
    try {
        $pdo->prepare('INSERT INTO lixeira (tabela,registro_id,recurso,rotulo,dados,relacionados,arquivo,usuario_id,usuario_nome) VALUES (?,?,?,?,?,?,?,?,?)')->execute([
            $table, $id, $recurso ?: $table, mb_substr($label, 0, 200),
            json_encode($row, JSON_UNESCAPED_UNICODE), $rel ? json_encode($rel, JSON_UNESCAPED_UNICODE) : null, $file,
            current_user()['id'] ?? null, current_user()['nome'] ?? 'Sistema',
        ]);
        $trashId = (int)$pdo->lastInsertId();
        foreach ($rel as $rt => $info) $pdo->prepare("DELETE FROM `$rt` WHERE `{$info['coluna']}`=?")->execute([$info['valor']]);
        $pdo->prepare("DELETE FROM `$table` WHERE id=?")->execute([$id]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        if ($file) @rename($file, BN_ROOT . '/' . $row['arquivo']);
        throw $e;
    }
    audit('excluir', $recurso ?: $table, $id, ['rotulo' => $label, 'lixeira' => $trashId]);
    do_action('content_deleted', $recurso ?: $table, $id, $row);
    return $trashId;
}

/** Restaura um item da lixeira para a tabela original. */
function trash_restore(int $trashId): array
{
    global $pdo;
    $item = db_one($pdo, 'SELECT * FROM lixeira WHERE id=?', [$trashId]);
    if (!$item) return [false, 'Item não encontrado na lixeira (talvez já tenha sido restaurado).'];
    $row = json_decode((string)$item['dados'], true) ?: [];
    $table = $item['tabela'];
    if (db_one($pdo, "SELECT id FROM `$table` WHERE id=?", [(int)$item['registro_id']])) {
        return [false, 'Já existe um registro com o mesmo ID. Não foi possível restaurar.'];
    }
    $pdo->beginTransaction();
    try {
        trash_insert_row($table, $row);
        foreach ((array)json_decode((string)$item['relacionados'], true) as $rt => $info) {
            foreach ((array)($info['linhas'] ?? []) as $r) trash_insert_row($rt, $r, true);
        }
        $pdo->prepare('DELETE FROM lixeira WHERE id=?')->execute([$trashId]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        return [false, 'Erro ao restaurar: ' . $e->getMessage()];
    }
    if (!empty($item['arquivo']) && is_file($item['arquivo']) && !empty($row['arquivo'])) {
        $dst = BN_ROOT . '/' . $row['arquivo'];
        if (!is_dir(dirname($dst))) @mkdir(dirname($dst), 0755, true);
        @rename($item['arquivo'], $dst);
    }
    audit('restaurar', $item['recurso'], $item['registro_id'], ['rotulo' => $item['rotulo']]);
    do_action('content_restored', $item['recurso'], (int)$item['registro_id'], $row);
    return [true, '“' . $item['rotulo'] . '” foi restaurado.'];
}

function trash_insert_row(string $table, array $row, bool $ignore = false): void
{
    global $pdo;
    if (!$row || !preg_match('/^[a-z_]+$/', $table)) return;
    // só insere colunas que ainda existem na tabela
    $cols = array_column(db_rows($pdo, "SHOW COLUMNS FROM `$table`"), 'Field');
    $row = array_intersect_key($row, array_flip($cols));
    $names = implode(',', array_map(fn($c) => "`$c`", array_keys($row)));
    $marks = implode(',', array_fill(0, count($row), '?'));
    $pdo->prepare('INSERT ' . ($ignore ? 'IGNORE ' : '') . "INTO `$table` ($names) VALUES ($marks)")->execute(array_values($row));
}

/** Exclui definitivamente (inclusive o arquivo de mídia guardado). */
function trash_purge(int $trashId): void
{
    global $pdo;
    $item = db_one($pdo, 'SELECT * FROM lixeira WHERE id=?', [$trashId]);
    if (!$item) return;
    if (!empty($item['arquivo']) && is_file($item['arquivo'])) @unlink($item['arquivo']);
    $pdo->prepare('DELETE FROM lixeira WHERE id=?')->execute([$trashId]);
    audit('excluir_definitivo', $item['recurso'], $item['registro_id'], ['rotulo' => $item['rotulo']]);
}

/** Remove automaticamente itens mais antigos que o prazo de retenção. */
function trash_expire(): void
{
    global $pdo;
    $days = max(1, (int)setting($pdo, 'lixeira_dias', '30'));
    foreach (db_rows($pdo, 'SELECT id FROM lixeira WHERE criado_em < (NOW() - INTERVAL ? DAY)', [$days]) as $r) trash_purge((int)$r['id']);
}

/** Link "Desfazer" para usar em mensagens flash. */
function undo_link(?int $trashId, string $back): string
{
    if (!$trashId) return '';
    return 'lixeira.php?restaurar=' . $trashId . '&token=' . urlencode(csrf_token()) . '&voltar=' . urlencode($back);
}
