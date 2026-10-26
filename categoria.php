<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
start_session();
ensure_schema($pdo);

$showCats = isset($_GET['todas']);
$searchCats = get_categories($pdo);
$searchLocations = get_locations($pdo);
$filters = search_filters($_GET);
$isResultsPage = true;
$ads = $showCats ? [] : search_ads($pdo, $filters);
if ($filters['q'] !== '' && $ads) register_search_term($pdo, $filters['q']);

$catName = '';
foreach ($searchCats as $c) if ((int)$c['id'] === $filters['categoria']) $catName = $c['nome'];

// "Chips" de filtros ativos, cada um com link para removê-lo
$chips = [];
$base = array_filter(['q' => $filters['q'], 'categoria' => $filters['categoria'] ?: '', 'localizacao' => $filters['localizacao'], 'tipo' => $filters['tipo'], 'letra' => $filters['letra'], 'ordem' => $filters['ordem'] !== 'relevancia' ? $filters['ordem'] : ''], fn($v) => $v !== '');
$without = function (string $key) use ($base): string {
    $q = $base;
    unset($q[$key]);
    return 'categoria.php' . ($q ? '?' . http_build_query($q) : '');
};
if ($filters['q'] !== '') $chips[] = [t('search.term') . ': “' . $filters['q'] . '”', $without('q')];
if ($catName !== '') $chips[] = [t('search.category') . ': ' . $catName, $without('categoria')];
if ($filters['localizacao'] !== '') $chips[] = [t('search.location') . ': ' . $filters['localizacao'], $without('localizacao')];
if ($filters['tipo'] !== '') $chips[] = [t('search.type') . ': ' . (ad_types()[$filters['tipo']] ?? $filters['tipo']), $without('tipo')];
if ($filters['letra'] !== '') $chips[] = [t('search.letter') . ': ' . $filters['letra'], $without('letra')];

$heading = $showCats ? t('list.all_categories') : ($catName ?: t('list.ads'));
$page_title = $heading . ' | ' . setting($pdo, 'site_name', 'Banco de Negócios');
include __DIR__ . '/includes/header.php';
?>
<main id="conteudo" class="inner-page">
  <div class="page-hero page-hero-compact">
    <div class="container">
      <p class="eyebrow"><?= e(t('list.eyebrow')) ?></p>
      <h1><?= e($heading) ?></h1>
      <p><?= e($showCats ? t('list.all_categories_text') : t('list.ads_text')) ?></p>
    </div>
  </div>

  <?php if ($showCats): ?>
  <div class="container section">
    <div class="category-grid all-categories">
      <?php foreach ($searchCats as $cat): ?>
      <a class="category-card" href="categoria.php?categoria=<?= (int)$cat['id'] ?>"><span class="category-icon"><?= e($cat['icone'] ?? '✦') ?></span><b><?= e($cat['nome']) ?></b><small><?= e(t('list.see_ads')) ?></small></a>
      <?php endforeach; ?>
    </div>
  </div>
  <?php else: ?>
    <?php include __DIR__ . '/includes/search_form.php'; ?>
    <div class="container section">
      <div class="results-bar">
        <strong><?= e(t('search.results', ['n' => count($ads)])) ?></strong>
        <?php if ($chips): ?>
        <div class="filter-chips">
          <?php foreach ($chips as [$label, $url]): ?><a class="filter-chip" href="<?= e($url) ?>" aria-label="<?= e($label) ?> ×"><?= e($label) ?> <span aria-hidden="true">×</span></a><?php endforeach; ?>
          <a class="filter-clear" href="categoria.php"><?= e(t('search.clear')) ?></a>
        </div>
        <?php endif; ?>
      </div>
      <?php if ($ads): ?>
      <div class="ads-grid">
        <?php foreach ($ads as $ad) include __DIR__ . '/includes/ad_card.php'; ?>
      </div>
      <?php else: ?>
      <div class="empty-state">
        <?= svg_icon('busca', 'icon-benefit') ?>
        <p><?= e(t('search.no_results')) ?></p>
        <a class="btn btn-outline" href="categoria.php"><?= e(t('search.clear')) ?></a>
      </div>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
