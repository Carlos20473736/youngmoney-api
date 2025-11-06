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
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['amount'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Valor não fornecido']);
        exit;
    }
    
    $amount = (int)$input['amount'];
    
    if ($amount <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Valor deve ser maior que zero']);
        exit;
    }
    
    $conn = getDbConnection();
    
    // Buscar usuário
    $stmt = $conn->prepare("SELECT id, points FROM users WHERE token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Token inválido']);
        exit;
    }
    
    $user = $result->fetch_assoc();
    $userId = $user['id'];
    $currentPoints = $user['points'];
    
    if ($currentPoints < $amount) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Saldo insuficiente']);
        exit;
    }
    
    // Por enquanto, apenas retornar sucesso (criar tabela withdrawals depois)
    echo json_encode([
        'success' => true,
        'data' => [
            'withdrawal_id' => time(),
            'amount' => $amount,
            'status' => 'pending',
            'message' => 'Solicitação de saque enviada com sucesso'
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro interno: ' . $e->getMessage()]);
}
?>
