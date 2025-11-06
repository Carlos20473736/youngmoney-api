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
    
    if (!isset($input['invite_code'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Código de convite não fornecido']);
        exit;
    }
    
    $inviteCode = $input['invite_code'];
    
    // Extrair ID do usuário do código (formato: YM000001)
    if (!preg_match('/^YM(\d+)$/', $inviteCode, $matches)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Código de convite inválido']);
        exit;
    }
    
    $inviterId = (int)$matches[1];
    
    $conn = getDbConnection();
    
    // Verificar se o convidador existe
    $stmt = $conn->prepare("SELECT id, name FROM users WHERE id = ?");
    $stmt->bind_param("i", $inviterId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Código de convite não encontrado']);
        exit;
    }
    
    $inviter = $result->fetch_assoc();
    
    echo json_encode([
        'success' => true,
        'data' => [
            'valid' => true,
            'inviter_name' => $inviter['name'],
            'bonus_points' => 100
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro interno: ' . $e->getMessage()]);
}
?>
