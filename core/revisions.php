<?php
declare(strict_types=1);

/* ==========================================================================
   Controle de versão do conteúdo
   Cada vez que um conteúdo é salvo, uma cópia completa é registrada em "revisoes"
   (quem, quando, o quê). É possível comparar duas versões e restaurar qualquer uma.
   ========================================================================== */

/** Tipos de conteúdo versionados: campos comparáveis/restauráveis e rótulos. */
function content_types(): array
{
    return apply_filters('content_types', [
        'noticias' => ['tabela' => 'noticias', 'rotulo' => 'Notícia', 'titulo' => 'titulo', 'admin' => 'gerenciar_noticias.php', 'perm' => 'noticias',
            'campos' => ['titulo' => 'Título', 'slug' => 'Slug', 'resumo' => 'Resumo', 'conteudo' => 'Conteúdo', 'imagem' => 'Imagem', 'autor' => 'Autor', 'destaque' => 'Destaque', 'publicado_em' => 'Publicação', 'seo_titulo' => 'Título SEO', 'seo_descricao' => 'Meta description', 'og_imagem' => 'Imagem Open Graph', 'indexar' => 'Indexar']],
        'paginas' => ['tabela' => 'paginas', 'rotulo' => 'Página', 'titulo' => 'titulo', 'admin' => 'configuracoes.php', 'perm' => 'paginas',
            'campos' => ['titulo' => 'Título', 'slug' => 'Slug', 'conteudo' => 'Conteúdo', 'imagem' => 'Imagem de capa', 'parent_id' => 'Página pai', 'ordem' => 'Ordem', 'seo_titulo' => 'Título SEO', 'seo_descricao' => 'Meta description', 'og_imagem' => 'Imagem Open Graph', 'indexar' => 'Indexar']],
        'anuncios' => ['tabela' => 'anuncios', 'rotulo' => 'Anúncio', 'titulo' => 'titulo', 'admin' => 'gerenciar_anuncios.php', 'perm' => 'anuncios',
            'campos' => ['titulo' => 'Título', 'slug' => 'Slug', 'descricao' => 'Descrição', 'categoria_id' => 'Categoria', 'tipo' => 'Tipo', 'imagem_destaque' => 'Capa', 'logo' => 'Logo', 'whatsapp' => 'WhatsApp', 'localizacao' => 'Localização', 'link' => 'Link', 'avaliacao' => 'Avaliação', 'status' => 'Status', 'seo_titulo' => 'Título SEO', 'seo_descricao' => 'Meta description', 'og_imagem' => 'Imagem Open Graph', 'indexar' => 'Indexar']],
        'banners' => ['tabela' => 'banners', 'rotulo' => 'Banner', 'titulo' => 'titulo', 'admin' => 'gerenciar_banners.php', 'perm' => 'banners',
            'campos' => ['titulo' => 'Título', 'subtitulo' => 'Subtítulo', 'imagem_fundo' => 'Imagem', 'texto_botao' => 'Texto do botão', 'link_botao' => 'Link do botão', 'status' => 'Ativo', 'duracao_segundos' => 'Tempo', 'efeito' => 'Efeito', 'ordem' => 'Ordem']],
    ]);
}

/** Registra uma nova versão do registro (chame logo após salvar). */
function revision_save(string $tipo, int $id, string $resumo = ''): void
{
    global $pdo;
    $ct = content_types()[$tipo] ?? null;
    if (!$ct || !$id) return;
    $row = db_one($pdo, "SELECT * FROM `{$ct['tabela']}` WHERE id=?", [$id]);
    if (!$row) return;
    $data = array_intersect_key($row, $ct['campos'] + ['estado' => 1]);
    $last = db_one($pdo, 'SELECT numero,dados FROM revisoes WHERE recurso=? AND recurso_id=? ORDER BY numero DESC LIMIT 1', [$tipo, $id]);
    if ($last && json_decode((string)$last['dados'], true) == $data && $resumo === '') return; // nada mudou
    $num = (int)($last['numero'] ?? 0) + 1;
    $u = current_user();
    $pdo->prepare('INSERT INTO revisoes (recurso,recurso_id,numero,dados,resumo,usuario_id,usuario_nome) VALUES (?,?,?,?,?,?,?)')
        ->execute([$tipo, $id, $num, json_encode($data, JSON_UNESCAPED_UNICODE), $resumo ?: ($num === 1 ? 'Criação' : 'Edição'), $u['id'] ?? null, $u['nome'] ?? 'Sistema']);
    // Mantém no máximo N versões por item
    $keep = max(5, (int)setting($pdo, 'versoes_max', '50'));
    $pdo->prepare('DELETE FROM revisoes WHERE recurso=? AND recurso_id=? AND numero <= ?')->execute([$tipo, $id, $num - $keep]);
    do_action('content_saved', $tipo, $id, $row);
}

