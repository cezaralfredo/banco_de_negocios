<?php
declare(strict_types=1);

/* ==========================================================================
   Biblioteca de mídia
   - Arquivos ficam em media/<cliente>/AAAA/MM/ (um diretório por cliente).
   - Cada arquivo é registrado na tabela "midias" (pasta, alt, dimensões, autor).
   - Corte e redimensionamento via GD; "onde é usado" varre o conteúdo.
   ========================================================================== */

function media_allowed(): array
{
    return [
        'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif',
        'application/pdf' => 'pdf',
    ];
}

function media_max_bytes(): int
{
    global $pdo;
    return max(1, (int)setting($pdo, 'midia_max_mb', '8')) * 1048576;
}

function media_ensure_dir(string $rel): string
{
    $abs = BN_ROOT . '/' . $rel;
    if (!is_dir($abs) && !mkdir($abs, 0755, true)) throw new RuntimeException('Não foi possível criar a pasta de mídia.');
    // impede execução de scripts na pasta de mídia
    $ht = BN_ROOT . '/media/.htaccess';
    if (!is_file($ht)) @file_put_contents($ht, "Options -Indexes\n<FilesMatch \"\\.(php\\d?|phtml|phar|pl|py|cgi|sh|html?|svg)$\">\n  Require all denied\n</FilesMatch>\n");
    if (!is_writable($abs)) throw new RuntimeException('A pasta de mídia não tem permissão de escrita.');
    return $abs;
}

/**
 * Salva um upload e registra na biblioteca. Retorna o caminho relativo (ex.: media/principal/2026/10/foto.jpg).
 * Compatível com a função antiga upload_image_file().
 */
function media_store_upload(array $file, string $prefix = 'arquivo', int $maxW = 2000, int $maxH = 2000, ?int $folderId = null, bool $imagesOnly = true): ?string
{
    global $pdo;
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
    if (($file['error'] ?? 1) !== UPLOAD_ERR_OK) throw new RuntimeException('Falha no envio do arquivo (código ' . (int)$file['error'] . ').');
    if (($file['size'] ?? 0) > media_max_bytes()) throw new RuntimeException('O arquivo excede o limite de ' . round(media_max_bytes() / 1048576) . ' MB.');
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $allowed = media_allowed();
    if ($imagesOnly) $allowed = array_filter($allowed, fn($k) => str_starts_with($k, 'image/'), ARRAY_FILTER_USE_KEY);
    if (!isset($allowed[$mime])) throw new RuntimeException('Formato não permitido. Use ' . strtoupper(implode(', ', array_unique($allowed))) . '.');
    $ext = $allowed[$mime];
    $relDir = media_base() . '/' . date('Y/m');
    $absDir = media_ensure_dir($relDir);
    $base = slugify(pathinfo((string)($file['name'] ?? $prefix), PATHINFO_FILENAME));
    $name = mb_substr($base ?: $prefix, 0, 60) . '-' . bin2hex(random_bytes(3)) . '.' . $ext;
    $target = $absDir . '/' . $name;
    [$w, $h] = [null, null];
    if (str_starts_with($mime, 'image/') && $ext !== 'gif' && extension_loaded('gd')) {
        $src = @imagecreatefromstring((string)file_get_contents($file['tmp_name']));
        if (!$src) throw new RuntimeException('Não foi possível ler a imagem.');
        $src = media_fix_orientation($src, $file['tmp_name'], $mime);
        [$w, $h] = media_save_resized($src, $target, $ext, $maxW, $maxH);
        imagedestroy($src);
    } else {
        if (!move_uploaded_file($file['tmp_name'], $target) && !rename($file['tmp_name'], $target)) throw new RuntimeException('Falha ao salvar o arquivo.');
        if (str_starts_with($mime, 'image/')) [$w, $h] = @getimagesize($target) ?: [null, null];
    }
    $rel = $relDir . '/' . $name;
    try {
        $pdo->prepare('INSERT INTO midias (pasta_id,nome,arquivo,mime,tamanho,largura,altura,alt,usuario_id) VALUES (?,?,?,?,?,?,?,?,?)')->execute([
            $folderId ?: null, mb_substr((string)($file['name'] ?? $name), 0, 190), $rel, $mime, (int)filesize($target), $w, $h,
            '', current_user()['id'] ?? null,
        ]);
        audit('midia_enviada', 'midia', (int)$pdo->lastInsertId(), ['arquivo' => $rel]);
    } catch (Throwable $e) { /* biblioteca indisponível: o arquivo continua salvo */ }
    return $rel;
}

