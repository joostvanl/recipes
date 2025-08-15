<?php

declare(strict_types=1);
require __DIR__ . '/lib/functions.php';

// Enable detailed error reporting (for development only)
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

$errors = [];
$message = null;
$prefill = null;

// Fetch via URL
if (isset($_POST['url']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $urlValue = filter_input(INPUT_POST, 'url', FILTER_VALIDATE_URL);
    if (!$urlValue) {
        $errors[] = 'Please enter a valid URL.';
    } else {
        $payload = json_encode(['url' => $urlValue]);
        $webhookUrl = 'https://n8n.joostvanleeuwaarden.com/webhook/3ad6d28a-d065-4c74-93f0-aeb0fb1af30c';
        // send request
        if (function_exists('curl_init')) {
            $ch = curl_init($webhookUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
        } else {
            $opts = [
                'http' => [
                    'method'  => 'POST',
                    'header'  => "Content-Type: application/json\r\n",
                    'content' => $payload,
                    'ignore_errors' => true,
                ],
            ];
            $context = stream_context_create($opts);
            $response = @file_get_contents($webhookUrl, false, $context);
            if (isset($http_response_header[0]) && preg_match('#HTTP/\d\.\d\s+([0-9]+)#', $http_response_header[0], $m)) {
                $httpCode = (int)$m[1];
            } else {
                $httpCode = 0;
            }
        }
        if (!isset($response) || $response === false || $httpCode < 200 || $httpCode >= 300) {
            $errors[] = 'Error fetching recipe via URL.';
        } else {
            $data = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $errors[] = 'Invalid JSON received.';
            } else {
                $title = $data['title'] ?? null;
                if (!$title) {
                    $errors[] = 'JSON missing title.';
                } else {
                    $pfTitle = sanitize_text((string)$data['title'], 200);
                    $pfDesc  = sanitize_text((string)($data['description'] ?? ''), 2000);
                    $pfImage = (isset($data['image']) && filter_var($data['image'], FILTER_VALIDATE_URL)) ? (string)$data['image'] : '';
                    $pfIngs  = [];
                    foreach (($data['ingredients'] ?? []) as $ing) {
                        if (!is_array($ing)) continue;
                        $n = sanitize_text((string)($ing['name'] ?? ''), 200);
                        $q = sanitize_text((string)($ing['quantity'] ?? ''), 50);
                        $u = sanitize_text((string)($ing['unit'] ?? ''), 50);
                        if ($n !== '') { $pfIngs[] = ['name' => $n, 'quantity' => $q, 'unit' => $u]; }
                    }
                    $pfSteps = [];
                    foreach (($data['steps'] ?? []) as $st) {
                        $t = sanitize_text(is_string($st) ? $st : (string)($st['text'] ?? ''), 500);
                        if ($t !== '') { $pfSteps[] = $t; }
                    }
                    $prefill = [
                        'title' => $pfTitle,
                        'description' => $pfDesc,
                        'image' => $pfImage,
                        'ingredients' => $pfIngs,
                        'steps' => $pfSteps,
                    ];
                    $message = 'Fetched recipe. Review and edit below, then save.';
                }
            }
        }
    }
}

// Manual recipe submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['url'])) {
    if (!verify_csrf($_POST['csrf'] ?? null)) {
        $errors[] = 'Invalid CSRF token.';
    } elseif (!verify_admin_pin($_POST['admin_pin'] ?? null)) {
        $errors[] = 'Invalid PIN.';
    } else {
        $title = sanitize_text($_POST['title'] ?? '', 200);
        $description = sanitize_text($_POST['description'] ?? '', 2000);
        $ing_names = $_POST['ing_name'] ?? [];
        $ing_qtys = $_POST['ing_qty'] ?? [];
        $ing_units = $_POST['ing_unit'] ?? [];
        $steps_in = $_POST['steps'] ?? [];

        if ($title === '') {
            $errors[] = 'Title is required.';
        }
        if (empty($ing_names)) {
            $errors[] = 'At least one ingredient.';
        }
        if (empty($steps_in)) {
            $errors[] = 'At least one step.';
        }

        $ingredients = [];
        for ($i = 0; $i < count($ing_names); $i++) {
            $n = sanitize_text($ing_names[$i] ?? '', 200);
            $q = sanitize_text($ing_qtys[$i] ?? '', 50);
            $u = sanitize_text($ing_units[$i] ?? '', 50);
            if ($n !== '') {
                $ingredients[] = ['name' => $n, 'quantity' => $q, 'unit' => $u];
            }
        }
        $steps = [];
        foreach ($steps_in as $st) {
            $t = sanitize_text($st ?? '', 500);
            if ($t !== '') {
                $steps[] = $t;
            }
        }
        if (empty($errors)) {
            $slug = slugify($title);
            $base = $slug;
            $count = 1;
            while (file_exists(recipe_path($slug))) {
                $slug = "$base-" . ++$count;
            }
            $recData = [
                '$schema'    => 'https://json-schema.org/draft/2020-12/schema',
                'title'      => $title,
                'description'=> $description,
                'ingredients'=> $ingredients,
                'steps'      => $steps,
                'votes'      => 0,
                'rating'     => 0,
                'reviews'    => [],
                'image'      => sanitize_text($_POST['image'] ?? '', 1000) ?: null,
            ];
            if (save_recipe($slug, $recData)) {
                flash_set('success', 'Recipe created.');
                header('Location: recipe.php?slug=' . urlencode($slug));
                exit;
            } else {
                $errors[] = 'Failed saving manual recipe.';
            }
        }
    }
}
?>

