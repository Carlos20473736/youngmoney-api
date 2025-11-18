<?php
require_once __DIR__ . '/../cors.php';
header('Content-Type: application/json');

require_once __DIR__ . '/../../database.php';

try {
    $conn = getDbConnection();
    
    // Resetar pontos de todos os usuários
    $stmt = $conn->prepare("UPDATE users SET points = 0");
    $stmt->execute();
    
    echo json_encode(['success' => true, 'data' => ['affected_rows' => $stmt->affected_rows]]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
