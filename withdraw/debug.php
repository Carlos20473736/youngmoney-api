<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Req');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Capturar TUDO que está chegando
$rawInput = file_get_contents('php://input');
$decodedInput = json_decode($rawInput, true);
$headers = getallheaders();

$debug = [
    'success' => true,
    'debug' => [
        'raw_input' => $rawInput,
        'decoded_input' => $decodedInput,
        'headers' => $headers,
        'method' => $_SERVER['REQUEST_METHOD'],
        'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'not set',
        'checks' => [
            'has_amount' => isset($decodedInput['amount']),
            'has_pixKeyType' => isset($decodedInput['pixKeyType']),
            'has_pixKey' => isset($decodedInput['pixKey']),
            'has_pix_type' => isset($decodedInput['pix_type']),
            'has_pix_key' => isset($decodedInput['pix_key']),
        ]
    ]
];

echo json_encode($debug, JSON_PRETTY_PRINT);
