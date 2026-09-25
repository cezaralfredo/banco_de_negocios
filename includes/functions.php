<?php
declare(strict_types=1);

/* ==========================================================================
   Funções utilitárias básicas
   ========================================================================== */

function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function db_rows(?PDO $pdo, string $sql, array $params = []): array
{
    if (!$pdo) return [];
    try {
        $st = $pdo->prepare($sql);
        $st->execute($params);
        return $st->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

function db_one(?PDO $pdo, string $sql, array $params = []): ?array
{
    $r = db_rows($pdo, $sql, $params);
    return $r[0] ?? null;
}

function slugify(string $text): string
{
    $text = trim($text);
    if (function_exists('iconv')) {
        $conv = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        if ($conv !== false) $text = $conv;
    }
    $text = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $text));
    return trim($text, '-') ?: 'item';
}

function initials(string $text): string
{
    return mb_strtoupper(mb_substr(trim($text), 0, 2));
}

/** Link canônico do perfil de um anúncio (o mesmo do botão "Ver perfil"). */
function ad_profile_url(array $ad): string
{
    return 'perfil.php?id=' . (int)($ad['id'] ?? 0);
}

/** Imagem principal do anúncio (capa > imagem legada > vazio). */
function ad_cover(array $ad): string
{
    return (string)($ad['imagem_destaque'] ?? $ad['imagem'] ?? '');
}

/* ==========================================================================
   Dados de demonstração (usados apenas quando o banco não está disponível)
   ========================================================================== */

function placeholder_data(): array
{
    return [
        'categories' => [
            ['id' => 1, 'nome' => 'Design', 'icone' => '✎'], ['id' => 2, 'nome' => 'Direito', 'icone' => '⚖'],
            ['id' => 3, 'nome' => 'Serviços', 'icone' => '⚙'], ['id' => 4, 'nome' => 'Tecnologia', 'icone' => '▣'],
            ['id' => 5, 'nome' => 'Marketing', 'icone' => '⚑'], ['id' => 6, 'nome' => 'Consultoria', 'icone' => '♙'],
            ['id' => 7, 'nome' => 'Finanças', 'icone' => '◉'], ['id' => 8, 'nome' => 'Educação', 'icone' => '▣'],
            ['id' => 9, 'nome' => 'Saúde', 'icone' => '♡'],
        ],
        'ads' => [
            ['id' => 1, 'titulo' => 'Anauê Design', 'categoria_id' => 3, 'categoria' => 'Serviços', 'tipo' => 'servico', 'descricao' => 'Design criativo e soluções web, dedicada a transformar ideias em experiências digitais memoráveis.', 'localizacao' => 'Fortaleza - CE', 'avaliacao' => '4.9', 'imagem' => 'https://images.unsplash.com/photo-1558655146-d09347e92766?auto=format&fit=crop&w=900&q=80', 'whatsapp' => '5585999999999'],
            ['id' => 2, 'titulo' => 'Projeto Brasil', 'categoria_id' => 3, 'categoria' => 'Serviços', 'tipo' => 'servico', 'descricao' => 'Compartilhando riquezas culturais, social e natural do nosso país com história e diversidade do Brasil.', 'localizacao' => 'Fortaleza - CE', 'avaliacao' => '4.9', 'imagem' => 'https://images.unsplash.com/photo-1521292270410-a8c4d716d518?auto=format&fit=crop&w=900&q=80', 'whatsapp' => '5585988888888'],
            ['id' => 3, 'titulo' => 'Cidadeneando', 'categoria_id' => 3, 'categoria' => 'Serviços', 'tipo' => 'servico', 'descricao' => 'Cidadanear é observar o bairro, a escola, o transporte, a saúde e a política com olhos críticos.', 'localizacao' => 'Fortaleza - CE', 'avaliacao' => '4.9', 'imagem' => 'https://images.unsplash.com/photo-1531206715517-5c0ba140b2b8?auto=format&fit=crop&w=900&q=80', 'whatsapp' => '5585977777777'],
            ['id' => 4, 'titulo' => 'Ernandes Oliveira', 'categoria_id' => 2, 'categoria' => 'Direito', 'tipo' => 'servico', 'descricao' => 'Atuando com técnica, ética e dedicação para garantir que direitos sejam respeitados.', 'localizacao' => 'Fortaleza - CE', 'avaliacao' => '4.9', 'imagem' => 'https://images.unsplash.com/photo-1589829545856-d10d557cf95f?auto=format&fit=crop&w=900&q=80', 'whatsapp' => '5585966666666'],
        ],
    ];
}

/* ==========================================================================
   Migração automática do schema (executada uma única vez por versão)
   ========================================================================== */

const SCHEMA_VERSION = 6;

