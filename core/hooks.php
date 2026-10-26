<?php
declare(strict_types=1);

/* ==========================================================================
   Hooks (ações e filtros) — permitem que módulos/plugins estendam o CMS
   sem alterar o núcleo.

   add_action('content_saved', function (string $tipo, int $id, array $dados) { ... });
   do_action('content_saved', 'noticias', 10, $row);

   add_filter('seo_meta', fn(array $meta) => $meta + ['author' => 'Fulano']);
   $meta = apply_filters('seo_meta', $meta);

   Hooks disponíveis no núcleo (documentados em admin/ajuda.php#plugins):
   - init, head, body_start, footer, admin_head, admin_footer
   - admin_menu (filtro), permissions (filtro), dashboard_widgets (ação)
   - content_saved, content_deleted, content_restored, workflow_changed
   - user_login, user_logout, maintenance_changed, mail_before_send (filtro)
   ========================================================================== */

$GLOBALS['__bn_hooks'] = $GLOBALS['__bn_hooks'] ?? ['action' => [], 'filter' => []];

function add_action(string $hook, callable $cb, int $priority = 10): void
{
    $GLOBALS['__bn_hooks']['action'][$hook][$priority][] = $cb;
}

function do_action(string $hook, ...$args): void
{
    $list = $GLOBALS['__bn_hooks']['action'][$hook] ?? [];
    ksort($list);
    foreach ($list as $cbs) {
        foreach ($cbs as $cb) {
            try {
                $cb(...$args);
            } catch (Throwable $e) {
                error_log("[CMS] Erro no hook {$hook}: " . $e->getMessage());
            }
        }
    }
}

function add_filter(string $hook, callable $cb, int $priority = 10): void
{
    $GLOBALS['__bn_hooks']['filter'][$hook][$priority][] = $cb;
}

function apply_filters(string $hook, $value, ...$args)
{
    $list = $GLOBALS['__bn_hooks']['filter'][$hook] ?? [];
    ksort($list);
    foreach ($list as $cbs) {
        foreach ($cbs as $cb) {
            try {
                $value = $cb($value, ...$args);
            } catch (Throwable $e) {
                error_log("[CMS] Erro no filtro {$hook}: " . $e->getMessage());
            }
        }
    }
    return $value;
}

/** Captura a saída de uma ação para inserir em templates. */
function render_action(string $hook, ...$args): string
{
    ob_start();
    do_action($hook, ...$args);
    return (string)ob_get_clean();
}
