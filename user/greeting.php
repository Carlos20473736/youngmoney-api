<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

// Definir timezone para São Paulo (Brasil)
date_default_timezone_set('America/Sao_Paulo');

// Obter hora atual
$currentHour = (int)date('H');

// Determinar saudação baseada no horário
if ($currentHour >= 6 && $currentHour < 12) {
    $greeting = "Bom dia";
} elseif ($currentHour >= 12 && $currentHour < 18) {
    $greeting = "Boa tarde";
} else {
    $greeting = "Boa noite";
}

// Retornar resposta
echo json_encode([
    'success' => true,
    'data' => [
        'greeting' => $greeting,
        'hour' => $currentHour,
        'datetime' => date('Y-m-d H:i:s')
    ]
]);
?>