function ensure_schema(?PDO $pdo): void
{
    static $done = false;
    if ($done || !$pdo) return;
    $done = true;

    $row = db_one($pdo, 'SELECT valor FROM configuracoes WHERE chave=?', ['schema_version']);
    if ((int)($row['valor'] ?? 0) >= SCHEMA_VERSION) return;

    $statements = [
        // prompt3/4/5 (idempotentes; falham silenciosamente se já existirem)
        'ALTER TABLE paginas ADD COLUMN parent_id INT NULL',
        'ALTER TABLE paginas ADD COLUMN imagem VARCHAR(255) NULL',
        'ALTER TABLE categorias ADD COLUMN destaque TINYINT(1) NOT NULL DEFAULT 0',
        'ALTER TABLE anuncios ADD COLUMN link VARCHAR(255) NULL',
        'ALTER TABLE anuncios ADD COLUMN logo VARCHAR(255) NULL',
        'ALTER TABLE banners ADD COLUMN duracao_segundos INT NOT NULL DEFAULT 5',
        "ALTER TABLE banners ADD COLUMN efeito VARCHAR(30) NOT NULL DEFAULT 'fade'",
        'ALTER TABLE banners ADD COLUMN ordem INT NOT NULL DEFAULT 0',
        'CREATE TABLE IF NOT EXISTS menu_itens (id INT AUTO_INCREMENT PRIMARY KEY, titulo VARCHAR(120) NOT NULL, url VARCHAR(255) NOT NULL, parent_id INT NULL, ordem INT NOT NULL DEFAULT 0, status TINYINT(1) NOT NULL DEFAULT 1, criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP)',
        "CREATE TABLE IF NOT EXISTS home_promocao (id TINYINT PRIMARY KEY, titulo VARCHAR(180) NOT NULL DEFAULT 'Amplie suas oportunidades!', texto TEXT, imagem VARCHAR(255) NULL, link VARCHAR(255) DEFAULT 'cadastro.php', ativo TINYINT(1) NOT NULL DEFAULT 1, atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP)",
        'CREATE TABLE IF NOT EXISTS anuncio_destaques (anuncio_id INT PRIMARY KEY, ordem INT NOT NULL DEFAULT 0, ativo TINYINT(1) NOT NULL DEFAULT 1)',
        'CREATE TABLE IF NOT EXISTS blog_posts (id INT AUTO_INCREMENT PRIMARY KEY, anuncio_id INT NOT NULL, titulo VARCHAR(180) NOT NULL, conteudo TEXT NOT NULL, status TINYINT(1) NOT NULL DEFAULT 1, criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP)',

        // prompt6
        "ALTER TABLE anuncios ADD COLUMN tipo VARCHAR(20) NOT NULL DEFAULT 'servico'",
        "ALTER TABLE home_promocao ADD COLUMN beneficios TEXT NULL",
        "ALTER TABLE home_promocao ADD COLUMN texto_botao VARCHAR(80) NOT NULL DEFAULT 'Criar Conta Agora'",
        "CREATE TABLE IF NOT EXISTS idiomas (id INT AUTO_INCREMENT PRIMARY KEY, codigo VARCHAR(10) NOT NULL UNIQUE, nome VARCHAR(60) NOT NULL, bandeira VARCHAR(16) NOT NULL DEFAULT '', padrao TINYINT(1) NOT NULL DEFAULT 0, ativo TINYINT(1) NOT NULL DEFAULT 1, ordem INT NOT NULL DEFAULT 0)",
        'CREATE TABLE IF NOT EXISTS traducoes (id INT AUTO_INCREMENT PRIMARY KEY, chave VARCHAR(190) NOT NULL, idioma VARCHAR(10) NOT NULL, valor TEXT NOT NULL, atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, UNIQUE KEY uk_traducao (chave, idioma))',
        "CREATE TABLE IF NOT EXISTS noticias (id INT AUTO_INCREMENT PRIMARY KEY, titulo VARCHAR(200) NOT NULL, slug VARCHAR(220) NOT NULL UNIQUE, resumo VARCHAR(400) NULL, conteudo LONGTEXT NOT NULL, imagem VARCHAR(255) NULL, autor VARCHAR(120) NULL, destaque TINYINT(1) NOT NULL DEFAULT 0, status TINYINT(1) NOT NULL DEFAULT 1, publicado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP)",
        "CREATE TABLE IF NOT EXISTS banner_beneficios (id INT AUTO_INCREMENT PRIMARY KEY, icone VARCHAR(40) NOT NULL DEFAULT 'megafone', titulo VARCHAR(120) NOT NULL, subtitulo VARCHAR(160) NULL, ordem INT NOT NULL DEFAULT 0, ativo TINYINT(1) NOT NULL DEFAULT 1)",
        "CREATE TABLE IF NOT EXISTS redes_sociais (id INT AUTO_INCREMENT PRIMARY KEY, rede VARCHAR(30) NOT NULL, nome VARCHAR(60) NOT NULL, url VARCHAR(255) NOT NULL, ordem INT NOT NULL DEFAULT 0, ativo TINYINT(1) NOT NULL DEFAULT 1)",
        "CREATE TABLE IF NOT EXISTS consentimentos_cookies (id INT AUTO_INCREMENT PRIMARY KEY, consent_id VARCHAR(40) NOT NULL, escolha VARCHAR(20) NOT NULL, categorias VARCHAR(120) NOT NULL, versao VARCHAR(10) NOT NULL, ip_hash CHAR(64) NULL, user_agent VARCHAR(255) NULL, criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP)",
    ];
    foreach ($statements as $q) {
        try { $pdo->exec($q); } catch (Throwable $e) { /* coluna/tabela já existe */ }
    }

    // Dados iniciais (só inserem se ainda não existirem)
    $seeds = [
        "INSERT IGNORE INTO idiomas (codigo,nome,bandeira,padrao,ativo,ordem) VALUES ('pt','Português','🇧🇷',1,1,1),('en','English','🇺🇸',0,1,2),('es','Español','🇪🇸',0,1,3)",
        "INSERT INTO banner_beneficios (icone,titulo,subtitulo,ordem,ativo) SELECT * FROM (SELECT 'megafone' a,'Visibilidade ao seu negócio' b,'e até novos clientes' c,1 d,1 e UNION ALL SELECT 'escudo','Ambiente seguro','e confiável',2,1 UNION ALL SELECT 'selo','Negócios verificados','e avaliados',3,1) x WHERE NOT EXISTS (SELECT 1 FROM banner_beneficios)",
        "INSERT INTO redes_sociais (rede,nome,url,ordem,ativo) SELECT * FROM (SELECT 'facebook' a,'Facebook' b,'https://facebook.com' c,1 d,1 e UNION ALL SELECT 'instagram','Instagram','https://instagram.com',2,1 UNION ALL SELECT 'linkedin','LinkedIn','https://linkedin.com',3,1 UNION ALL SELECT 'youtube','YouTube','https://youtube.com',4,1) x WHERE NOT EXISTS (SELECT 1 FROM redes_sociais)",
        "INSERT IGNORE INTO home_promocao (id,titulo,texto,imagem,link,ativo) VALUES (1,'Amplie suas oportunidades!','Cadastre seu negócio e seja encontrado por milhares de pessoas.','','cadastro.php',1)",
        "UPDATE home_promocao SET beneficios='Cadastre seu negócio em poucos minutos\nApareça para clientes da sua região\nGerencie seus anúncios facilmente\nConecte-se e faça mais negócios' WHERE id=1 AND (beneficios IS NULL OR beneficios='')",
        "UPDATE menu_itens SET url='noticias.php' WHERE url IN ('pagina.php?slug=noticias','#noticias')",
        "INSERT IGNORE INTO paginas (titulo,slug,conteudo,ordem,status) VALUES ('Sobre Nós','sobre-nos','<p>O Banco de Negócios conecta empreendedores, vendedores e prestadores de serviços a potenciais clientes em um só lugar.</p>',0,1)",
        "UPDATE menu_itens SET url='pagina.php?slug=sobre-nos' WHERE url='#sobre'",
        "UPDATE menu_itens SET url='index.php#servicos' WHERE url='#servicos'",
        // O hash do admin de demonstração no SQL original era inválido (a senha "password" não funcionava)
        "UPDATE usuarios SET senha='\$2y\$12\$ht/AiquN9Nbfdajt7adnkurTydlWlk4I9ujc4rbUnNv98otXTWt2G' WHERE email='admin@bancodenegocios.com.br' AND senha='\$2y\$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llCq6qM9e2JfT3J6l9M3K'",
        "INSERT INTO noticias (titulo,slug,resumo,conteudo,autor,destaque,status) SELECT 'Bem-vindo ao novo Banco de Negócios','bem-vindo-ao-novo-banco-de-negocios','Conheça as novidades da plataforma: busca aprimorada, acessibilidade e suporte a vários idiomas.','<p>A plataforma recebeu melhorias na busca, novos recursos de acessibilidade e suporte a vários idiomas.</p><p>Cadastre seu negócio e seja encontrado por milhares de pessoas.</p>','Equipe Banco de Negócios',1,1 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM noticias)",
    ];
    foreach ($seeds as $q) {
        try { $pdo->exec($q); } catch (Throwable $e) { }
    }
    // Garante que o item "Notícias" exista no menu
    try {
        if (!db_one($pdo, "SELECT id FROM menu_itens WHERE url='noticias.php'")) {
            $pdo->exec("INSERT INTO menu_itens (titulo,url,parent_id,ordem,status) VALUES ('Notícias','noticias.php',NULL,30,1)");
        }
    } catch (Throwable $e) { }

    save_setting($pdo, 'schema_version', (string)SCHEMA_VERSION);
}

