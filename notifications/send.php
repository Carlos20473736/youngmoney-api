<?php
/**
 * Endpoint para enviar notificação para um usuário
 * Usado pelo painel administrativo
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../includes/db.php';

try {
    // Obter dados da requisição
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Dados inválidos']);
        exit;
    }
    
    // Validar campos obrigatórios
    $userId = $input['user_id'] ?? null;
    $title = $input['title'] ?? 'YoungMoney';
    $message = $input['message'] ?? null;
    
    if (!$userId || !$message) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'user_id e message são obrigatórios']);
        exit;
    }
    
    // Inserir notificação no banco
    $conn = getDbConnection();
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, is_read, created_at) VALUES (?, ?, ?, 0, NOW())");
    $stmt->bind_param("iss", $userId, $title, $message);
    $stmt->execute();
    $notificationId = $stmt->insert_id;
    $stmt->close();
    $conn->close();
    
    echo json_encode([
        'success' => true,
        'data' => [
            'notification_id' => $notificationId,
            'message' => 'Notificação enviada com sucesso'
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro ao enviar notificação: ' . $e->getMessage()]);
}
?>
