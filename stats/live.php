<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

$dataPath = __DIR__ . '/data.json';
if (!is_file($dataPath)) {
    http_response_code(404);
    echo json_encode(['error' => 'Stats data not found']);
    exit;
}

$data = file_get_contents($dataPath);
if ($data === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Unable to read stats data']);
    exit;
}

echo $data;
