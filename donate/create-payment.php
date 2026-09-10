<?php
// Mollie donation checkout endpoint.
// Configure MOLLIE_API_KEY in the server environment; never commit the key to Git.
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed.']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw ?: '', true);
$amountInput = isset($data['amount']) ? str_replace(',', '.', trim((string)$data['amount'])) : '';
$method = isset($data['method']) ? strtolower(trim((string)$data['method'])) : '';

if (!preg_match('/^\d+(?:\.\d{1,2})?$/', $amountInput)) {
    http_response_code(400);
    echo json_encode(['error' => 'Voer een geldig bedrag in.']);
    exit;
}

$amount = (float)$amountInput;
if ($amount < 2.00) {
    http_response_code(400);
    echo json_encode(['error' => 'De minimale bijdrage is €2,00.']);
    exit;
}
if ($amount > 10000.00) {
    http_response_code(400);
    echo json_encode(['error' => 'Het maximale bedrag is €10.000,00.']);
    exit;
}

$allowedMethods = ['ideal', 'bancontact'];
if (!in_array($method, $allowedMethods, true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Kies iDEAL of Bancontact.']);
    exit;
}

$apiKey = getenv('MOLLIE_API_KEY');
if (!$apiKey) {
    http_response_code(500);
    echo json_encode(['error' => 'Mollie is nog niet geconfigureerd op de server.']);
    exit;
}

$amountFormatted = number_format($amount, 2, '.', '');
$baseUrl = 'https://justpetrov.com/donate/';
$payload = [
    'amount' => ['currency' => 'EUR', 'value' => $amountFormatted],
    'description' => 'Vrijwillige bijdrage aan JustPetrov',
    'method' => $method,
    'redirectUrl' => $baseUrl . '?status=success',
    'cancelUrl' => $baseUrl . '?status=cancelled',
    'webhookUrl' => $baseUrl . 'webhook.php'
];

$ch = curl_init('https://api.mollie.com/v2/payments');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json',
        'Accept: application/json'
    ],
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_TIMEOUT => 20
]);
$response = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($response === false || $curlError) {
    http_response_code(502);
    echo json_encode(['error' => 'Mollie kon niet worden bereikt.']);
    exit;
}

$result = json_decode($response, true);
$checkout = isset($result['_links']['checkout']['href']) ? $result['_links']['checkout']['href'] : null;
if ($status < 200 || $status >= 300 || !$checkout) {
    http_response_code(502);
    echo json_encode(['error' => 'Mollie kon de betaling niet aanmaken.']);
    exit;
}

echo json_encode(['checkoutUrl' => $checkout]);
