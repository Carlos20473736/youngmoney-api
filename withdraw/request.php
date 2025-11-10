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
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Log para debug
    error_log("[WITHDRAW] Input recebido: " . json_encode($input));
    
    if (!isset($input['amount']) || !isset($input['pixKeyType']) || !isset($input['pixKey'])) {
        error_log("[WITHDRAW] Dados incompletos - amount: " . (isset($input['amount']) ? 'OK' : 'FALTA') . ", pixKeyType: " . (isset($input['pixKeyType']) ? 'OK' : 'FALTA') . ", pixKey: " . (isset($input['pixKey']) ? 'OK' : 'FALTA'));
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Dados incompletos']);
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
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Token inválido']);
        exit;
    }
    
    $user = $result->fetch_assoc();
    $userId = $user['id'];
    $currentPoints = $user['points'];
    $stmt->close();
    
    // Verificar se tem pontos suficientes
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
        // 1. Debitar pontos do usuário
        $stmt = $conn->prepare("UPDATE users SET points = points - ? WHERE id = ?");
        $stmt->bind_param("ii", $pointsRequired, $userId);
        $stmt->execute();
        $stmt->close();
        
        // 2. Registrar no histórico de pontos
        $stmt = $conn->prepare("INSERT INTO points_history (user_id, points, description, type) VALUES (?, ?, ?, 'debit')");
        $description = "Saque de R$ " . number_format($amountBrl, 2, ',', '.') . " via PIX";
        $negativePoints = -$pointsRequired;
        $stmt->bind_param("iis", $userId, $negativePoints, $description);
        $stmt->execute();
        $stmt->close();
        
        // 3. Criar registro de saque
        $stmt = $conn->prepare("INSERT INTO withdrawals (user_id, pix_key, pix_key_type, amount, status, created_at) VALUES (?, ?, ?, ?, 'pending', NOW())");
        $stmt->bind_param("issd", $userId, $pixKey, $pixKeyType, $amountBrl);
        $stmt->execute();
        $withdrawalId = $stmt->insert_id;
        $stmt->close();
        
        // Commit da transação
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'data' => [
                'withdrawal_id' => $withdrawalId,
                'amount' => $amountBrl,
                'points_debited' => $pointsRequired,
                'remaining_points' => $currentPoints - $pointsRequired,
                'status' => 'pending',
                'message' => 'Solicitação de saque enviada com sucesso'
            ]
        ]);
        
    } catch (Exception $e) {
        // Rollback em caso de erro
        $conn->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro interno: ' . $e->getMessage()]);
}
?>
