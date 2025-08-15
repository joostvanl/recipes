<?php
$path = 'blackbox_targets/blackbox_targets.json';

if (!file_exists($path)) {
    echo json_encode([]);
    exit;
}

$data = json_decode(file_get_contents($path), true);
$groups = [];

foreach ($data as $entry) {
    $groups[] = [
        'name' => $entry['group'] ?? '',
        'targets' => $entry['targets'] ?? []
    ];
}

echo json_encode($groups);
