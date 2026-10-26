<?php
declare(strict_types=1);

/* ==========================================================================
   Plugins: módulos opcionais que estendem o CMS sem alterar o núcleo.

   Estrutura:  plugins/<slug>/plugin.json  { "nome", "descricao", "versao", "autor" }
               plugins/<slug>/plugin.php   (carregado quando o plugin está ativo)

   Um plugin pode:
   - usar add_action()/add_filter() (ver core/hooks.php)
   - registrar páginas no painel:  plugin_admin_page('meu-plugin', 'Título', 'ler:painel', __DIR__.'/admin.php');
   - criar permissões próprias via filtro "permissions"
   - guardar configurações com setting()/save_setting() usando o prefixo "plugin_<slug>_"
   Os plugins ativos são escolhidos por cliente em Admin › Plugins.
   ========================================================================== */

function plugins_available(): array
{
    $list = [];
    foreach (glob(BN_ROOT . '/plugins/*/plugin.json') ?: [] as $f) {
        $j = json_decode((string)file_get_contents($f), true);
        if (!is_array($j)) continue;
        $slug = basename(dirname($f));
        if (!preg_match('/^[a-z0-9-]+$/', $slug)) continue;
        $list[$slug] = $j + ['slug' => $slug, 'nome' => $slug, 'descricao' => '', 'versao' => '1.0', 'autor' => ''];
    }
    ksort($list);
    return $list;
}

function plugins_enabled(): array
{
    global $pdo;
    $on = json_decode(setting($pdo, 'plugins_ativos', '[]'), true);
    return is_array($on) ? array_values(array_intersect($on, array_keys(plugins_available()))) : [];
}

function plugins_load(): void
{
    foreach (plugins_enabled() as $slug) {
        $file = BN_ROOT . "/plugins/$slug/plugin.php";
        if (!is_file($file)) continue;
        try {
            (static function () use ($file) { require_once $file; })();
        } catch (Throwable $e) {
            error_log("[CMS] Plugin $slug falhou ao carregar: " . $e->getMessage());
        }
    }
}

/** Registra uma página de plugin no menu do painel (acessada em admin/plugin.php?p=slug). */
function plugin_admin_page(string $slug, string $title, string $perm, string $file, string $icon = '🧩'): void
{
    $GLOBALS['__bn_plugin_pages'][$slug] = compact('slug', 'title', 'perm', 'file', 'icon');
}

function plugin_admin_pages(): array
{
    return $GLOBALS['__bn_plugin_pages'] ?? [];
}
