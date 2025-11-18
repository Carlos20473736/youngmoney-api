<?php
require_once __DIR__ . '/cors.php';
header('Content-Type: application/json');

require_once __DIR__ . '/../database.php';

try {
    $conn = getDbConnection();
    
    // Buscar ranking (top 100)
    $stmt = $conn->prepare("
        SELECT 
            id as user_id,
            name,
            points
        FROM users
        WHERE points > 0
        ORDER BY points DESC
        LIMIT 100
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    
    $ranking = [];
    $position = 1;
    while ($row = $result->fetch_assoc()) {
        $ranking[] = [
            'position' => $position++,
            'user_id' => (int)$row['user_id'],
            'name' => $row['name'],
            'points' => (int)$row['points']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $ranking
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
