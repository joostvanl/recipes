<?php
$path = 'blackbox_targets/blackbox_targets.json';
$input = json_decode(file_get_contents('php://input'), true);

if (!is_array($input)) {
    http_response_code(400);
    echo "Invalid input.";
    exit;
}

$data = [];

foreach ($input as $groupEntry) {
    if (!isset($groupEntry['group']) || !isset($groupEntry['targets'])) {
        continue;
    }

    $filtered = array_values(array_filter($groupEntry['targets'], fn($t) => trim($t) !== ''));
    if (empty($filtered)) continue;

    $data[] = [
        'labels' => [
            'job' => 'blackbox_http',
            'group' => $groupEntry['group']
        ],
        'targets' => $filtered
    ];
}

file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

// Reload Prometheus
$ch = curl_init('http://192.168.1.91:9090/-/reload');
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);

echo "Targets saved successfully.";
