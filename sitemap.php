<?php
/* sitemap.xml (e feed RSS com ?rss=1) gerados automaticamente a partir do conteúdo publicado e indexável. */
require_once __DIR__ . '/config/database.php';
$base = site_base_url();
if (isset($_GET['rss'])) {
    header('Content-Type: application/rss+xml; charset=utf-8');
    $site = setting($pdo, 'site_name', 'Site');
    echo '<?xml version="1.0" encoding="UTF-8"?><rss version="2.0"><channel><title>' . e($site) . '</title><link>' . e($base) . '</link><description>' . e(setting($pdo, 'seo_descricao', $site)) . '</description>';
    foreach (get_news($pdo, 20) as $n) {
        echo '<item><title>' . e($n['titulo']) . '</title><link>' . e($base . url_noticia($n['slug'])) . '</link><guid>' . e($base . url_noticia($n['slug'])) . '</guid><pubDate>' . date('r', strtotime($n['publicado_em'])) . '</pubDate><description>' . e((string)$n['resumo']) . '</description></item>';
    }
    echo '</channel></rss>';
    exit;
}
header('Content-Type: application/xml; charset=utf-8');
$urls = [['loc' => $base, 'mod' => null, 'pri' => '1.0'], ['loc' => $base . 'noticias.php', 'mod' => null, 'pri' => '0.7'], ['loc' => $base . 'categoria.php', 'mod' => null, 'pri' => '0.6']];
if (setting($pdo, 'seo_indexar_site', '1') === '1') {
    foreach (db_rows($pdo, "SELECT slug, criado_em FROM paginas WHERE status=1 AND indexar=1") as $p) $urls[] = ['loc' => $base . url_pagina($p['slug']), 'mod' => $p['criado_em'], 'pri' => '0.6'];
    foreach (db_rows($pdo, "SELECT slug, publicado_em FROM noticias WHERE status=1 AND indexar=1 AND publicado_em<=NOW()") as $n) $urls[] = ['loc' => $base . url_noticia($n['slug']), 'mod' => $n['publicado_em'], 'pri' => '0.7'];
    foreach (db_rows($pdo, "SELECT id, slug, data_criacao FROM anuncios WHERE status='ativo' AND indexar=1") as $a) $urls[] = ['loc' => $base . ad_profile_url($a), 'mod' => $a['data_criacao'], 'pri' => '0.8'];
    foreach (db_rows($pdo, 'SELECT id FROM categorias') as $c) $urls[] = ['loc' => $base . url_categoria((int)$c['id']), 'mod' => null, 'pri' => '0.5'];
}
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as $u) {
    echo '  <url><loc>' . e($u['loc']) . '</loc>' . ($u['mod'] ? '<lastmod>' . date('Y-m-d', strtotime($u['mod'])) . '</lastmod>' : '') . '<priority>' . $u['pri'] . "</priority></url>\n";
}
echo '</urlset>';
