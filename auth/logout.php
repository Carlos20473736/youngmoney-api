<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../database.php';

try {
    $headers = getallheaders();
    $token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : null;
    
    if (!$token) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Token não fornecido']);
        exit;
    }
    
    $conn = getDbConnection();
    
    // Limpar token do usuário
    $stmt = $conn->prepare("UPDATE users SET token = NULL WHERE token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    
    echo json_encode(['success' => true, 'message' => 'Logout realizado com sucesso']);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro interno: ' . $e->getMessage()]);
}
?>
