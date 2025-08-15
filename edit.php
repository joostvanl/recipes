<?php
declare(strict_types=1);
require __DIR__ . '/lib/functions.php';

// Add delete_recipe function if it doesn't exist
if (!function_exists('delete_recipe')) {
    function delete_recipe(string $slug): bool {
        $file = __DIR__ . '/data/recipes/' . $slug . '.json';
        if (file_exists($file)) {
            return unlink($file);
        }
        return false;
    }
}

$slug = isset($_GET['slug']) ? preg_replace('/[^a-z0-9\-]/i', '', (string)$_GET['slug']) : '';
if ($slug === '') { http_response_code(400); echo 'Missing slug'; exit; }
$existing = load_recipe($slug);
if (!$existing) { http_response_code(404); echo 'Recipe not found'; exit; }

$errors = [];
$message = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf'] ?? null)) {
        $errors[] = 'Invalid CSRF token.';
    } elseif (!verify_admin_pin($_POST['admin_pin'] ?? null)) {
        $errors[] = 'Invalid PIN.';
    } else {
        // Check if this is a delete action
        if (isset($_POST['action']) && $_POST['action'] === 'delete_recipe') {
            if (delete_recipe($slug)) {
                flash_set('success', 'Recipe deleted.');
                header('Location: index.php');
                exit;
            } else {
                $errors[] = 'Failed to delete recipe.';
            }
        } else {
            // This is the existing save/update logic
            $title = sanitize_text($_POST['title'] ?? '', 200);
            $description = sanitize_text($_POST['description'] ?? '', 2000);
            $image = sanitize_text($_POST['image'] ?? '', 1000);
            $tags = array_map('trim', array_filter(explode(',', sanitize_text($_POST['tags'] ?? '', 500))));
            $ing_names = $_POST['ing_name'] ?? [];
            $ing_qtys = $_POST['ing_qty'] ?? [];
            $ing_units = $_POST['ing_unit'] ?? [];
            $steps_in = $_POST['steps'] ?? [];
            if ($title === '') $errors[] = 'Title is required.';
            $ingredients = [];
            for ($i = 0; $i < count($ing_names); $i++) {
                $n = sanitize_text($ing_names[$i] ?? '', 200);
                $q = sanitize_text($ing_qtys[$i] ?? '', 50);
                $u = sanitize_text($ing_units[$i] ?? '', 50);
                if ($n !== '') $ingredients[] = ['name' => $n, 'quantity' => $q, 'unit' => $u];
            }
            $steps = [];
            foreach ($steps_in as $st) {
                $t = sanitize_text($st ?? '', 500);
                if ($t !== '') $steps[] = $t;
            }
            if (!$errors) {
                $data = $existing;
                $data['title'] = $title;
                $data['description'] = $description;
                $data['image'] = $image ?: null;
                $data['tags'] = $tags;
                $data['ingredients'] = $ingredients;
                $data['steps'] = $steps;
                if (save_recipe($slug, $data)) {
                    flash_set('success', 'Recipe updated.');
                    header('Location: recipe.php?slug=' . urlencode($slug));
                    exit;
                } else {
                    $errors[] = 'Failed to save changes.';
                }
            }
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Edit Recipe - <?= htmlspecialchars($existing['title'] ?? $slug) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
      <link href="lib/theme.css" rel="stylesheet">
 </head>
 <body>
<nav class="navbar navbar-expand-lg bg-body-tertiary border-bottom" style="background: var(--surface)">
  <div class="container">
    <a class="navbar-brand" href="index.php">Recipe Box</a>
  </div>
    </nav>
    <main class="container my-4">
      <div class="row justify-content-center">
        <div class="col-12 col-lg-9 col-xl-8">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <h1 class="h4 mb-0">Edit Recipe</h1>
            <a href="recipe.php?slug=<?= urlencode($slug) ?>" class="btn btn-outline-secondary">Cancel</a>
          </div>
          <?php foreach ($errors as $e): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($e) ?></div>
          <?php endforeach; ?>
          
          <!-- Main form for editing and saving -->
          <form method="post" class="section-card p-3 p-md-4" onsubmit="return requestPinAndSubmit(this)">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
            <input type="hidden" name="admin_pin" value="">
            <div class="mb-3">
              <label class="form-label">Title</label>
              <input name="title" class="form-control" required value="<?= htmlspecialchars($existing['title'] ?? '') ?>">
            </div>
            <div class="mb-3">
              <label class="form-label">Description</label>
              <textarea name="description" class="form-control" rows="3"><?= htmlspecialchars($existing['description'] ?? '') ?></textarea>
            </div>
            <div class="mb-3">
              <label class="form-label">Image URL (optional)</label>
              <input name="image" class="form-control" value="<?= htmlspecialchars($existing['image'] ?? '') ?>">
              <div class="form-text">Provide a direct image URL for the recipe hero and card. This will be used as a fallback if no review photos exist.</div>
            </div>
            <div class="mb-3">
              <label class="form-label">Tags (optional)</label>
              <input name="tags" class="form-control" value="<?= htmlspecialchars(implode(', ', $existing['tags'] ?? [])) ?>" placeholder="e.g. italian, pasta, quick, vegetarian">
              <div class="form-text">Separate multiple tags with commas. Tags help with searching and filtering recipes.</div>
            </div>
            <div class="mb-4">
              <div class="d-flex justify-content-between align-items-center mb-2">
                <label class="form-label mb-0">Ingredients</label>
                <button type="button" class="btn btn-sm btn-outline-primary" onclick="addIngRow()">Add</button>
              </div>
              <div id="ings">
                <?php foreach (($existing['ingredients'] ?? []) as $idx => $ing): ?>
                  <div class="row g-2 mb-2 align-items-end ing-row">
                    <div class="col-6"><input name="ing_name[]" class="form-control" placeholder="Name" value="<?= htmlspecialchars($ing['name'] ?? '') ?>"></div>
                    <div class="col-3"><input name="ing_qty[]" class="form-control" placeholder="Qty" value="<?= htmlspecialchars($ing['quantity'] ?? '') ?>"></div>
                    <div class="col-2"><input name="ing_unit[]" class="form-control" placeholder="Unit" value="<?= htmlspecialchars($ing['unit'] ?? '') ?>"></div>
                    <div class="col-1 d-grid"><button class="btn btn-outline-danger" type="button" onclick="this.closest('.ing-row').remove()">&times;</button></div>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
            <div class="mb-4">
              <div class="d-flex justify-content-between align-items-center mb-2">
                <label class="form-label mb-0">Steps</label>
                <button type="button" class="btn btn-sm btn-outline-primary" onclick="addStepRow()">Add</button>
              </div>
              <div id="steps">
                <?php foreach (($existing['steps'] ?? []) as $st): ?>
                  <div class="mb-2 step-row">
                    <textarea name="steps[]" class="form-control" rows="2"><?= htmlspecialchars($st) ?></textarea>
                    <button class="btn btn-sm btn-outline-danger mt-1" type="button" onclick="this.closest('.step-row').remove()">Remove</button>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
            <div class="d-flex gap-2">
              <button class="btn btn-success" type="submit">Save Changes</button>
            </div>
          </form>
          
          <!-- Separate form for delete action -->
          <form method="post" class="mt-3" onsubmit="return confirmDelete(this)">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
            <input type="hidden" name="admin_pin" value="">
            <input type="hidden" name="action" value="delete_recipe">
            <button class="btn btn-danger" type="submit">Delete Recipe</button>
          </form>
        </div>
      </div>
    </main>
<script>
function addIngRow() {
  const wrap = document.getElementById('ings');
  wrap.insertAdjacentHTML('beforeend', `
    <div class="row g-2 mb-2 align-items-end ing-row">
      <div class="col-6"><input name="ing_name[]" class="form-control" placeholder="Name"></div>
      <div class="col-3"><input name="ing_qty[]" class="form-control" placeholder="Qty"></div>
      <div class="col-2"><input name="ing_unit[]" class="form-control" placeholder="Unit"></div>
      <div class="col-1 d-grid"><button class="btn btn-outline-danger" type="button" onclick="this.closest('.ing-row').remove()">&times;</button></div>
    </div>
  `);
}
function addStepRow() {
  const wrap = document.getElementById('steps');
  wrap.insertAdjacentHTML('beforeend', `
    <div class="mb-2 step-row">
      <textarea name="steps[]" class="form-control" rows="2"></textarea>
      <button class="btn btn-sm btn-outline-danger mt-1" type="button" onclick="this.closest('.step-row').remove()">Remove</button>
    </div>
  `);
}
</script>
<script>
function requestPinAndSubmit(form) {
  const pin = prompt('Enter PIN to save changes');
  if (pin === null || pin.trim() === '') return false;
  const pinField = form.querySelector('input[name="admin_pin"]');
  if (pinField) pinField.value = pin.trim();
  return true;
}

function confirmDelete(form) {
  if (!confirm('Are you sure you want to delete this recipe?')) {
    return false;
  }
  
  if (!confirm('Are you really sure? This action cannot be undone.')) {
    return false;
  }
  
  const pin = prompt('Enter PIN to delete this recipe');
  if (pin === null || pin.trim() === '') return false;
  
  const pinField = form.querySelector('input[name="admin_pin"]');
  if (pinField) pinField.value = pin.trim();
  
  return true;
}
</script>
</body>
</html>