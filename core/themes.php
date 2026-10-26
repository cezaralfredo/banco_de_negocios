<?php
declare(strict_types=1);

/* ==========================================================================
   Temas e layout
   Cada tema fica em themes/<slug>/ com:
   - theme.json: nome, descrição, prévia e valores padrão das variáveis
   - theme.css : estilos próprios (opcional) usando as variáveis CSS
   As variáveis (cores, tipografia, cantos, botões, largura) são centralizadas
   e podem ser personalizadas por cliente em Admin › Temas e layout.
   ========================================================================== */

/** Definição das variáveis editáveis (comuns a todos os temas). */
function theme_var_schema(): array
{
    return [
        'cor_primaria' => ['Cor principal', 'color', '--wine'],
        'cor_primaria_escura' => ['Cor principal escura (barra superior)', 'color', '--wine-dark'],
        'cor_destaque' => ['Cor de destaque', 'color', '--pink'],
        'cor_titulos' => ['Cor dos títulos', 'color', '--navy'],
        'cor_texto' => ['Cor do texto', 'color', '--ink'],
        'cor_fundo' => ['Fundo da página', 'color', '--page-bg'],
        'cor_superficie' => ['Fundo de cards e caixas', 'color', '--surface'],
        'cor_rodape' => ['Fundo do rodapé', 'color', '--footer-bg'],
        'hero_inicio' => ['Banner: cor inicial do degradê', 'color', '--hero-from'],
        'hero_fim' => ['Banner: cor final do degradê', 'color', '--hero-to'],
        'fonte_texto' => ['Fonte do texto', 'font', '--site-font'],
        'fonte_titulos' => ['Fonte dos títulos', 'font', '--heading-font'],
        'raio' => ['Arredondamento de cards (px)', 'range', '--radius'],
        'botoes' => ['Estilo dos botões', 'select', '--btn-radius'],
        'largura' => ['Largura do conteúdo', 'select', '--container'],
    ];
}

function theme_fonts(): array
{
    return ['Hanken Grotesk', 'DM Sans', 'Inter', 'Plus Jakarta Sans', 'Poppins', 'Nunito', 'Montserrat', 'Lato', 'Merriweather', 'Playfair Display', 'Lora', 'Roboto', 'Arial'];
}

function themes_available(): array
{
    static $list = null;
    if ($list !== null) return $list;
    $list = [];
    foreach (glob(BN_ROOT . '/themes/*/theme.json') ?: [] as $f) {
        $j = json_decode((string)file_get_contents($f), true);
        if (!is_array($j)) continue;
        $slug = basename(dirname($f));
        $list[$slug] = $j + ['slug' => $slug, 'nome' => $slug, 'descricao' => '', 'variaveis' => []];
    }
    uasort($list, fn($a, $b) => ($a['ordem'] ?? 99) <=> ($b['ordem'] ?? 99));
    return $list;
}

function active_theme(): string
{
    global $pdo;
    $t = setting($pdo, 'tema', 'classico');
    if (!empty($_GET['preview_tema']) && function_exists('can') && can('gerenciar:temas')) $t = (string)$_GET['preview_tema'];
    return isset(themes_available()[$t]) ? $t : (array_key_first(themes_available()) ?? 'classico');
}

/** Variáveis do tema ativo: padrão do tema + personalizações do cliente. */
function theme_vars(?string $slug = null): array
{
    global $pdo;
    $slug = $slug ?? active_theme();
    $defaults = (array)(themes_available()[$slug]['variaveis'] ?? []);
    $custom = json_decode(setting($pdo, 'tema_vars_' . $slug, '{}'), true) ?: [];
    return array_merge($defaults, array_intersect_key($custom, theme_var_schema()));
}

function theme_var(string $key, string $default = ''): string
{
    return (string)(theme_vars()[$key] ?? $default);
}

/** CSS com as variáveis centralizadas (impresso no <head>). */
function theme_css_vars(): string
{
    $v = theme_vars();
    $css = [];
    foreach (theme_var_schema() as $key => [$label, $type, $cssVar]) {
        if (!isset($v[$key]) || $v[$key] === '') continue;
        $val = (string)$v[$key];
        if ($type === 'color' && !preg_match('/^#[0-9a-f]{3,8}$/i', $val)) continue;
        if ($type === 'font') $val = "'" . str_replace("'", '', $val) . "', Arial, sans-serif";
        if ($key === 'raio') $val = (int)$val . 'px';
        if ($key === 'botoes') $val = ['pilula' => '999px', 'arredondado' => '10px', 'reto' => '3px'][$val] ?? '999px';
        if ($key === 'largura') $val = ['larga' => '1180px', 'compacta' => '1040px', 'extra' => '1320px'][$val] ?? '1180px';
        $css[] = $cssVar . ':' . $val;
    }
    return ':root{' . implode(';', $css) . '}';
}

/** Link do Google Fonts com as fontes usadas pelo tema. */
function theme_fonts_link(): string
{
    $v = theme_vars();
    $fonts = array_unique(array_filter([$v['fonte_texto'] ?? 'Hanken Grotesk', $v['fonte_titulos'] ?? 'Poppins']));
    $fam = [];
    foreach ($fonts as $f) {
        if ($f === 'Arial') continue;
        // pesos disponíveis em cada fonte (pedir um peso inexistente faz o Google Fonts recusar a folha inteira)
        $w = ['Lato' => '400;700', 'Playfair Display' => '400;500;600;700', 'Lora' => '400;500;600;700', 'Merriweather' => '400;700'][$f] ?? '400;500;600;700';
        $fam[] = 'family=' . str_replace(' ', '+', $f) . ':wght@' . $w;
    }
    return $fam ? 'https://fonts.googleapis.com/css2?' . implode('&', $fam) . '&display=swap' : '';
}

/** Importa as cores antigas de Admin › Aparência para o tema clássico (uma vez). */
function theme_migrate_legacy(PDO $pdo): void
{
    if (setting($pdo, 'tema_vars_classico', '') !== '') return;
    $map = ['theme_primary' => 'cor_primaria', 'theme_dark' => 'cor_primaria_escura', 'theme_accent' => 'cor_destaque', 'theme_font' => 'fonte_texto'];
    $vars = [];
    foreach ($map as $old => $new) if (($val = setting($pdo, $old, '')) !== '') $vars[$new] = $val;
    if (setting($pdo, 'theme_layout', '') === 'boxed') $vars['largura'] = 'compacta';
    if ($vars) save_setting($pdo, 'tema_vars_classico', json_encode($vars));
}
