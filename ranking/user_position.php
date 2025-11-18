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
    
    // Buscar usuário com daily_points e total_points
    $stmt = $conn->prepare("SELECT id, name, daily_points, points as total_points FROM users WHERE token = ?");
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
    $dailyPoints = $user['daily_points'];
    $totalPoints = $user['total_points'];
    
    // Calcular posição do usuário baseada em DAILY_POINTS (ranking diário)
    $stmt = $conn->prepare("SELECT COUNT(*) as position FROM users WHERE daily_points > ?");
    $stmt->bind_param("i", $dailyPoints);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $position = $row['position'] + 1;
    
    echo json_encode([
        'success' => true,
        'data' => [
            'position' => $position,
            'daily_points' => (int)$dailyPoints,
            'points' => (int)$dailyPoints, // Para compatibilidade com versão antiga
            'total_points' => (int)$totalPoints,
            'name' => $user['name']
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro interno: ' . $e->getMessage()]);
}
?>
