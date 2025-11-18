<?php
require_once __DIR__ . '/../admin/cors.php';
require_once __DIR__ . '/../database.php';

header('Content-Type: application/json');

try {
    $conn = getDbConnection();
    
    // Buscar ranking ordenado por daily_points (pontos diários)
    $stmt = $conn->prepare("
        SELECT id, name, daily_points as points
        FROM users 
        WHERE daily_points > 0
        ORDER BY daily_points DESC 
        LIMIT 100
    ");
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $ranking = [];
    while ($row = $result->fetch_assoc()) {
        $ranking[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'data' => $ranking
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

$conn->close();
?>
