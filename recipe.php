<?php
declare(strict_types=1);

require __DIR__ . '/lib/functions.php';

// Where your recipe JSON files live (adjust if needed)
$RECIPES_DIR = __DIR__ . '/data/recipes';

// PHP 7 polyfill for str_ends_with (safe on PHP 8+ as well)
if (!function_exists('str_ends_with')) {
    function str_ends_with(string $haystack, string $needle): bool {
        if ($needle === '') return true;
        $len = strlen($needle);
        return substr($haystack, -$len) === $needle;
    }
}

// 1) Get slug safely BEFORE using it
$slug = isset($_GET['slug']) ? (string)$_GET['slug'] : '';
if ($slug === '') {
    http_response_code(400);
    echo "Missing recipe slug.";
    exit;
}

// 2) Sanitize slug, allow only safe filename chars
$slug = preg_replace('/[^a-z0-9._-]/i', '', $slug);

// 3) Build file path, ensure .json extension
$file = rtrim($RECIPES_DIR, "/\\") . DIRECTORY_SEPARATOR
      . (str_ends_with(strtolower($slug), '.json') ? $slug : $slug . '.json');

// 4) Load recipe JSON
if (!is_file($file)) {
    http_response_code(404);
    echo "Recipe not found.";
    exit;
}

$raw = file_get_contents($file);
$recipe = json_decode((string)$raw, true);
if (!is_array($recipe)) {
    http_response_code(500);
    echo "Invalid recipe JSON.";
    exit;
}

