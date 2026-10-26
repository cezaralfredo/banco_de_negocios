<?php
/* Plugin de exemplo: faixa de aviso no topo (hook body_start) com página de configuração e permissão própria. */

add_filter('permissions', function (array $perms) {
    $perms['gerenciar:aviso-topo'] = ['Plugins', 'Configurar a barra de aviso do site'];
    return $perms;
});
plugin_admin_page('aviso-topo', 'Barra de aviso', 'gerenciar:aviso-topo', __DIR__ . '/admin.php', '📣');

add_action('body_start', function () {
    global $pdo;
    $text = trim(setting($pdo, 'plugin_aviso_texto', ''));
    if ($text === '') return;
    $now = time();
    $start = setting($pdo, 'plugin_aviso_inicio', ''); $end = setting($pdo, 'plugin_aviso_fim', '');
    if (($start && strtotime($start) > $now) || ($end && strtotime($end) < $now)) return;
    $link = setting($pdo, 'plugin_aviso_link', '');
    $bg = setting($pdo, 'plugin_aviso_cor', '#ffb703');
    $fg = setting($pdo, 'plugin_aviso_cor_texto', '#1d1d1d');
    echo '<div role="region" aria-label="Aviso" style="background:' . e($bg) . ';color:' . e($fg) . ';text-align:center;padding:9px 16px;font:600 14px var(--site-font,Arial)">'
        . e($text) . ($link ? ' <a href="' . e($link) . '" style="color:inherit;text-decoration:underline;margin-left:6px">' . e(setting($pdo, 'plugin_aviso_link_texto', 'Saiba mais')) . ' →</a>' : '') . '</div>';
});
