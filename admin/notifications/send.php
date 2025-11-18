<?php
require_once __DIR__ . '/../cors.php';
header('Content-Type: application/json');

require_once __DIR__ . '/../../database.php';

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $userId = $input['user_id'] ?? null;
    $title = $input['title'] ?? '';
    $message = $input['message'] ?? '';
    
    if (empty($title) || empty($message)) {
        throw new Exception('Título e mensagem são obrigatórios');
    }
    
    $conn = getDbConnection();
    
    if ($userId === null) {
        // Enviar para todos os usuários
        $stmt = $conn->prepare("SELECT id FROM users");
        $stmt->execute();
        $result = $stmt->get_result();
        
        $insertStmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, created_at) VALUES (?, ?, ?, NOW())");
        
        $count = 0;
        while ($row = $result->fetch_assoc()) {
            $insertStmt->bind_param('iss', $row['id'], $title, $message);
            $insertStmt->execute();
            $count++;
        }
        
        echo json_encode(['success' => true, 'data' => ['count' => $count]]);
    } else {
        // Enviar para um usuário específico
        $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->bind_param('iss', $userId, $title, $message);
        $stmt->execute();
        
        echo json_encode(['success' => true, 'data' => ['id' => $conn->insert_id]]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