/** Mantido por compatibilidade com o código anterior. */
function ensure_prompt5_schema(?PDO $pdo): void
{
    ensure_schema($pdo);
}

/* ==========================================================================
   Sessão, segurança e configurações
   ========================================================================== */

function start_session(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
}

function require_admin(): void
{
    global $pdo;
    start_session();
    $ok = !empty($_SESSION['admin_id']);
    if ($ok && $pdo) {
        $u = db_one($pdo, 'SELECT id,nivel_acesso,status FROM usuarios WHERE id=?', [(int)$_SESSION['admin_id']]);
        $ok = $u && $u['nivel_acesso'] === 'admin' && ($u['status'] ?? 'ativo') === 'ativo';
    }
    if (!$ok) {
        header('Location: index.php');
        exit;
    }
}

function require_super_admin(): void
{
    require_admin();
}

function csrf_token(): string
{
    start_session();
    if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}

function verify_csrf(?string $token): bool
{
    start_session();
    return is_string($token) && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function setting(?PDO $pdo, string $key, string $default = ''): string
{
    if ($pdo && !isset($GLOBALS['__bn_settings'])) {
        $GLOBALS['__bn_settings'] = [];
        foreach (db_rows($pdo, 'SELECT chave,valor FROM configuracoes') as $r) $GLOBALS['__bn_settings'][$r['chave']] = $r['valor'];
    }
    $cache = $GLOBALS['__bn_settings'] ?? [];
    return array_key_exists($key, $cache) ? (string)$cache[$key] : $default;
}

function save_setting(PDO $pdo, string $key, string $value): void
{
    $pdo->prepare('INSERT INTO configuracoes(chave,valor) VALUES(?,?) ON DUPLICATE KEY UPDATE valor=VALUES(valor)')->execute([$key, $value]);
    if (isset($GLOBALS['__bn_settings'])) $GLOBALS['__bn_settings'][$key] = $value;
}

/** Upload seguro de imagem, com redimensionamento opcional via GD. */
function upload_image_file(array $file, string $prefix, int $maxWidth = 1600, int $maxHeight = 1600, int $maxBytes = 5242880): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
    if (($file['error'] ?? 1) !== UPLOAD_ERR_OK || ($file['size'] ?? 0) > $maxBytes) {
        throw new RuntimeException('A imagem deve ter no máximo ' . round($maxBytes / 1048576) . ' MB.');
    }
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!isset($allowed[$mime])) throw new RuntimeException('Envie uma imagem JPG, PNG ou WebP válida.');
    $dir = __DIR__ . '/../assets/img/uploads';
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) throw new RuntimeException('Não foi possível preparar a pasta de imagens.');
    if (!is_writable($dir)) throw new RuntimeException('A pasta de imagens não tem permissão de escrita.');
    $ext = $allowed[$mime];
    $name = $prefix . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $target = $dir . '/' . $name;
    $source = extension_loaded('gd') ? @imagecreatefromstring((string)file_get_contents($file['tmp_name'])) : false;
    if ($source) {
        $w = imagesx($source);
        $h = imagesy($source);
        $scale = min(1, $maxWidth / $w, $maxHeight / $h);
        $canvas = imagecreatetruecolor(max(1, (int)($w * $scale)), max(1, (int)($h * $scale)));
        if ($ext !== 'jpg') {
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
            imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));
        } else {
            imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        }
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, imagesx($canvas), imagesy($canvas), $w, $h);
        $ok = $ext === 'jpg' ? imagejpeg($canvas, $target, 86) : ($ext === 'png' ? imagepng($canvas, $target, 7) : imagewebp($canvas, $target, 86));
        imagedestroy($source);
        imagedestroy($canvas);
        if (!$ok) throw new RuntimeException('Falha ao processar a imagem.');
    } elseif (!move_uploaded_file($file['tmp_name'], $target)) {
        throw new RuntimeException('Falha ao salvar a imagem.');
    }
    return 'assets/img/uploads/' . $name;
}

