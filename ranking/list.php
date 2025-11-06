<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../database.php';

try {
    $conn = getDbConnection();
    
    // Buscar top 100 usuários por pontos
    $stmt = $conn->query("SELECT id, name, email, profile_picture, points FROM users WHERE name IS NOT NULL ORDER BY points DESC LIMIT 100");
    $ranking = [];
    $position = 1;
    
    while ($row = $stmt->fetch_assoc()) {
        $ranking[] = [
            'position' => $position++,
            'userId' => (string)$row['id'],
            'userName' => $row['name'] ?? 'Usuário',
            'userEmail' => $row['email'] ?? '',
            'userImageUrl' => $row['profile_picture'] ?? '',
            'profileImageUrl' => $row['profile_picture'] ?? '',
            'dailyPoints' => (int)$row['points'],
            'totalPoints' => (int)$row['points'],
            'level' => 1
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'ranking' => $ranking
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro interno: ' . $e->getMessage()]);
}
?>