<!doctype html>
<html lang="en" x-data="recipeForm()">
<head>
  <meta charset="utf-8">
  <title>New Recipe - Recipe Box</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="lib/theme.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-lg bg-light">
  <div class="container">
    <a class="navbar-brand" href="index.php">Recipe Box</a>
  </div>
</nav>
<main class="container my-4">
  <!-- URL Import -->
  <div class="card mb-4">
    <div class="card-body">
      <h5 class="card-title">Import via URL</h5>
      <?php if ($message): ?>
        <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
      <?php endif; ?>
      <?php foreach ($errors as $e): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($e) ?></div>
      <?php endforeach; ?>
      <form method="post" class="d-flex" onsubmit="return requestPinForFetch(this)">
        <input type="hidden" name="admin_pin" value="">
        <input type="url" name="url" class="form-control me-2" placeholder="Recipe URL" required>
        <button class="btn btn-primary" type="submit">Fetch</button>
      </form>
    </div>
  </div>

  <!-- Manual Entry -->
  <div class="card">
    <div class="card-body">
      <h5 class="card-title mb-4">Create Recipe Manually</h5>
      <form method="post" onsubmit="return requestPinAndSubmit(this)">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
        <input type="hidden" name="admin_pin" value="">
        <div class="mb-3">
          <label class="form-label">Admin PIN</label>
          <input name="admin_pin" class="form-control" type="password" inputmode="numeric" pattern="[0-9]*" required>
          <div class="form-text">Enter the 4–8 digit PIN to create a recipe.</div>
        </div>

        <div class="mb-3">
          <label class="form-label">Title</label>
          <input name="title" class="form-control" required>
        </div>

        <div class="mb-3">
          <label class="form-label">Description</label>
          <textarea name="description" class="form-control"></textarea>
        </div>

        <div class="mb-4">
          <div class="d-flex justify-content-between">
            <label class="form-label mb-0">Ingredients</label>
            <div>
              <button class="btn btn-sm btn-outline-primary" type="button" @click="addIngredient()">Add</button>
              <button class="btn btn-sm btn-outline-danger" type="button" @click="clearIngredients()">Clear</button>
            </div>
          </div>
          <template x-for="(ing, idx) in ingredients" :key="idx">
            <div class="row g-2 mb-2 align-items-end">
              <div class="col-6">
                <input x-model="ing.name" :name="`ing_name[${idx}]`" class="form-control" placeholder="Name" required>
              </div>
              <div class="col-3">
                <input x-model="ing.quantity" :name="`ing_qty[${idx}]`" class="form-control" placeholder="Qty">
              </div>
              <div class="col-2">
                <input x-model="ing.unit" :name="`ing_unit[${idx}]`" class="form-control" placeholder="Unit">
              </div>
              <div class="col-1 d-grid">
                <button class="btn btn-outline-secondary" type="button" @click="removeIngredient(idx)">&times;</button>
              </div>
            </div>
          </template>
          <div class="form-text">Add as many ingredients as needed.</div>
        </div>

        <div class="mb-4">
          <div class="d-flex justify-content-between">
            <label class="form-label mb-0">Steps</label>
            <div>
              <button class="btn btn-sm btn-outline-primary" type="button" @click="addStep()">Add</button>
              <button class="btn btn-sm btn-outline-danger" type="button" @click="clearSteps()">Clear</button>
            </div>
          </div>
          <template x-for="(st, idx) in steps" :key="idx">
            <div class="mb-2">
              <label class="form-label small">Step <span x-text="idx+1"></span></label>
              <textarea x-model="st.text" :name="`steps[${idx}]`" class="form-control" rows="2" required></textarea>
              <button class="btn btn-sm btn-outline-secondary mt-1" type="button" @click="removeStep(idx)">Remove</button>
            </div>
          </template>
          <div class="form-text">Write instructions in order.</div>
        </div>

        <div class="d-grid">
          <button class="btn btn-success" type="submit">Save Recipe</button>
        </div>
      </form>
    </div>
  </div>
</main>

<script>
function recipeForm() {
  return {
    ingredients: [{ name: '', quantity: '', unit: '' }],
    steps: [{ text: '' }],
    addIngredient() { this.ingredients.push({ name: '', quantity: '', unit: '' }); },
    removeIngredient(i) { this.ingredients.splice(i, 1); if (!this.ingredients.length) this.addIngredient(); },
    clearIngredients() { this.ingredients = [{ name: '', quantity: '', unit: '' }]; },
    addStep() { this.steps.push({ text: '' }); },
    removeStep(i) { this.steps.splice(i, 1); if (!this.steps.length) this.addStep(); },
    clearSteps() { this.steps = [{ text: '' }]; }
  };
}
</script>

<script>
function requestPinForFetch(form){
  const pin = prompt('Enter PIN to fetch and prefill');
  if (pin === null || pin.trim() === '') return false;
  form.querySelector('input[name="admin_pin"]').value = pin.trim();
  return true;
}
function requestPinAndSubmit(form){
  const pin = prompt('Enter PIN to save recipe');
  if (pin === null || pin.trim() === '') return false;
  form.querySelector('input[name="admin_pin"]').value = pin.trim();
  return true;
}
</script>

</body>
</html>