/** Mantida para o código existente. */
function upload_image_file(array $file, string $prefix, int $maxWidth = 1600, int $maxHeight = 1600, int $maxBytes = 5242880): ?string
{
    return media_store_upload($file, $prefix, $maxWidth, $maxHeight);
}

function media_fix_orientation($img, string $path, string $mime)
{
    if ($mime !== 'image/jpeg' || !function_exists('exif_read_data')) return $img;
    $exif = @exif_read_data($path);
    $o = (int)($exif['Orientation'] ?? 1);
    return match ($o) { 3 => imagerotate($img, 180, 0), 6 => imagerotate($img, -90, 0), 8 => imagerotate($img, 90, 0), default => $img };
}

/** Salva redimensionando para caber em $maxW×$maxH. Retorna [largura, altura]. */
function media_save_resized($src, string $target, string $ext, int $maxW, int $maxH): array
{
    $w = imagesx($src); $h = imagesy($src);
    $scale = min(1, $maxW / $w, $maxH / $h);
    $nw = max(1, (int)round($w * $scale)); $nh = max(1, (int)round($h * $scale));
    $dst = imagecreatetruecolor($nw, $nh);
    if ($ext !== 'jpg') {
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
    } else {
        imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
    }
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
    $ok = match ($ext) { 'jpg' => imagejpeg($dst, $target, 86), 'png' => imagepng($dst, $target, 7), 'webp' => imagewebp($dst, $target, 86), default => false };
    imagedestroy($dst);
    if (!$ok) throw new RuntimeException('Falha ao processar a imagem.');
    return [$nw, $nh];
}

/** Corta e/ou redimensiona uma imagem da biblioteca. $asCopy=true cria um novo arquivo. */
function media_edit_image(int $id, array $crop, ?int $width, bool $asCopy): array
{
    global $pdo;
    $m = db_one($pdo, 'SELECT * FROM midias WHERE id=?', [$id]);
    if (!$m || !str_starts_with((string)$m['mime'], 'image/') || $m['mime'] === 'image/gif') return [false, 'Somente imagens JPG, PNG ou WebP podem ser editadas.'];
    $abs = BN_ROOT . '/' . $m['arquivo'];
    $src = @imagecreatefromstring((string)@file_get_contents($abs));
    if (!$src) return [false, 'Arquivo de imagem não encontrado.'];
    $W = imagesx($src); $H = imagesy($src);
    if (!empty($crop['w']) && !empty($crop['h'])) {
        $x = max(0, min($W - 1, (int)$crop['x'])); $y = max(0, min($H - 1, (int)$crop['y']));
        $cw = max(1, min($W - $x, (int)$crop['w'])); $ch = max(1, min($H - $y, (int)$crop['h']));
        $c = imagecreatetruecolor($cw, $ch);
        imagealphablending($c, false); imagesavealpha($c, true);
        imagecopy($c, $src, 0, 0, $x, $y, $cw, $ch);
        imagedestroy($src);
        $src = $c;
    }
    $ext = media_allowed()[$m['mime']] ?? 'jpg';
    $target = $abs;
    $rel = $m['arquivo'];
    if ($asCopy) {
        $rel = preg_replace('/(\.\w+)$/', '-editada-' . bin2hex(random_bytes(2)) . '$1', $m['arquivo']);
        $target = BN_ROOT . '/' . $rel;
    }
    $maxW = $width ? max(16, min(4000, $width)) : imagesx($src);
    [$nw, $nh] = media_save_resized($src, $target, $ext, $maxW, 10000);
    imagedestroy($src);
    clearstatcache();
    if ($asCopy) {
        $pdo->prepare('INSERT INTO midias (pasta_id,nome,arquivo,mime,tamanho,largura,altura,alt,usuario_id) VALUES (?,?,?,?,?,?,?,?,?)')->execute([
            $m['pasta_id'], 'Cópia editada — ' . $m['nome'], $rel, $m['mime'], (int)filesize($target), $nw, $nh, $m['alt'], current_user()['id'] ?? null]);
        $newId = (int)$pdo->lastInsertId();
    } else {
        $pdo->prepare('UPDATE midias SET largura=?,altura=?,tamanho=? WHERE id=?')->execute([$nw, $nh, (int)filesize($target), $id]);
        $newId = $id;
    }
    audit('midia_editada', 'midia', $newId, ['arquivo' => $rel, 'corte' => $crop, 'largura' => $width, 'copia' => $asCopy]);
    return [true, $asCopy ? 'Cópia editada criada.' : 'Imagem atualizada.', $newId];
}

