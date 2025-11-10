<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Req');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../xreq/validate.php';

// Taxa de conversão: 10.000 pontos = R$ 1,00
define('POINTS_PER_REAL', 10000);

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
    
    // LER JSON DIRETO - SEM CRIPTOGRAFIA
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Log detalhado para debug
    error_log("[WITHDRAW_PLAIN] Raw input: " . file_get_contents('php://input'));
    error_log("[WITHDRAW_PLAIN] Decoded input: " . json_encode($input));
    error_log("[WITHDRAW_PLAIN] Input keys: " . json_encode(array_keys($input ?: [])));
    
    if (!isset($input['amount']) || !isset($input['pixKeyType']) || !isset($input['pixKey'])) {
        $debug = [
            'has_amount' => isset($input['amount']),
            'has_pixKeyType' => isset($input['pixKeyType']),
            'has_pixKey' => isset($input['pixKey']),
            'input_keys' => array_keys($input ?: []),
            'input_data' => $input
        ];
        error_log("[WITHDRAW_PLAIN] Dados incompletos: " . json_encode($debug));
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Dados incompletos',
            'debug' => $debug
        ]);
        exit;
    }
    
    $amountBrl = floatval($input['amount']);
    $pixKeyType = $input['pixKeyType'];
    $pixKey = $input['pixKey'];
    
    // Validar valor mínimo (R$ 1,00)
    if ($amountBrl < 1) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Valor mínimo: R$ 1,00']);
        exit;
    }
    
    // Calcular pontos necessários (R$ 1,00 = 10.000 pontos)
    $pointsRequired = intval($amountBrl * POINTS_PER_REAL);
    
    $conn = getDbConnection();
    
    // Buscar usuário e saldo
    $stmt = $conn->prepare("SELECT id, points FROM users WHERE token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Usuário não encontrado']);
        exit;
    }
    
    $user = $result->fetch_assoc();
    $userId = $user['id'];
    $currentPoints = intval($user['points']);
    
    // Verificar saldo
    if ($currentPoints < $pointsRequired) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Saldo insuficiente',
            'current_points' => $currentPoints,
            'required_points' => $pointsRequired
        ]);
        exit;
    }
    
    // Iniciar transação
    $conn->begin_transaction();
    
    try {
        // Debitar pontos
        $newPoints = $currentPoints - $pointsRequired;
        $stmt = $conn->prepare("UPDATE users SET points = ? WHERE id = ?");
        $stmt->bind_param("ii", $newPoints, $userId);
        $stmt->execute();
        
        // Registrar saque
        $stmt = $conn->prepare("
            INSERT INTO withdrawals (user_id, amount, pix_type, pix_key, status, created_at)
            VALUES (?, ?, ?, ?, 'pending', NOW())
        ");
        $stmt->bind_param("idss", $userId, $amountBrl, $pixKeyType, $pixKey);
        $stmt->execute();
        $withdrawalId = $conn->insert_id;
        
        // Registrar transação de pontos
        $description = "Saque de R$ " . number_format($amountBrl, 2, ',', '.') . " (ID: $withdrawalId)";
        $stmt = $conn->prepare("
            INSERT INTO point_transactions (user_id, points, type, description, created_at)
            VALUES (?, ?, 'debit', ?, NOW())
        ");
        $pointsNegative = -$pointsRequired;
        $stmt->bind_param("iis", $userId, $pointsNegative, $description);
        $stmt->execute();
        
        // Commit da transação
        $conn->commit();
        
        error_log("[WITHDRAW_PLAIN] Success: User $userId withdrew R$ $amountBrl ($pointsRequired points)");
        
        echo json_encode([
            'success' => true,
            'message' => 'Saque solicitado com sucesso',
            'withdrawal_id' => $withdrawalId,
            'new_balance' => $newPoints
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        error_log("[WITHDRAW_PLAIN] Transaction error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Erro ao processar saque']);
    }
    
} catch (Exception $e) {
    error_log("[WITHDRAW_PLAIN] Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro interno do servidor']);
}
?>
