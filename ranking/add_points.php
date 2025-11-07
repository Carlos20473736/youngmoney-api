<?php
header('Content-Type: application/json' );
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Req');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200 );
    exit;
}

require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../xreq/validate.php';
require_once __DIR__ . '/../includes/DecryptMiddleware.php';

try {
    // Validar XReq token
    validateXReq();
    
    $headers = getallheaders();
    $token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : null;
    
    if (!$token) {
        http_response_code(401 );
        echo json_encode(['success' => false, 'error' => 'Token não fornecido']);
        exit;
    }
    
    // Processar requisição (descriptografa automaticamente se necessário)
    $input = DecryptMiddleware::processRequest();
    
    if (!isset($input['points'])) {
        http_response_code(400 );
        echo json_encode(['success' => false, 'error' => 'Pontos não fornecidos']);
        exit;
    }
    
    $points = (int)$input['points'];
    
    if ($points <= 0) {
        http_response_code(400 );
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
        http_response_code(401 );
        echo json_encode(['success' => false, 'error' => 'Token inválido']);
        exit;
    }
    
    $user = $result->fetch_assoc();
    $userId = $user['id'];
    $currentPoints = $user['points'];
    $newPoints = $currentPoints + $points;
    
    // Atualizar pontos
    $stmt = $conn->prepare("UPDATE users SET points = ? WHERE id = ?");
    $stmt->bind_param("ii", $newPoints, $userId);
    $stmt->execute();
    
    // Salvar no histórico
    $description = isset($input['description']) ? $input['description'] : 'Pontos adicionados';
    $stmt = $conn->prepare("INSERT INTO points_history (user_id, points, description, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->bind_param("iis", $userId, $points, $description);
    $stmt->execute();
    
    // Enviar resposta criptografada
    DecryptMiddleware::sendSuccess([
        'points_added' => $points,
        'daily_points' => $points,
        'total_points' => $newPoints
    ], true);
    
} catch (Exception $e) {
    http_response_code(500 );
    echo json_encode(['success' => false, 'error' => 'Erro interno: ' . $e->getMessage()]);
}
?>