function revisions_list(string $tipo, int $id): array
{
    global $pdo;
    return db_rows($pdo, 'SELECT id,numero,resumo,usuario_nome,criado_em FROM revisoes WHERE recurso=? AND recurso_id=? ORDER BY numero DESC', [$tipo, $id]);
}

/** Restaura os campos de uma versão e registra uma nova versão "Restaurado da versão N". */
function revision_restore(int $revId, string $tipo, int $id): array
{
    global $pdo;
    $rev = db_one($pdo, 'SELECT * FROM revisoes WHERE id=? AND recurso=? AND recurso_id=?', [$revId, $tipo, $id]);
    $ct = $rev ? (content_types()[$rev['recurso']] ?? null) : null;
    if (!$rev || !$ct) return [false, 'Versão não encontrada.'];
    $data = json_decode((string)$rev['dados'], true) ?: [];
    $cols = array_column(db_rows($pdo, "SHOW COLUMNS FROM `{$ct['tabela']}`"), 'Field');
    $data = array_intersect_key($data, array_flip($cols));
    unset($data['estado']); // o estado editorial não volta junto (evita publicar sem aprovação)
    if (!can('publicar:' . ($ct['perm'] ?? $rev['recurso']))) unset($data['status']); // publicar/despublicar exige permissão própria
    if (!$data) return [false, 'Versão vazia.'];
    $set = implode(',', array_map(fn($c) => "`$c`=?", array_keys($data)));
    $pdo->prepare("UPDATE `{$ct['tabela']}` SET $set WHERE id=?")->execute([...array_values($data), (int)$rev['recurso_id']]);
    revision_save($rev['recurso'], (int)$rev['recurso_id'], 'Restaurado da versão ' . $rev['numero']);
    audit('restaurar_versao', $rev['recurso'], $rev['recurso_id'], ['versao' => (int)$rev['numero']]);
    return [true, 'Versão ' . $rev['numero'] . ' restaurada.'];
}

/** Diff simples por palavras (LCS) com marcação <del>/<ins>, já escapado. */
function text_diff(string $old, string $new): string
{
    if ($old === $new) return nl2br(e($new));
    $a = preg_split('/(\s+)/u', $old, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
    $b = preg_split('/(\s+)/u', $new, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
    $n = count($a); $m = count($b);
    if ($n * $m > 4000000) { // textos enormes: mostra antes/depois
        return '<del>' . nl2br(e($old)) . '</del><br><ins>' . nl2br(e($new)) . '</ins>';
    }
    $L = array_fill(0, $n + 1, array_fill(0, $m + 1, 0));
    for ($i = $n - 1; $i >= 0; $i--) for ($j = $m - 1; $j >= 0; $j--) {
        $L[$i][$j] = $a[$i] === $b[$j] ? $L[$i + 1][$j + 1] + 1 : max($L[$i + 1][$j], $L[$i][$j + 1]);
    }
    $out = ''; $i = 0; $j = 0;
    while ($i < $n && $j < $m) {
        if ($a[$i] === $b[$j]) { $out .= e($a[$i]); $i++; $j++; }
        elseif ($L[$i + 1][$j] >= $L[$i][$j + 1]) { $out .= '<del>' . e($a[$i]) . '</del>'; $i++; }
        else { $out .= '<ins>' . e($b[$j]) . '</ins>'; $j++; }
    }
    while ($i < $n) $out .= '<del>' . e($a[$i++]) . '</del>';
    while ($j < $m) $out .= '<ins>' . e($b[$j++]) . '</ins>';
    return nl2br(preg_replace('#</del><del>|</ins><ins>#', '', $out));
}
