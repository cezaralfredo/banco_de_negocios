<?php
/*
 * Busca "Encontre o que você precisa" (A–Z + campos).
 * Espera: $filters (search_filters()), $searchCats, $searchLocations, opcional $popular.
 * Todos os campos são enviados juntos para categoria.php, que aplica cada filtro.
 */
$filters = $filters ?? search_filters([]);
$letterUrl = function (string $letter) use ($filters): string {
    $q = array_filter([
        'q' => $filters['q'], 'categoria' => $filters['categoria'] ?: '', 'localizacao' => $filters['localizacao'],
        'tipo' => $filters['tipo'], 'ordem' => $filters['ordem'] !== 'relevancia' ? $filters['ordem'] : '', 'letra' => $letter,
    ], fn($v) => $v !== '' && $v !== 0);
    return 'categoria.php' . ($q ? '?' . http_build_query($q) : '');
};
?>
<section class="search-card container" id="busca">
  <h2><?= e(t('search.title')) ?></h2>
  <p><?= e(t('search.subtitle')) ?></p>
  <div class="alphabet-scroller">
    <button type="button" class="alphabet-arrow" data-alphabet-prev aria-label="<?= e(t('search.prev_letters')) ?>">‹</button>
    <nav class="alphabet" aria-label="<?= e(t('search.alphabet')) ?>">
      <a href="<?= e($letterUrl('')) ?>" class="alphabet-all<?= $filters['letra'] === '' && !empty($isResultsPage) ? ' selected' : '' ?>"><?= e(t('search.all_letters')) ?></a>
      <?php foreach (range('A', 'Z') as $letter): ?>
      <a href="<?= e($letterUrl($letter)) ?>" class="<?= $filters['letra'] === $letter ? 'selected' : '' ?>"<?= $filters['letra'] === $letter ? ' aria-current="true"' : '' ?>><?= $letter ?></a>
      <?php endforeach; ?>
    </nav>
    <button type="button" class="alphabet-arrow" data-alphabet-next aria-label="<?= e(t('search.next_letters')) ?>">›</button>
  </div>
  <form class="advanced-search" action="categoria.php" method="get" id="advancedSearch">
    <?php if ($filters['letra'] !== ''): ?><input type="hidden" name="letra" value="<?= e($filters['letra']) ?>"><?php endif; ?>
    <label><span class="sr-only"><?= e(t('search.term')) ?></span><input name="q" value="<?= e($filters['q']) ?>" placeholder="<?= e(t('search.placeholder')) ?>"><?= svg_icon('busca', 'icon-sm') ?></label>
    <label><span><?= e(t('search.category')) ?></span>
      <select name="categoria"><option value=""><?= e(t('search.all')) ?></option>
        <?php foreach ($searchCats as $cat): ?><option value="<?= (int)$cat['id'] ?>" <?= $filters['categoria'] === (int)$cat['id'] ? 'selected' : '' ?>><?= e($cat['nome']) ?></option><?php endforeach; ?>
      </select>
    </label>
    <label><span><?= e(t('search.location')) ?></span>
      <select name="localizacao"><option value=""><?= e(t('search.all')) ?></option>
        <?php foreach ($searchLocations as $loc): ?><option value="<?= e($loc['localizacao']) ?>" <?= $filters['localizacao'] === $loc['localizacao'] ? 'selected' : '' ?>><?= e($loc['localizacao']) ?></option><?php endforeach; ?>
      </select>
    </label>
    <label><span><?= e(t('search.type')) ?></span>
      <select name="tipo"><option value=""><?= e(t('search.all_types')) ?></option>
        <?php foreach (ad_types() as $k => $label): ?><option value="<?= e($k) ?>" <?= $filters['tipo'] === $k ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
      </select>
    </label>
    <button class="btn btn-primary" type="submit"><?= svg_icon('busca', 'icon-sm') ?> <?= e(t('search.button')) ?></button>
  </form>
  <div class="popular">
    <?php if (!empty($popular)): ?>
      <strong><?= e(t('search.popular')) ?></strong>
      <?php foreach ($popular as $term): ?><a href="categoria.php?q=<?= urlencode($term['termo']) ?>"><?= e($term['termo']) ?></a><?php endforeach; ?>
    <?php endif; ?>
    <label class="sort"><?= e(t('search.sort')) ?>
      <select name="ordem" form="advancedSearch"<?= !empty($isResultsPage) ? " data-autosubmit" : "" ?>>
        <?php foreach (['relevancia' => 'search.sort_relevance', 'avaliacao' => 'search.sort_rating', 'recentes' => 'search.sort_recent', 'az' => 'search.sort_az'] as $k => $label): ?>
        <option value="<?= $k ?>" <?= $filters['ordem'] === $k ? 'selected' : '' ?>><?= e(t($label)) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
  </div>
</section>
