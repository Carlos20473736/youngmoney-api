<?php
header('Content-Type: application/json' );
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200 );
    exit;
}

require_once __DIR__ . '/../database.php';

try {
    $conn = getDbConnection();
    
    // Buscar top 100 usuários por pontos
    $stmt = $conn->query("SELECT id, name, points FROM users ORDER BY points DESC LIMIT 100");
    $ranking = [];
    $position = 1;
    
    while ($row = $stmt->fetch_assoc()) {
        $ranking[] = [
            'position' => $position++,
            'user_id' => (int)$row['id'],
            'name' => $row['name'],
            'points' => (int)$row['points']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'ranking' => $ranking
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500 );
    echo json_encode(['success' => false, 'error' => 'Erro interno: ' . $e->getMessage()]);
}
?>
