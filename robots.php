<?php
/* robots.txt dinâmico (com .htaccess, /robots.txt aponta para este arquivo). */
require_once __DIR__ . '/config/database.php';
header('Content-Type: text/plain; charset=utf-8');
if (setting($pdo, 'seo_indexar_site', '1') !== '1' || maintenance_active()) {
    echo "User-agent: *\nDisallow: /\n";
    exit;
}
echo "User-agent: *\nDisallow: /admin/\nDisallow: /storage/\nDisallow: /config/\nDisallow: /core/\nAllow: /\n\nSitemap: " . site_base_url() . "sitemap.php\n";
echo setting($pdo, 'seo_robots_extra', '');
