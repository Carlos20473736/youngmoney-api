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
require_once __DIR__ . '/../includes/SecureMiddleware.php';

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
    
    // Processar requisição (descriptografa automaticamente se necessário)
    $input = SecureMiddleware::processRequest();
    
    if (!isset($input['points'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Pontos não fornecidos']);
        exit;
    }
    
    $points = (int)$input['points'];
    
    if ($points <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Pontos devem ser maior que zero']);
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
    $newPoints = $currentPoints + $points;
    
    // Iniciar transação
    $conn->begin_transaction();
    
    try {
        // 1. Atualizar pontos totais do usuário
        $stmt = $conn->prepare("UPDATE users SET points = ? WHERE id = ?");
        $stmt->bind_param("ii", $newPoints, $userId);
        $stmt->execute();
        
        // 2. Salvar no histórico
        $description = isset($input['description']) ? $input['description'] : (isset($input['activity']) ? $input['activity'] : 'Pontos adicionados');
        $stmt = $conn->prepare("INSERT INTO points_history (user_id, points, description, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->bind_param("iis", $userId, $points, $description);
        $stmt->execute();
        
        // 3. Obter período ativo (diário por padrão)
        $periodType = isset($input['period_type']) ? $input['period_type'] : 'daily';
        
        // Chamar stored procedure e pegar resultado
        if ($conn->multi_query("CALL get_active_period('$periodType')")) {
            do {
                if ($result = $conn->store_result()) {
                    $periodRow = $result->fetch_assoc();
                    $periodId = $periodRow['period_id'];
                    $result->free();
                }
            } while ($conn->more_results() && $conn->next_result());
        }
        
        // 4. Atualizar pontos do ranking do período
        // Usar INSERT ... ON DUPLICATE KEY UPDATE para criar ou atualizar
        $stmt = $conn->prepare("
            INSERT INTO ranking_points (user_id, period_id, points)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE points = points + VALUES(points)
        ");
        $stmt->bind_param("iii", $userId, $periodId, $points);
        $stmt->execute();
        
        // Buscar pontos do período
        $stmt = $conn->prepare("SELECT points FROM ranking_points WHERE user_id = ? AND period_id = ?");
        $stmt->bind_param("ii", $userId, $periodId);
        $stmt->execute();
        $result = $stmt->get_result();
        $periodPoints = 0;
        if ($row = $result->fetch_assoc()) {
            $periodPoints = (int)$row['points'];
        }
        
        // Commit da transação
        $conn->commit();
        
        // Enviar resposta criptografada
        SecureMiddleware::sendSuccessAuto([
            'points_added' => $points,
            'daily_points' => $periodPoints,  // Pontos do período atual
            'total_points' => $newPoints      // Pontos totais acumulados
        ], true);
        
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro interno: ' . $e->getMessage()]);
}
?>
