<?php
declare(strict_types=1);

/* ==========================================================================
   SEO, URLs amigáveis, Open Graph, sitemap e integrações de analytics
   ========================================================================== */

function pretty_urls(): bool
{
    global $pdo;
    return setting($pdo, 'urls_amigaveis', '0') === '1';
}

function url_noticia(string $slug): string { return pretty_urls() ? 'noticia/' . rawurlencode($slug) : 'noticia.php?slug=' . rawurlencode($slug); }
function url_pagina(string $slug): string { return pretty_urls() ? 'pagina/' . rawurlencode($slug) : 'pagina.php?slug=' . rawurlencode($slug); }
function url_categoria(int $id): string { return 'categoria.php?categoria=' . $id; }

/** URL do perfil do anúncio: usa o slug quando existir. */
function ad_profile_url(array $ad): string
{
    $slug = (string)($ad['slug'] ?? '');
    if ($slug !== '') return pretty_urls() ? 'anuncio/' . rawurlencode($slug) : 'perfil.php?slug=' . rawurlencode($slug);
    return 'perfil.php?id=' . (int)($ad['id'] ?? 0);
}

/** Garante um slug único na tabela. */
function unique_slug(string $table, string $slug, int $exceptId = 0): string
{
    global $pdo;
    $base = slugify($slug);
    $try = $base; $n = 2;
    while (db_one($pdo, "SELECT id FROM `$table` WHERE slug=? AND id<>?", [$try, $exceptId])) $try = $base . '-' . $n++;
    return $try;
}

/**
 * Monta as meta tags da página. $seo = [title, description, image, url, type, index].
 * Configurações padrão em Configurações globais › SEO.
 */
function seo_tags(array $seo): string
{
    global $pdo;
    $site = setting($pdo, 'site_name', 'Site');
    $pattern = setting($pdo, 'seo_titulo_padrao', '%titulo% | %site%');
    $rawTitle = trim((string)($seo['title'] ?? ''));
    $title = $rawTitle === '' || $rawTitle === $site ? $site : str_replace(['%titulo%', '%site%'], [$rawTitle, $site], $pattern);
    if (!empty($seo['title_exact'])) $title = $rawTitle;
    $desc = trim((string)($seo['description'] ?? '')) ?: setting($pdo, 'seo_descricao', '');
    $desc = mb_strimwidth(preg_replace('/\s+/', ' ', strip_tags($desc)), 0, 160, '…');
    $img = (string)($seo['image'] ?? '') ?: setting($pdo, 'seo_og_imagem', '') ?: setting($pdo, 'site_logo', '');
    $base = site_base_url();
    if ($img !== '' && !preg_match('#^https?://#', $img)) $img = $base . ltrim($img, '/');
    $url = (string)($seo['url'] ?? '') ?: ($base . ltrim(preg_replace('#^' . preg_quote(parse_url($base, PHP_URL_PATH) ?: '/', '#') . '#', '', strtok($_SERVER['REQUEST_URI'] ?? '', '?')), '/'));
    if (!preg_match('#^https?://#', $url)) $url = $base . ltrim($url, '/');
    $index = ($seo['index'] ?? true) && setting($pdo, 'seo_indexar_site', '1') === '1' && !maintenance_active();
    $meta = apply_filters('seo_meta', [
        'title' => $title, 'description' => $desc, 'image' => $img, 'url' => $url,
        'type' => $seo['type'] ?? 'website', 'robots' => $index ? 'index, follow' : 'noindex, nofollow', 'site' => $site,
    ]);
    $h = '<title>' . e($meta['title']) . "</title>\n";
    if ($meta['description'] !== '') $h .= '<meta name="description" content="' . e($meta['description']) . "\">\n";
    $h .= '<meta name="robots" content="' . e($meta['robots']) . "\">\n";
    $h .= '<link rel="canonical" href="' . e($meta['url']) . "\">\n";
    $h .= '<meta property="og:site_name" content="' . e($meta['site']) . "\">\n";
    $h .= '<meta property="og:type" content="' . e($meta['type']) . "\">\n";
    $h .= '<meta property="og:title" content="' . e($meta['title']) . "\">\n";
    if ($meta['description'] !== '') $h .= '<meta property="og:description" content="' . e($meta['description']) . "\">\n";
    $h .= '<meta property="og:url" content="' . e($meta['url']) . "\">\n";
    $h .= '<meta property="og:locale" content="' . e(str_replace('-', '_', html_lang())) . "\">\n";
    if ($meta['image'] !== '') $h .= '<meta property="og:image" content="' . e($meta['image']) . "\">\n<meta name=\"twitter:card\" content=\"summary_large_image\">\n";
    if ($v = setting($pdo, 'seo_google_verificacao', '')) $h .= '<meta name="google-site-verification" content="' . e($v) . "\">\n";
    return $h;
}