/* ==========================================================================
   Multilíngue
   ========================================================================== */

function get_languages(?PDO $pdo, bool $onlyActive = true): array
{
    static $cache = [];
    $k = $onlyActive ? 'a' : 't';
    if (isset($cache[$k])) return $cache[$k];
    $rows = db_rows($pdo, 'SELECT * FROM idiomas' . ($onlyActive ? ' WHERE ativo=1' : '') . ' ORDER BY ordem,id');
    if (!$rows) $rows = [['id' => 0, 'codigo' => 'pt', 'nome' => 'Português', 'bandeira' => '🇧🇷', 'padrao' => 1, 'ativo' => 1, 'ordem' => 1]];
    return $cache[$k] = $rows;
}

function default_language(?PDO $pdo): string
{
    foreach (get_languages($pdo) as $l) if ((int)$l['padrao'] === 1) return $l['codigo'];
    return get_languages($pdo)[0]['codigo'] ?? 'pt';
}

/** Idioma ativo: ?lang= > sessão > cookie > idioma padrão. */
function current_lang(): string
{
    static $lang = null;
    if ($lang !== null) return $lang;
    global $pdo;
    start_session();
    $codes = array_column(get_languages($pdo ?? null), 'codigo');
    $candidate = $_GET['lang'] ?? $_SESSION['lang'] ?? $_COOKIE['bn_lang'] ?? null;
    if (is_string($candidate) && in_array($candidate, $codes, true)) {
        $lang = $candidate;
        if (isset($_GET['lang'])) {
            $_SESSION['lang'] = $lang;
            if (!headers_sent()) setcookie('bn_lang', $lang, ['expires' => time() + 31536000, 'path' => '/', 'samesite' => 'Lax']);
        }
    } else {
        $lang = default_language($pdo ?? null);
    }
    return $lang;
}

function html_lang(): string
{
    $map = ['pt' => 'pt-BR', 'en' => 'en', 'es' => 'es'];
    $l = current_lang();
    return $map[$l] ?? $l;
}

function load_translations(?PDO $pdo, string $lang): array
{
    static $cache = [];
    if (!isset($cache[$lang])) {
        $cache[$lang] = [];
        foreach (db_rows($pdo, 'SELECT chave,valor FROM traducoes WHERE idioma=?', [$lang]) as $r) $cache[$lang][$r['chave']] = $r['valor'];
    }
    return $cache[$lang];
}

/** Traduz um texto fixo da interface. O texto-base (pt-BR) fica em includes/i18n.php. */
function t(string $key, array $vars = []): string
{
    global $pdo;
    static $base = null;
    if ($base === null) $base = require __DIR__ . '/i18n.php';
    $lang = current_lang();
    $tr = load_translations($pdo ?? null, $lang);
    $text = $tr[$key] ?? null;
    if ($text === null || $text === '') {
        $def = default_language($pdo ?? null);
        if ($def !== $lang) $text = load_translations($pdo ?? null, $def)[$key] ?? null;
    }
    if ($text === null || $text === '') $text = $base[$key] ?? $key;
    foreach ($vars as $k => $v) $text = str_replace('{' . $k . '}', (string)$v, $text);
    return $text;
}

/** Traduz um conteúdo cadastrado no painel (banner, benefício, footer…). */
function tc(string $key, ?string $original): string
{
    global $pdo;
    $lang = current_lang();
    $tr = load_translations($pdo ?? null, $lang);
    $val = $tr['conteudo.' . $key] ?? '';
    return $val !== '' ? $val : (string)$original;
}

/** URL da página atual trocando apenas o idioma. */
function lang_url(string $code): string
{
    $q = $_GET;
    $q['lang'] = $code;
    $path = strtok($_SERVER['REQUEST_URI'] ?? 'index.php', '?');
    return $path . '?' . http_build_query($q);
}

/* ==========================================================================
   Conteúdo do site
   ========================================================================== */

