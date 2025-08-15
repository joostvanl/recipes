<?php

declare(strict_types=1);

// Enable detailed error reporting (for development only)
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

// On form submission, send URL to webhook and save JSON response
$message = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $urlValue = filter_input(INPUT_POST, 'url', FILTER_VALIDATE_URL);

    if (!$urlValue) {
        $error = 'Please enter a valid URL.';
    } else {
        // Prepare payload and headers
        $dataPayload = ['url' => $urlValue];
        $jsonPayload = json_encode($dataPayload);
        $webhookUrl = 'https://n8n.joostvanleeuwaarden.com/webhook/3ad6d28a-d065-4c74-93f0-aeb0fb1af30c';

        // Send POST: try cURL if available, otherwise use file_get_contents
        if (function_exists('curl_init')) {
            $ch = curl_init($webhookUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($jsonPayload)
            ]);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);

            $response = curl_exec($ch);
            $errno    = curl_errno($ch);
            $errMsg   = curl_error($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($response === false) {
                $error = "cURL error ({$errno}): {$errMsg}";
            } elseif ($httpCode < 200 || $httpCode >= 300) {
                $error = "HTTP error code: {$httpCode}. Response: " . htmlspecialchars((string)$response);
            }
        } else {
            $opts = [
                'http' => [
                    'method'  => 'POST',
                    'header'  => "Content-Type: application/json\r\n" .
                                 "Content-Length: " . strlen($jsonPayload) . "\r\n",
                    'content' => $jsonPayload,
                    'ignore_errors' => true,
                ],
            ];
            $context  = stream_context_create($opts);
            $response = @file_get_contents($webhookUrl, false, $context);
            // parse HTTP response code from $http_response_header
            if (isset($http_response_header) && preg_match('#HTTP/\d\.\d\s+([0-9]+)#', $http_response_header[0], $m)) {
                $httpCode = (int)$m[1];
            } else {
                $httpCode = 0;
            }

            if ($response === false) {
                $error = 'HTTP request failed via file_get_contents.';
            } elseif ($httpCode < 200 || $httpCode >= 300) {
                $error = "HTTP error code: {$httpCode}. Response: " . htmlspecialchars((string)$response);
            }
        }

        // If no HTTP or transport errors, process response
        if (!$error) {
            $decoded = json_decode((string)$response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $error = 'JSON parse error ('.json_last_error_msg().'): ' . htmlspecialchars((string)$response);
            } else {
                // Determine filename from title
                $title = $decoded['title'] ?? null;
                if (!$title) {
                    $error = 'Response JSON missing "title" property.';
                } else {
                    // Slugify title
                    $slug = preg_replace('/[^\p{L}\p{Nd}]+/u', '-', trim($title));
                    $slug = trim($slug, '-');

                    $dir = __DIR__ . '/data/recipes';
                    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
                        $error = 'Failed to create directory: ' . htmlspecialchars($dir);
                    } else {
                        $filename = $dir . '/' . $slug . '.json';
                        if (file_put_contents($filename, json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) === false) {
                            $error = 'Failed to write file: ' . htmlspecialchars($filename);
                        } else {
                            $message = "Data saved to recipes/{$slug}.json";
                        }
                    }
                }
            }
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>URL Sender</title>
  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
  <div class="container py-5">
    <div class="row justify-content-center">
      <div class="col-md-6">
        <div class="card shadow-sm">
          <div class="card-body">
            <h5 class="card-title mb-4">Send URL</h5>

            <?php if ($message): ?>
              <div class="alert alert-success" role="alert"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
              <div class="alert alert-danger" role="alert"><?= nl2br(htmlspecialchars($error)) ?></div>
            <?php endif; ?>

            <form method="post">
              <div class="input-group mb-3">
                <input
                  type="url"
                  name="url"
                  class="form-control"
                  placeholder="Enter URL here"
                  aria-label="URL input"
                  value="<?= isset($urlValue) ? htmlspecialchars($urlValue) : '' ?>"
                  required
                >
                <button class="btn btn-primary" type="submit">Send</button>
              </div>
            </form>

          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Bootstrap JS bundle -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>