<?php 
declare(strict_types=1); 
require __DIR__ . '/lib/functions.php'; 

$q = $_GET['q'] ?? ''; 
$recipes = search_recipes($q); 
$flash = flash_get(); 
?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Recipes</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="lib/theme.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg bg-body-tertiary border-bottom" style="background: var(--surface)">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php">Recipe Box</a>
            <div class="d-flex">
                <a href="new.php" class="btn btn-accent text-white">New Recipe</a>
            </div>
        </div>
    </nav>
    
    <main class="container my-4">
        <?php if ($flash): ?>
            <div class="alert alert-<?= htmlspecialchars($flash['type']) ?> mb-4">
                <?= htmlspecialchars($flash['message']) ?>
            </div>
        <?php endif; ?>
        
        <section class="hero p-4 p-md-5 mb-4">
            <div class="d-flex flex-column flex-md-row align-items-start align-items-md-center gap-3">
                <div class="flex-grow-1">
                    <span class="badge rounded-pill">Fresh & tasty</span>
                    <h1 class="mt-2 mb-2 h3">Discover and save your favorite recipes</h1>
                    <div class="muted">Search by title, ingredient, description, or tags</div>
                </div>
                <div class="w-100 w-md-auto" style="min-width:320px;">
                    <form method="get" class="">
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="bi bi-search"></i>
                            </span>
                            <input type="text" class="form-control" name="q" 
                                   placeholder="e.g. chicken, pasta, chocolate" 
                                   value="<?= htmlspecialchars($q) ?>">
                            <button class="btn btn-outline-secondary" type="submit">Search</button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Popular Tags Section -->
            <?php
            // Get all recipes to extract tags
            $allRecipes = list_recipes();
            $tagCounts = [];
            
            foreach ($allRecipes as $recipe) {
                if (!empty($recipe['tags']) && is_array($recipe['tags'])) {
                    foreach ($recipe['tags'] as $tag) {
                        $tag = trim(strtolower($tag));
                        if (!empty($tag)) {
                            $tagCounts[$tag] = ($tagCounts[$tag] ?? 0) + 1;
                        }
                    }
                }
            }
            
            // Sort tags by frequency and get top 10
            arsort($tagCounts);
            $popularTags = array_slice(array_keys($tagCounts), 0, 10);
            ?>
            
            <?php if (!empty($popularTags)): ?>
                <div class="mt-3">
                    <div class="small text-muted mb-2">Popular tags:</div>
                    <div class="d-flex flex-wrap gap-2 align-items-center">
                        <?php foreach ($popularTags as $tag): ?>
                            <a href="?q=<?= urlencode($tag) ?>" class="popular-tag">
                                <?= htmlspecialchars($tag) ?>
                            </a>
                        <?php endforeach; ?>
                        <?php if (!empty($q)): ?>
                            <a href="?" class="clear-search-tag">
                                Clear search
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
            </div>
        </section>
        
        <?php if (!empty($q)): ?>
            <div class="mb-3">
                <div class="d-flex align-items-center gap-2">
                    <h2 class="h5 mb-0">Search results for "<?= htmlspecialchars($q) ?>"</h2>
                    <span class="badge bg-secondary"><?= count($recipes) ?> recipe<?= count($recipes) !== 1 ? 's' : '' ?></span>
                    <a href="?" class="btn btn-sm btn-outline-secondary">Clear search</a>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if (empty($recipes)): ?>
            <div class="text-muted">
                <?php if (!empty($q)): ?>
                    No recipes found for "<?= htmlspecialchars($q) ?>". 
                    <a href="?" class="text-decoration-none">Try a different search</a> or 
                    <a href="new.php" class="text-decoration-none">add a new recipe</a>.
                <?php else: ?>
                    No recipes found.
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($recipes as $r): 
                    $full = load_recipe($r['slug']); 
                    $img = best_review_photo($full) ?? $full['image'] ?? null; 
                ?>
                    <div class="col-12 col-sm-6 col-lg-4">
                        <a href="recipe.php?slug=<?= urlencode($r['slug']) ?>" class="text-decoration-none text-reset">
                            <div class="recipe-card h-100">
                                <div class="recipe-media">
                                    <?php if ($img): ?>
                                        <img src="<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($r['title']) ?>">
                                    <?php endif; ?>
                                    <div class="recipe-badge">
                                        <i class="bi bi-star-fill rating-star"></i> 
                                        <?= number_format((float)$r['rating'], 1) ?> 
                                        <span class="muted">(<?= (int)$r['votes'] ?>)</span>
                                    </div>
                                </div>
                                <div class="p-3">
                                    <h2 class="h6 mb-1"><?= htmlspecialchars($r['title']) ?></h2>
                                    <div class="small muted mb-2">
                                        <?= htmlspecialchars(mb_strimwidth($r['description'], 0, 120, '…')) ?>
                                    </div>
                                    <?php if (!empty($r['tags'])): ?>
                                        <div class="mb-2">
                                            <?php foreach (array_slice($r['tags'], 0, 3) as $tag): ?>
                                                <span class="tag-badge me-1">
                                                    <?= htmlspecialchars($tag) ?>
                                                </span>
                                            <?php endforeach; ?>
                                            <?php if (count($r['tags']) > 3): ?>
                                                <span class="badge rounded-pill" 
                                                      style="background: rgba(108,117,125,.12); color: var(--bs-secondary); padding: 0.4em 0.8em; font-size: 0.75rem;">
                                                    +<?= count($r['tags']) - 3 ?> more
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                    <div class="d-flex align-items-center gap-2 small">
                                        <span class="text-success">
                                            <i class="bi bi-egg-fried"></i>
                                        </span>
                                        <span class="muted">Ingredients: <?= count($r['ingredients']) ?></span>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>