function get_categories(?PDO $pdo): array
{
    return db_rows($pdo, 'SELECT * FROM categorias ORDER BY nome') ?: placeholder_data()['categories'];
}

function get_featured_categories(?PDO $pdo): array
{
    $r = db_rows($pdo, 'SELECT * FROM categorias WHERE destaque=1 ORDER BY nome');
    return $r ?: array_slice(get_categories($pdo), 0, 9);
}

function get_ads(?PDO $pdo, int $limit = 8): array
{
    ensure_schema($pdo);
    $r = db_rows($pdo, 'SELECT a.*,c.nome categoria FROM anuncios a LEFT JOIN categorias c ON c.id=a.categoria_id WHERE a.status="ativo" ORDER BY a.avaliacao DESC,a.data_criacao DESC LIMIT ' . max(1, $limit));
    return $r ?: ($pdo ? [] : placeholder_data()['ads']);
}

function get_featured_ads(?PDO $pdo, int $limit = 4): array
{
    ensure_schema($pdo);
    return db_rows($pdo, 'SELECT a.*,c.nome categoria FROM anuncio_destaques d JOIN anuncios a ON a.id=d.anuncio_id LEFT JOIN categorias c ON c.id=a.categoria_id WHERE d.ativo=1 AND a.status="ativo" ORDER BY d.ordem,d.anuncio_id LIMIT ' . max(1, $limit));
}

function get_locations(?PDO $pdo): array
{
    $r = db_rows($pdo, 'SELECT DISTINCT localizacao FROM anuncios WHERE status="ativo" AND localizacao<>"" ORDER BY localizacao');
    return $r ?: ($pdo ? [] : [['localizacao' => 'Fortaleza - CE']]);
}

function ad_types(): array
{
    return ['produto' => t('search.type_product'), 'servico' => t('search.type_service')];
}

/** Normaliza os filtros de busca vindos da URL. */
function search_filters(array $src): array
{
    $letra = mb_strtoupper(mb_substr(trim((string)($src['letra'] ?? '')), 0, 1));
    $tipo = strtolower(trim((string)($src['tipo'] ?? '')));
    $ordem = (string)($src['ordem'] ?? 'relevancia');
    return [
        'q' => mb_substr(trim((string)($src['q'] ?? '')), 0, 100),
        'categoria' => (int)($src['categoria'] ?? $src['id'] ?? 0),
        'localizacao' => mb_substr(trim((string)($src['localizacao'] ?? '')), 0, 120),
        'tipo' => array_key_exists($tipo, ['produto' => 1, 'servico' => 1]) ? $tipo : '',
        'letra' => preg_match('/^[A-Z0-9]$/u', $letra) ? $letra : '',
        'ordem' => in_array($ordem, ['relevancia', 'avaliacao', 'recentes', 'az'], true) ? $ordem : 'relevancia',
    ];
}

function search_has_filters(array $f): bool
{
    return $f['q'] !== '' || $f['categoria'] || $f['localizacao'] !== '' || $f['tipo'] !== '' || $f['letra'] !== '';
}

/** Busca anúncios aplicando TODOS os filtros informados. */
function search_ads(?PDO $pdo, array $f): array
{
    ensure_schema($pdo);
    if (!$pdo) {
        // Filtro equivalente para os dados de demonstração
        $norm = fn($s) => mb_strtolower((string)$s);
        return array_values(array_filter(placeholder_data()['ads'], function ($ad) use ($f, $norm) {
            if ($f['q'] !== '' && !str_contains($norm($ad['titulo'] . ' ' . $ad['descricao'] . ' ' . $ad['categoria'] . ' ' . $ad['localizacao']), $norm($f['q']))) return false;
            if ($f['categoria'] && (int)$ad['categoria_id'] !== $f['categoria']) return false;
            if ($f['localizacao'] !== '' && $ad['localizacao'] !== $f['localizacao']) return false;
            if ($f['tipo'] !== '' && $ad['tipo'] !== $f['tipo']) return false;
            if ($f['letra'] !== '' && mb_strtoupper(mb_substr($ad['titulo'], 0, 1)) !== $f['letra']) return false;
            return true;
        }));
    }
    $sql = 'SELECT a.*,c.nome categoria FROM anuncios a LEFT JOIN categorias c ON c.id=a.categoria_id WHERE a.status="ativo"';
    $p = [];
    if ($f['q'] !== '') {
        $like = '%' . addcslashes($f['q'], '%_\\') . '%';
        $sql .= ' AND (a.titulo LIKE ? OR a.descricao LIKE ? OR c.nome LIKE ? OR a.localizacao LIKE ?)';
        array_push($p, $like, $like, $like, $like);
    }
    if ($f['categoria']) { $sql .= ' AND a.categoria_id=?'; $p[] = $f['categoria']; }
    if ($f['localizacao'] !== '') { $sql .= ' AND a.localizacao=?'; $p[] = $f['localizacao']; }
    if ($f['tipo'] !== '') { $sql .= ' AND a.tipo IN (?,"ambos")'; $p[] = $f['tipo']; }
    if ($f['letra'] !== '') { $sql .= ' AND TRIM(a.titulo) LIKE ?'; $p[] = $f['letra'] . '%'; }
    $order = [
        'relevancia' => $f['q'] !== '' ? '(a.titulo LIKE ' . $pdo->quote('%' . $f['q'] . '%') . ') DESC, a.avaliacao DESC, a.data_criacao DESC' : 'a.avaliacao DESC, a.data_criacao DESC',
        'avaliacao' => 'a.avaliacao DESC, a.titulo',
        'recentes' => 'a.data_criacao DESC',
        'az' => 'a.titulo ASC',
    ][$f['ordem']];
    return db_rows($pdo, $sql . ' ORDER BY ' . $order, $p);
}

