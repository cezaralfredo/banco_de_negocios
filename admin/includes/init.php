<?php
/* Inicialização do painel (sem saída HTML): login, menu e permissão da página.
   Páginas que processam formulários incluem este arquivo ANTES da lógica e o
   header.php depois, permitindo redirecionar com mensagens (padrão PRG). */
require_once __DIR__ . '/../../config/database.php';
require_admin();
$current_admin = basename($_SERVER['PHP_SELF']);
$me = current_user();

/* Menu do painel: arquivo => [ícone, rótulo, permissão necessária para ver a página] */
$adminNav = [
    'Conteúdo' => [
        'dashboard.php' => ['▦', 'Dashboard', 'acessar:painel'],
        'gerenciar_anuncios.php' => ['▣', 'Anúncios', 'ler:anuncios'],
        'gerenciar_categorias.php' => ['◉', 'Categorias', 'ler:categorias'],
        'gerenciar_noticias.php' => ['✎', 'Notícias', 'ler:noticias'],
        'configuracoes.php' => ['▤', 'Páginas', 'ler:paginas'],
        'midia.php' => ['🖼', 'Biblioteca de mídia', 'ler:midia'],
        'gerenciar_banners.php' => ['▰', 'Banners e slider', 'ler:banners'],
        'beneficios.php' => ['★', 'Textos do banner', 'ler:banners'],
        'promocao.php' => ['▥', 'Promoção da home', 'ler:promocao'],
    ],
    'Aparência' => [
        'temas.php' => ['◒', 'Temas e layout', 'gerenciar:temas'],
        'menu.php' => ['☷', 'Menu principal', 'ler:menu'],
        'rodape.php' => ['▁', 'Rodapé e redes sociais', 'ler:rodape'],
        'idiomas.php' => ['🌐', 'Idiomas e traduções', 'ler:idiomas'],
    ],
    'Sistema' => [
        'configuracoes_gerais.php' => ['⚙', 'Configurações globais', 'gerenciar:configuracoes'],
        'gerenciar_usuarios.php' => ['♟', 'Usuários', 'gerenciar:usuarios'],
        'papeis.php' => ['🔐', 'Papéis e permissões', 'gerenciar:papeis'],
        'manutencao.php' => ['🔧', 'Modo de manutenção', 'gerenciar:manutencao'],
        'backup.php' => ['💾', 'Backup', 'gerenciar:backup'],
        'auditoria.php' => ['📋', 'Logs de auditoria', 'ler:auditoria'],
        'lixeira.php' => ['🗑', 'Lixeira', 'ler:lixeira'],
        'plugins.php' => ['🧩', 'Plugins', 'gerenciar:plugins'],
        'clientes.php' => ['🏢', 'Clientes', 'gerenciar:clientes'],
    ],
];
foreach (plugin_admin_pages() as $pp) $adminNav['Plugins']['plugin.php?p=' . $pp['slug']] = [$pp['icon'], $pp['title'], $pp['perm']];
$adminNav['Ajuda'] = ['ajuda.php' => ['?', 'Guia do administrador', 'acessar:painel'], 'minha_conta.php' => ['◔', 'Minha conta', 'acessar:painel']];
$adminNav = apply_filters('admin_menu', $adminNav);

// Permissão exigida pela página atual (definida na página com $admin_perm ou no menu acima)
$navKey = $current_admin === 'plugin.php' ? 'plugin.php?p=' . ($_GET['p'] ?? '') : $current_admin;
if (!isset($admin_perm)) {
    $admin_perm = 'acessar:painel';
    foreach ($adminNav as $links) if (isset($links[$navKey])) $admin_perm = $links[$navKey][2];
}
$forbidden = !can($admin_perm);
if ($forbidden) {
    http_response_code(403);
    audit('acesso_negado', 'pagina', null, ['pagina' => $navKey, 'permissao' => $admin_perm]);
}
if ($forbidden && empty($GLOBALS['__bn_admin_header_loading'])) {
    $GLOBALS['__bn_admin_header_loading'] = true;
    include __DIR__ . '/header.php';
    exit;
}
// Backup automático (no máximo 1x por período), executado após enviar a página
register_shutdown_function(function () {
    if (function_exists('fastcgi_finish_request')) @fastcgi_finish_request();
    try { backup_maybe_auto(); trash_expire(); } catch (Throwable $e) { error_log('[CMS] ' . $e->getMessage()); }
});
