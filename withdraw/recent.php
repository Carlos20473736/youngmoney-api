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
require_once __DIR__ . '/../includes/SecureMiddleware.php';

try {
    // Endpoint público - não requer XReq validation

    $conn = getDbConnection();
    
    // Buscar últimos 100 saques aprovados/processados com informações do usuário
    $stmt = $conn->prepare("
        SELECT 
            u.name,
            u.photo_url,
            w.amount,
            w.created_at
        FROM withdrawals w
        INNER JOIN users u ON w.user_id = u.id
        WHERE w.status IN ('approved', 'processed', 'completed')
        ORDER BY w.created_at DESC
        LIMIT 100
    ");
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $withdrawals = [];
    while ($row = $result->fetch_assoc()) {
        $withdrawals[] = [
            'name' => $row['name'],
            'photo_url' => $row['photo_url'],
            'amount' => floatval($row['amount']),
            'created_at' => $row['created_at']
        ];
    }
    
    $stmt->close();
    
    // Retornar com SecureMiddleware (criptografado)
    SecureMiddleware::sendResponse([
        'success' => true,
        'data' => [
            'withdrawals' => $withdrawals
        ]
    ]);
    
} catch (Exception $e) {
    error_log("[RECENT_WITHDRAWALS] Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro interno: ' . $e->getMessage()]);
}
?>