/** Lista onde um arquivo está sendo usado (conteúdos, banners, configurações). */
function media_usage(string $path): array
{
    global $pdo;
    $like = '%' . addcslashes($path, '%_\\') . '%';
    $checks = [
        ['anuncios', 'titulo', ['imagem_destaque', 'logo', 'og_imagem', 'descricao'], 'Anúncio', 'gerenciar_anuncios.php?edit='],
        ['noticias', 'titulo', ['imagem', 'og_imagem', 'conteudo'], 'Notícia', 'gerenciar_noticias.php?edit='],
        ['paginas', 'titulo', ['imagem', 'og_imagem', 'conteudo'], 'Página', 'configuracoes.php?edit='],
        ['banners', 'titulo', ['imagem_fundo'], 'Banner', 'gerenciar_banners.php?edit='],
        ['home_promocao', 'titulo', ['imagem'], 'Promoção da home', 'promocao.php?'],
        ['configuracoes', 'chave', ['valor'], 'Configuração', 'configuracoes_gerais.php?'],
    ];
    $out = [];
    foreach ($checks as [$table, $titleCol, $cols, $label, $link]) {
        $where = implode(' OR ', array_map(fn($c) => "`$c` LIKE ?", $cols));
        foreach (db_rows($pdo, "SELECT id, `$titleCol` t FROM `$table` WHERE $where LIMIT 20", array_fill(0, count($cols), $like)) as $r) {
            $out[] = ['tipo' => $label, 'titulo' => $r['t'], 'link' => $link . ($table === 'configuracoes' || $table === 'home_promocao' ? '' : $r['id'])];
        }
    }
    return $out;
}

function media_folders(): array
{
    global $pdo;
    return db_rows($pdo, 'SELECT f.*, (SELECT COUNT(*) FROM midias m WHERE m.pasta_id=f.id) total FROM midia_pastas f ORDER BY f.nome');
}

function format_bytes(int $b): string
{
    if ($b >= 1048576) return number_format($b / 1048576, 1, ',', '.') . ' MB';
    return max(1, (int)round($b / 1024)) . ' KB';
}

/** URL pública de um caminho salvo (aceita caminhos relativos e URLs absolutas). */
function media_url(string $path, string $prefix = ''): string
{
    if ($path === '' || preg_match('#^(https?:)?//#', $path)) return $path;
    return $prefix . ltrim($path, '/');
}

/** Campo de formulário com upload + "Escolher da biblioteca" (painel). */
function media_field(string $name, ?string $value, string $label, string $help = ''): string
{
    $v = (string)$value;
    $preview = $v !== '' ? '<img src="' . e(media_url($v, preg_match('#^https?://#', $v) ? '' : '../')) . '" alt="">' : '<span>Sem imagem</span>';
    return '<div class="media-field" data-media-field>'
        . '<span class="media-field-label">' . e($label) . '</span>'
        . '<div class="media-field-row"><div class="media-field-preview" data-media-preview>' . $preview . '</div>'
        . '<div class="media-field-actions"><input type="hidden" name="' . e($name) . '" value="' . e($v) . '" data-media-input>'
        . (can('ler:midia') ? '<button type="button" class="btn btn-sm btn-outline-secondary" data-media-pick>Escolher da biblioteca</button> ' : '')
        . (can('enviar:midia') ? '<label class="btn btn-sm btn-outline-secondary mb-0">Enviar nova<input type="file" name="' . e($name) . '_upload" accept="image/jpeg,image/png,image/webp,image/gif" hidden data-media-upload></label> ' : '')
        . '<button type="button" class="btn btn-sm btn-link text-danger" data-media-clear' . ($v === '' ? ' hidden' : '') . '>Remover</button>'
        . ($help ? '<small class="muted d-block">' . e($help) . '</small>' : '') . '</div></div></div>';
}

/** Lê o valor de um media_field() no POST (upload novo tem prioridade). */
function media_field_value(string $name, string $prefix, int $maxW = 2000, int $maxH = 2000): string
{
    $up = media_store_upload($_FILES[$name . '_upload'] ?? [], $prefix, $maxW, $maxH);
    if ($up) return $up;
    $v = trim((string)($_POST[$name] ?? ''));
    if ($v !== '' && !preg_match('#^(https?://|media/|assets/)#', $v)) return '';
    return $v;
}