function register_search_term(?PDO $pdo, string $term): void
{
    $term = mb_strtolower(trim($term));
    if (!$pdo || mb_strlen($term) < 3 || mb_strlen($term) > 60) return;
    try {
        $row = db_one($pdo, 'SELECT id FROM buscas_populares WHERE termo=?', [$term]);
        if ($row) $pdo->prepare('UPDATE buscas_populares SET contagem=contagem+1 WHERE id=?')->execute([$row['id']]);
        else $pdo->prepare('INSERT INTO buscas_populares(termo,contagem) VALUES(?,1)')->execute([$term]);
    } catch (Throwable $e) { }
}

function page_settings(?PDO $pdo): array
{
    return db_rows($pdo, 'SELECT * FROM paginas WHERE status=1 ORDER BY ordem,id');
}

function get_banners(?PDO $pdo): array
{
    ensure_schema($pdo);
    return db_rows($pdo, 'SELECT * FROM banners WHERE status=1 ORDER BY ordem,id DESC');
}

function get_benefits(?PDO $pdo, bool $onlyActive = true): array
{
    ensure_schema($pdo);
    $r = db_rows($pdo, 'SELECT * FROM banner_beneficios' . ($onlyActive ? ' WHERE ativo=1' : '') . ' ORDER BY ordem,id');
    if (!$r && !$pdo) {
        $r = [
            ['id' => 1, 'icone' => 'megafone', 'titulo' => 'Visibilidade ao seu negócio', 'subtitulo' => 'e até novos clientes'],
            ['id' => 2, 'icone' => 'escudo', 'titulo' => 'Ambiente seguro', 'subtitulo' => 'e confiável'],
            ['id' => 3, 'icone' => 'selo', 'titulo' => 'Negócios verificados', 'subtitulo' => 'e avaliados'],
        ];
    }
    return $r;
}

function get_social_links(?PDO $pdo, bool $onlyActive = true): array
{
    ensure_schema($pdo);
    return db_rows($pdo, 'SELECT * FROM redes_sociais' . ($onlyActive ? ' WHERE ativo=1' : '') . ' ORDER BY ordem,id');
}

function get_news(?PDO $pdo, int $limit = 20, int $offset = 0, int $exceptId = 0): array
{
    ensure_schema($pdo);
    return db_rows($pdo, 'SELECT * FROM noticias WHERE status=1 AND publicado_em<=NOW() AND id<>? ORDER BY destaque DESC, publicado_em DESC LIMIT ' . max(1, $limit) . ' OFFSET ' . max(0, $offset), [$exceptId]);
}

function get_menu_items(?PDO $pdo): array
{
    $items = db_rows($pdo, 'SELECT * FROM menu_itens WHERE status=1 ORDER BY ordem,id');
    foreach ($items as &$item) {
        $item['has_children'] = false;
        foreach ($items as $c) {
            if ((int)$c['parent_id'] === (int)$item['id']) { $item['has_children'] = true; break; }
        }
    }
    return $items;
}

function theme_settings(?PDO $pdo): array
{
    return [
        'primary' => setting($pdo, 'theme_primary', '#9f1549'),
        'dark' => setting($pdo, 'theme_dark', '#74152f'),
        'accent' => setting($pdo, 'theme_accent', '#ef5d98'),
        'font' => setting($pdo, 'theme_font', 'DM Sans'),
        'layout' => setting($pdo, 'theme_layout', 'wide'),
    ];
}

/** Verifica se um link do menu corresponde à página atual. */
function is_current_url(string $url): bool
{
    if ($url === '' || str_contains($url, '#') || preg_match('#^https?://#', $url)) return false;
    $script = basename($_SERVER['SCRIPT_NAME'] ?? '');
    $parts = parse_url($url);
    if (basename($parts['path'] ?? '') !== $script) return false;
    parse_str($parts['query'] ?? '', $q);
    foreach ($q as $k => $v) if (($_GET[$k] ?? null) !== $v) return false;
    return true;
}

/* ==========================================================================
   Ícones SVG (benefícios do banner, redes sociais, acessibilidade)
   ========================================================================== */

function benefit_icon_options(): array
{
    return [
        'megafone' => 'Megafone', 'escudo' => 'Escudo', 'selo' => 'Selo verificado', 'estrela' => 'Estrela',
        'pessoas' => 'Pessoas', 'foguete' => 'Foguete', 'coracao' => 'Coração', 'mapa' => 'Localização',
        'grafico' => 'Gráfico', 'aperto' => 'Aperto de mãos', 'relogio' => 'Relógio', 'cadeado' => 'Cadeado',
    ];
}

function social_network_options(): array
{
    return [
        'facebook' => 'Facebook', 'instagram' => 'Instagram', 'linkedin' => 'LinkedIn', 'youtube' => 'YouTube',
        'x' => 'X (Twitter)', 'tiktok' => 'TikTok', 'whatsapp' => 'WhatsApp', 'telegram' => 'Telegram',
        'pinterest' => 'Pinterest', 'site' => 'Site / outro',
    ];
}