/** Campos de SEO reutilizados nos formulários do painel. */
function seo_fields(array $row, string $fallbackTitle = ''): string
{
    if (!can('editar:seo')) return '';
    $idx = (int)($row['indexar'] ?? 1);
    return '<details class="seo-box"' . (!empty($row['seo_titulo']) || !empty($row['seo_descricao']) ? ' open' : '') . '><summary>SEO e compartilhamento</summary>'
        . '<div class="seo-preview" data-seo-preview><span class="sp-title">' . e(($row['seo_titulo'] ?? '') ?: ($row['titulo'] ?? $fallbackTitle) ?: 'Título da página') . '</span><span class="sp-url">' . e(site_base_url()) . '…</span><span class="sp-desc">' . e(($row['seo_descricao'] ?? '') ?: 'A descrição aparece aqui nos resultados de busca.') . '</span></div>'
        . '<label>Título SEO <small class="muted">Até 60 caracteres. Vazio = título do conteúdo.</small><input name="seo_titulo" maxlength="70" value="' . e($row['seo_titulo'] ?? '') . '" data-seo-title data-count="60"></label>'
        . '<label>Meta description <small class="muted">Até 160 caracteres.</small><textarea name="seo_descricao" maxlength="200" style="min-height:70px" data-seo-desc data-count="160">' . e($row['seo_descricao'] ?? '') . '</textarea></label>'
        . media_field('og_imagem', $row['og_imagem'] ?? '', 'Imagem para redes sociais (Open Graph)', 'Recomendado 1200×630. Vazio = imagem principal do conteúdo.')
        . '<label class="check-row"><input type="checkbox" name="indexar" value="1"' . ($idx ? ' checked' : '') . '> Permitir que buscadores indexem esta página</label>'
        . '</details>';
}

/** Lê os campos de SEO do POST (mantém os valores antigos se o usuário não pode editar SEO). */
function seo_post(array $old): array
{
    if (!can('editar:seo')) return ['seo_titulo' => $old['seo_titulo'] ?? null, 'seo_descricao' => $old['seo_descricao'] ?? null, 'og_imagem' => $old['og_imagem'] ?? null, 'indexar' => (int)($old['indexar'] ?? 1)];
    return [
        'seo_titulo' => mb_substr(trim((string)($_POST['seo_titulo'] ?? '')), 0, 70) ?: null,
        'seo_descricao' => mb_substr(trim((string)($_POST['seo_descricao'] ?? '')), 0, 200) ?: null,
        'og_imagem' => media_field_value('og_imagem', 'og', 1200, 1200) ?: null,
        'indexar' => isset($_POST['indexar']) ? 1 : 0,
    ];
}

/** Scripts de analytics/marcação: carregados somente após o consentimento de cookies (LGPD). */
function analytics_snippet(): string
{
    global $pdo;
    $ga = preg_replace('/[^A-Z0-9-]/i', '', setting($pdo, 'analytics_ga4', ''));
    $gtm = preg_replace('/[^A-Z0-9-]/i', '', setting($pdo, 'analytics_gtm', ''));
    $matomoUrl = rtrim(setting($pdo, 'analytics_matomo_url', ''), '/');
    $matomoId = (int)setting($pdo, 'analytics_matomo_id', '0');
    $custom = setting($pdo, 'scripts_head', '');
    $customCat = setting($pdo, 'scripts_head_categoria', 'statistics');
    if (!$ga && !$gtm && !($matomoUrl && $matomoId) && $custom === '') return '';
    $cfg = json_encode(['ga4' => $ga, 'gtm' => $gtm, 'matomoUrl' => $matomoUrl, 'matomoId' => $matomoId, 'custom' => $custom, 'customCat' => $customCat], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG);
    return "<script>window.CMS_ANALYTICS=$cfg;</script>\n";
}