// Normalize optional fields
if (!isset($recipe['reviews']) || !is_array($recipe['reviews'])) $recipe['reviews'] = [];
if (!isset($recipe['votes'])) $recipe['votes'] = count($recipe['reviews']);
if (!isset($recipe['rating'])) $recipe['rating'] = compute_average_rating($recipe['reviews']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_recipe') {
    if (!verify_csrf($_POST['csrf'] ?? null)) {
        flash_set('danger', 'Invalid CSRF token.');
        header('Location: recipe.php?slug=' . urlencode($slug));
        exit;
    }
    if (!verify_admin_pin($_POST['admin_pin'] ?? null)) {
        flash_set('danger', 'Invalid PIN.');
        header('Location: recipe.php?slug=' . urlencode($slug));
        exit;
    }
    // Delete JSON
    $ok = @unlink($file);
    // Best effort: delete uploads directory
    $uploadsDir = __DIR__ . '/uploads/recipes/' . $slug;
    if (is_dir($uploadsDir)) {
        $iter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($uploadsDir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iter as $path) { $path->isDir() ? @rmdir($path->getPathname()) : @unlink($path->getPathname()); }
        @rmdir($uploadsDir);
    }
    if ($ok) {
        flash_set('success', 'Recipe deleted.');
        header('Location: index.php');
    } else {
        flash_set('danger', 'Failed to delete recipe.');
        header('Location: recipe.php?slug=' . urlencode($slug));
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_review') {
    if (!verify_csrf($_POST['csrf'] ?? null)) {
        flash_set('danger', 'Invalid CSRF token.');
        header('Location: recipe.php?slug=' . urlencode($slug));
        exit;
    }

    $name = sanitize_text($_POST['name'] ?? '', 100);
    if ($name === '') $name = 'Anonymous';
    $rating = (int)($_POST['rating'] ?? 0);
    $comment = sanitize_text($_POST['comment'] ?? '', 2000);
    $photoUrl = null;

    // Handle optional camera/file upload
    if (!empty($_FILES['photo_file']['tmp_name']) && is_uploaded_file($_FILES['photo_file']['tmp_name'])) {
        $maxSize = 5 * 1024 * 1024; // 5MB
        $size = (int)($_FILES['photo_file']['size'] ?? 0);
        if ($size > 0 && $size <= $maxSize) {
            $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : null;
            $mime = $finfo ? finfo_file($finfo, $_FILES['photo_file']['tmp_name']) : ($_FILES['photo_file']['type'] ?? '');
            if ($finfo) finfo_close($finfo);
            $ext = null;
            $map = [ 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif' ];
            if (isset($map[$mime])) {
                $ext = $map[$mime];
            }
            if ($ext) {
                $baseUploads = __DIR__ . '/uploads/recipes/' . $slug;
                if (!is_dir($baseUploads)) {
                    if (!@mkdir($baseUploads, 0775, true)) {
                        error_log("Failed to create upload directory: " . $baseUploads);
                    }
                }
                $fileName = 'rev_' . str_replace('.', '', uniqid('', true)) . '.' . $ext;
                $destPath = $baseUploads . '/' . $fileName;
                if (@move_uploaded_file($_FILES['photo_file']['tmp_name'], $destPath)) {
                    $photoUrl = '/uploads/recipes/' . $slug . '/' . $fileName;
                } else {
                    error_log("Failed to move uploaded file from " . $_FILES['photo_file']['tmp_name'] . " to " . $destPath);
                }
            }
        }
    }

    // Or use provided photo URL if valid
    if (!$photoUrl) {
        $candidate = $_POST['photo'] ?? null;
        if ($candidate && filter_var($candidate, FILTER_VALIDATE_URL)) {
            $photoUrl = $candidate;
        }
    }

    if ($rating < 1 || $rating > 5) {
        flash_set('danger', 'Please select a rating between 1 and 5.');
        header('Location: recipe.php?slug=' . urlencode($slug));
        exit;
    }

    // Reload to avoid race conditions
    $raw = file_get_contents($file);
    $recipe = json_decode((string)$raw, true);
    if (!is_array($recipe)) {
        flash_set('danger', 'Recipe missing or corrupt.');
        header('Location: index.php');
        exit;
    }
    if (!isset($recipe['reviews']) || !is_array($recipe['reviews'])) $recipe['reviews'] = [];

    $recipe['reviews'][] = [
        'name' => $name,
        'rating' => $rating,
        'comment' => $comment,
        'photo' => $photoUrl,
        'date' => date('c'),
    ];

    // Recalculate rating and votes
    $recipe['votes'] = count($recipe['reviews']);
    $recipe['rating'] = compute_average_rating($recipe['reviews']);

    // Save back to the same file
    $ok = (bool)file_put_contents(
        $file,
        json_encode($recipe, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        LOCK_EX
    );

    if ($ok) {
        flash_set('success', 'Thank you for your review!');
    } else {
        flash_set('danger', 'Failed to save your review.');
    }

    header('Location: recipe.php?slug=' . urlencode($slug));
    exit;
}

$flash = flash_get();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title><?= htmlspecialchars($recipe['title'] ?? 'Recipe') ?> - Recipe</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="lib/theme.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-lg bg-body-tertiary border-bottom" style="background: var(--surface)">
    <div class="container">
        <a class="navbar-brand" href="index.php">Recipe Box</a>
        <div class="d-flex">
            <a href="new.php" class="btn btn-primary">New Recipe</a>
        </div>
    </div>
</nav>

<main class="container my-4">
    <?php if ($flash): ?>
        <div class="alert alert-<?= htmlspecialchars($flash['type']) ?>"><?= htmlspecialchars($flash['message']) ?></div>
    <?php endif; ?>

    <?php $img = best_review_photo($recipe) ?? $recipe['image'] ?? null; ?>
    <section class="recipe-hero mb-4">
        <?php if ($img): ?>
            <div class="recipe-hero-media"><img src="<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($recipe['title'] ?? '') ?>"></div>
        <?php endif; ?>
        <div class="p-3 p-md-4">
            <div class="d-flex justify-content-between align-items-start gap-3">
                <div>
                    <h1 class="h3 mb-1"><?= htmlspecialchars($recipe['title'] ?? '') ?></h1>
                    <div class="muted mb-2"><?= htmlspecialchars($recipe['description'] ?? '') ?></div>
                    <?php if (!empty($recipe['tags'])): ?>
                        <div class="mb-2">
                                                    <?php foreach ($recipe['tags'] as $tag): ?>
                            <span class="tag-badge me-1">
                                <?= htmlspecialchars($tag) ?>
                            </span>
                        <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <span class="rating-star"><i class="bi bi-star-fill"></i> <?= number_format((float)($recipe['rating'] ?? 0), 1) ?></span>
                        <span class="muted small">(<?= (int)($recipe['votes'] ?? 0) ?> votes)</span>
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <a href="index.php" class="btn btn-outline-secondary">Back</a>
                    <a href="edit.php?slug=<?= urlencode($slug) ?>" class="btn btn-accent text-white">Edit</a>
                </div>
            </div>
        </div>
    </section>

    <div class="row g-4">
        <div class="col-md-5">
            <div class="section-card p-3 p-md-4">
                <div class="section-title">
                    <div class="icon"><i class="bi bi-basket"></i></div>
                    <h2 class="h5 mb-0">Ingredients</h2>
                </div>
                <ul class="list-group">
                    <?php foreach (($recipe['ingredients'] ?? []) as $ing): $label = trim(($ing['quantity'] ?? '') . ' ' . ($ing['unit'] ?? '') . ' ' . ($ing['name'] ?? '')); ?>
                        <li class="list-group-item ingredient-item d-flex align-items-center gap-2">
                            <input type="checkbox" class="form-check-input mt-0" onclick="this.closest('li').classList.toggle('checked', this.checked)">
                            <span><?= htmlspecialchars($label) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        <div class="col-md-7">
            <div class="section-card p-3 p-md-4">
                <div class="section-title">
                    <div class="icon" style="background: rgba(255,107,53,.12); color: var(--accent)"><i class="bi bi-list-ol"></i></div>
                    <h2 class="h5 mb-0">Steps</h2>
                </div>
                <div>
                    <?php $i = 1; foreach (($recipe['steps'] ?? []) as $step): ?>
                        <div class="step-item p-3 d-flex gap-3 align-items-start">
                            <div class="step-index"><?= $i++ ?></div>
                            <div><?= htmlspecialchars($step) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <hr class="my-4">

    <div class="row g-4">
        <div class="col-md-6">
            <h3 class="h5 mb-3">Reviews (<?= count($recipe['reviews']) ?>)</h3>
            <?php if (empty($recipe['reviews'])): ?>
                <div class="text-muted">No reviews yet. Be the first!</div>
            <?php else: ?>
                <?php foreach (array_reverse($recipe['reviews']) as $rev): ?>
                    <div class="border rounded p-3 mb-3">
                        <div class="d-flex justify-content-between">
                            <strong><?= htmlspecialchars($rev['name'] ?? 'Anonymous') ?></strong>
                            <span class="rating"><i class="bi bi-star-fill"></i> <?= (int)($rev['rating'] ?? 0) ?>/5</span>
                        </div>
                        <?php if (!empty($rev['comment'])): ?>
                            <div class="mt-2"><?= nl2br(htmlspecialchars($rev['comment'])) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($rev['photo'])): ?>
                            <div class="mt-2">
                                <img src="<?= htmlspecialchars($rev['photo']) ?>" alt="Review photo" class="img-fluid rounded" style="max-width: 200px; max-height: 200px;">
                            </div>
                        <?php endif; ?>
                        <div class="text-muted small mt-2">
                            <?= htmlspecialchars(isset($rev['date']) ? date('M j, Y', strtotime($rev['date'])) : '') ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="col-md-6">
            <h3 class="h5 mb-3">Add a Review</h3>
            <form method="post" class="card card-body" enctype="multipart/form-data">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                <input type="hidden" name="action" value="add_review">

                <div class="mb-3">
                    <label class="form-label">Name (optional)</label>
                    <input type="text" name="name" class="form-control" maxlength="100" placeholder="Your name">
                </div>

                <div class="mb-3">
                    <label class="form-label">Rating</label>
                    <select name="rating" class="form-select" required>
                        <option value="">Choose...</option>
                        <?php for ($i = 5; $i >= 1; $i--): ?>
                            <option value="<?= $i ?>"><?= $i ?> star<?= $i > 1 ? 's' : '' ?></option>
                        <?php endfor; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">Comment</label>
                    <textarea name="comment" class="form-control" rows="4" maxlength="2000" placeholder="What did you think?"></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Photo (optional)</label>
                    <div class="mb-2">
                        <label class="form-label small">Photo URL</label>
                        <input type="url" name="photo" class="form-control" placeholder="https://example.com/your-photo.jpg">
                        <div class="form-text">Provide a direct image URL if you prefer to link to an external image.</div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Or upload a file</label>
                        <input type="file" name="photo_file" class="form-control" accept="image/*" capture="environment">
                        <div class="form-text">Max 5MB. JPG, PNG, WEBP, or GIF.</div>
                    </div>
                </div>

                <button class="btn btn-primary" type="submit">Submit Review</button>
            </form>
        </div>
    </div>
</main>
</body>
</html>