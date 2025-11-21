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

try {
    // Endpoint público - não requer XReq validation

    $conn = getDbConnection();
    
    // Buscar últimos 100 saques aprovados/processados com informações do usuário
    $stmt = $conn->prepare("
        SELECT 
            u.email,
            u.profile_picture,
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
        // Mascarar o email: mostrar primeiros 2 caracteres + *** + domínio
        $email = $row['email'];
        $emailParts = explode('@', $email);
        $maskedEmail = substr($emailParts[0], 0, 2) . '***@' . $emailParts[1];
        
        $withdrawals[] = [
            'email' => $maskedEmail,
            'photo_url' => $row['profile_picture'],
            'amount' => floatval($row['amount']),
            'created_at' => $row['created_at']
        ];
    }
    
    $stmt->close();
    
    // Retornar JSON simples (endpoint público)
    echo json_encode([
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
