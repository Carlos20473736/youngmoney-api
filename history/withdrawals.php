<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Req');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../xreq/validate.php';

try {
    // Validar XReq token
    validateXReq();

    $headers = getallheaders();
    $token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : null;
    
    if (!$token) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Token não fornecido']);
        exit;
    }
    
    $conn = getDbConnection();
    
    // Verificar se usuário existe
    $stmt = $conn->prepare("SELECT id FROM users WHERE token = ?");
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
    
    // Buscar histórico de saques
    $stmt = $conn->prepare("
        SELECT 
            id,
            amount,
            pix_key,
            status,
            created_at as date,
            updated_at
        FROM withdrawal_requests 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 100
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $history = [];
    while ($row = $result->fetch_assoc()) {
        $history[] = [
            'id' => (int)$row['id'],
            'amount' => (float)$row['amount'],
            'pix_key' => $row['pix_key'],
            'status' => $row['status'],
            'date' => $row['date'],
            'updated_at' => $row['updated_at']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'withdrawals' => $history
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro interno: ' . $e->getMessage()]);
}
?>