function svg_icon(string $name, string $class = 'icon'): string
{
    $stroke = [
        'megafone' => '<path d="M3 11v2a1 1 0 0 0 1 1h2l5 4V6L6 10H4a1 1 0 0 0-1 1z"/><path d="M15.5 8.5a5 5 0 0 1 0 7"/><path d="M18.5 5.5a9 9 0 0 1 0 13"/>',
        'escudo' => '<path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z"/><path d="M9 12l2 2 4-4"/>',
        'selo' => '<path d="M12 2l2.4 2.1 3.2-.3.7 3.1 2.7 1.7-1.3 2.9 1.3 2.9-2.7 1.7-.7 3.1-3.2-.3L12 22l-2.4-2.1-3.2.3-.7-3.1-2.7-1.7L4.3 12 3 9.1l2.7-1.7.7-3.1 3.2.3z"/><path d="M8.5 12l2.3 2.3 4.7-4.6"/>',
        'estrela' => '<path d="M12 3l2.8 5.7 6.2.9-4.5 4.4 1.1 6.2L12 17.3 6.4 20.2l1.1-6.2L3 9.6l6.2-.9z"/>',
        'pessoas' => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0"/><path d="M16 4.6a3.5 3.5 0 0 1 0 6.8"/><path d="M18 14.3a6.5 6.5 0 0 1 3.5 5.7"/>',
        'foguete' => '<path d="M5 15c-1.5 1.3-2 5-2 5s3.7-.5 5-2"/><path d="M9 12a22 22 0 0 1 11-9 22 22 0 0 1-9 11l-2-2z"/><path d="M9 12H5l2-4h5"/><path d="M12 15v4l4-2v-5"/>',
        'coracao' => '<path d="M12 20s-8-4.6-8-10.2A4.3 4.3 0 0 1 12 7a4.3 4.3 0 0 1 8 2.8C20 15.4 12 20 12 20z"/>',
        'mapa' => '<path d="M12 21s-7-6.2-7-11.5a7 7 0 0 1 14 0C19 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/>',
        'grafico' => '<path d="M4 20V10"/><path d="M10 20V4"/><path d="M16 20v-7"/><path d="M22 20H2"/>',
        'aperto' => '<path d="M2 12l4-4 4 2 3-2 4 1 5 3"/><path d="M6 14l3 3 2-1 2 2 2-1 2 1 3-3"/><path d="M10 10l3 3"/>',
        'relogio' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'cadeado' => '<rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/>',
        'busca' => '<circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/>',
        'site' => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18"/><path d="M12 3a14 14 0 0 1 0 18 14 14 0 0 1 0-18z"/>',
        'email' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/>',
        'telefone' => '<path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2z"/>',
        'seta-esq' => '<path d="M15 18l-6-6 6-6"/>',
        'seta-dir' => '<path d="M9 18l6-6-6-6"/>',
        'contraste' => '<circle cx="12" cy="12" r="9"/><path d="M12 3v18" /><path d="M12 3a9 9 0 0 1 0 18z" fill="currentColor"/>',
        'link' => '<path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1"/><path d="M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1"/>',
        'audio' => '<path d="M4 10v4h3l5 4V6L7 10z"/><path d="M16 9a4 4 0 0 1 0 6"/><path d="M19 6a8 8 0 0 1 0 12"/>',
        'reset' => '<path d="M3 12a9 9 0 1 0 3-6.7"/><path d="M3 4v5h5"/>',
        'cookie' => '<path d="M12 3a9 9 0 1 0 9 9 4 4 0 0 1-5-5 4 4 0 0 1-4-4z"/><circle cx="8.5" cy="11" r="1" fill="currentColor"/><circle cx="12" cy="16" r="1" fill="currentColor"/><circle cx="15.5" cy="13" r="1" fill="currentColor"/>',
    ];
    $fill = [
        'acessibilidade' => '<circle cx="12" cy="4" r="2"/><path d="M20.5 7.3l-5.4 1.2v3.9l2.1 8.3a1.1 1.1 0 0 1-2.1.6L12.9 15h-1.8l-2.2 6.3a1.1 1.1 0 0 1-2.1-.6l2.1-8.3V8.5L3.5 7.3a1.1 1.1 0 0 1 .5-2.2c2.6.6 5.3 1 8 1s5.4-.4 8-1a1.1 1.1 0 0 1 .5 2.2z"/>',
        'facebook' => '<path d="M14 8.5V6.8c0-.8.5-1 1-1h2.5V2h-3.4C10.4 2 9.7 4.7 9.7 6.5v2H7.5V12h2.2v10H14V12h3l.5-3.5z"/>',
        'instagram' => '<path d="M12 7a5 5 0 1 0 0 10 5 5 0 0 0 0-10zm0 8.2a3.2 3.2 0 1 1 0-6.4 3.2 3.2 0 0 1 0 6.4zM17.3 5.5a1.2 1.2 0 1 0 0 2.4 1.2 1.2 0 0 0 0-2.4zM12 2c-2.7 0-3.1 0-4.1.1-4 .2-5.6 2.1-5.8 5.8C2 8.9 2 9.3 2 12s0 3.1.1 4.1c.2 3.7 1.9 5.6 5.8 5.8 1 .1 1.4.1 4.1.1s3.1 0 4.1-.1c3.7-.2 5.6-1.9 5.8-5.8.1-1 .1-1.4.1-4.1s0-3.1-.1-4.1c-.2-3.7-2-5.6-5.8-5.8C15.1 2 14.7 2 12 2zm0 1.8c2.7 0 3 0 4 .1 2.7.1 3.9 1.4 4 4 .1 1 .1 1.3.1 4s0 3-.1 4c-.1 2.6-1.3 3.9-4 4-1 .1-1.3.1-4 .1s-3 0-4-.1c-2.7-.1-3.9-1.4-4-4-.1-1-.1-1.3-.1-4s0-3 .1-4c.1-2.6 1.3-3.9 4-4 1-.1 1.3-.1 4-.1z"/>',
        'linkedin' => '<path d="M4.98 3.5a2.5 2.5 0 1 1 0 5 2.5 2.5 0 0 1 0-5zM3 9.5h4V21H3zM9.5 9.5h3.8v1.6h.1c.5-1 1.8-2 3.8-2 4 0 4.8 2.6 4.8 6V21h-4v-5.2c0-1.2 0-2.8-1.7-2.8s-2 1.3-2 2.7V21h-4z"/>',
        'youtube' => '<path d="M23 7.2a3 3 0 0 0-2.1-2.1C19 4.6 12 4.6 12 4.6s-7 0-8.9.5A3 3 0 0 0 1 7.2 31 31 0 0 0 .5 12a31 31 0 0 0 .5 4.8 3 3 0 0 0 2.1 2.1c1.9.5 8.9.5 8.9.5s7 0 8.9-.5a3 3 0 0 0 2.1-2.1 31 31 0 0 0 .5-4.8 31 31 0 0 0-.5-4.8zM9.7 15V9l5.8 3z"/>',
        'x' => '<path d="M17.8 3h3.1l-6.8 7.7L22 21h-6.2l-4.9-6.3L5.3 21H2.2l7.2-8.3L2 3h6.4l4.4 5.8zm-1.1 16.2h1.7L7.4 4.7H5.6z"/>',
        'tiktok' => '<path d="M16.6 2h-3.4v13.3a2.9 2.9 0 1 1-2-2.8V9a6.4 6.4 0 1 0 5.4 6.3V8.6a8 8 0 0 0 4.4 1.4V6.6a4.5 4.5 0 0 1-4.4-4.6z"/>',
        'whatsapp' => '<path d="M17.5 14.4c-.3-.1-1.8-.9-2-1-.3-.1-.5-.1-.7.1-.2.3-.8 1-.9 1.2-.2.2-.3.2-.6.1a8 8 0 0 1-2.4-1.5 9 9 0 0 1-1.6-2c-.2-.3 0-.5.1-.6l.5-.5.3-.5a.6.6 0 0 0 0-.5l-.9-2.2c-.2-.6-.5-.5-.7-.5h-.6a1.1 1.1 0 0 0-.8.4 3.4 3.4 0 0 0-1 2.5 5.9 5.9 0 0 0 1.2 3.1 13.5 13.5 0 0 0 5.2 4.6c1.9.8 2.7.9 3.6.7a3.1 3.1 0 0 0 2-1.4 2.5 2.5 0 0 0 .2-1.4c-.1-.1-.3-.2-.6-.3zM12 21.8a9.8 9.8 0 0 1-5-1.4l-.4-.2-3.7 1 1-3.6-.2-.4A9.8 9.8 0 1 1 12 21.8zM20.5 3.5A11.8 11.8 0 0 0 1.9 17.7L.2 24l6.4-1.7A11.8 11.8 0 0 0 24 12a11.7 11.7 0 0 0-3.5-8.5z"/>',
        'telegram' => '<path d="M21.9 4.3l-3.3 15.4c-.2 1.1-.9 1.4-1.8.9l-5-3.7-2.4 2.3c-.3.3-.5.5-1 .5l.4-5.1 9.2-8.3c.4-.4-.1-.6-.6-.2L6 13.3l-4.9-1.5c-1.1-.3-1.1-1.1.2-1.6L20.5 2.9c.9-.3 1.7.2 1.4 1.4z"/>',
        'pinterest' => '<path d="M12 2a10 10 0 0 0-3.6 19.3c-.1-.8-.2-2 0-2.9l1.2-5s-.3-.6-.3-1.5c0-1.4.8-2.5 1.8-2.5.9 0 1.3.6 1.3 1.4 0 .9-.6 2.2-.8 3.4-.2 1 .5 1.9 1.6 1.9 1.9 0 3.3-2 3.3-4.9 0-2.6-1.8-4.4-4.5-4.4a4.6 4.6 0 0 0-4.8 4.6c0 .9.4 1.9.8 2.4.1.1.1.2.1.3l-.3 1.2c0 .2-.2.3-.4.2-1.4-.7-2.2-2.7-2.2-4.3 0-3.5 2.5-6.7 7.3-6.7 3.8 0 6.8 2.7 6.8 6.4 0 3.8-2.4 6.9-5.8 6.9-1.1 0-2.2-.6-2.5-1.3l-.7 2.6c-.3 1-.9 2.2-1.4 2.9A10 10 0 1 0 12 2z"/>',
    ];
    if (isset($fill[$name])) {
        return '<svg class="' . e($class) . '" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false">' . $fill[$name] . '</svg>';
    }
    $path = $stroke[$name] ?? $stroke['site'];
    return '<svg class="' . e($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $path . '</svg>';
}

/** Limpa HTML cadastrado no painel: mantém apenas tags seguras e remove eventos/javascript:. */
function safe_html(string $html, string $allowed = '<p><br><strong><b><em><i><u><h2><h3><h4><ul><ol><li><a><img><blockquote><figure><figcaption><table><thead><tbody><tr><th><td><hr>'): string
{
    $html = strip_tags($html, $allowed);
    $html = preg_replace('/\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html);
    $html = preg_replace('/(href|src)\s*=\s*(["\']?)\s*(javascript|vbscript|data):[^"\'\s>]*\2/i', '$1="#"', $html);
    return preg_replace('/\s+style\s*=\s*("[^"]*"|\'[^\']*\')/i', '', $html);
}
