<?php
/* Plugin de exemplo: botão flutuante de WhatsApp.
   Mostra como usar um hook do site (footer) e registrar uma página no painel. */

plugin_admin_page('whatsapp-flutuante', 'WhatsApp flutuante', 'gerenciar:configuracoes', __DIR__ . '/admin.php', '💬');

add_action('footer', function () {
    global $pdo;
    $num = preg_replace('/\D+/', '', setting($pdo, 'plugin_whatsapp_numero', '') ?: setting($pdo, 'footer_phone', ''));
    if ($num === '') return;
    $msg = rawurlencode(setting($pdo, 'plugin_whatsapp_mensagem', 'Olá! Vim pelo site.'));
    $side = setting($pdo, 'plugin_whatsapp_lado', 'direita') === 'esquerda' ? 'left' : 'right';
    $label = e(setting($pdo, 'plugin_whatsapp_rotulo', 'Fale conosco'));
    echo '<a href="https://wa.me/' . e($num) . '?text=' . $msg . '" target="_blank" rel="noopener" class="wa-float" style="position:fixed;bottom:22px;' . $side . ':22px;z-index:1040;display:flex;align-items:center;gap:8px;background:#25d366;color:#fff;border-radius:40px;padding:12px 18px 12px 14px;font:700 14px Arial,sans-serif;text-decoration:none;box-shadow:0 8px 24px rgba(0,0,0,.25)" aria-label="' . $label . ' pelo WhatsApp">'
        . svg_icon('whatsapp', 'icon') . '<span>' . $label . '</span></a>'
        . '<style>.wa-float svg{width:24px;height:24px}body.has-cookie-banner .wa-float{bottom:140px!important}@media(max-width:600px){.wa-float span{display:none}.wa-float{padding:14px!important}}</style>';
});
