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
    
    // Buscar todos os usuários
    $stmt = $conn->query("SELECT id, name, email, profile_picture, points, created_at FROM users ORDER BY created_at DESC");
    $users = [];
    
    while ($row = $stmt->fetch_assoc()) {
        $users[] = [
            'id' => (int)$row['id'],
            'name' => $row['name'] ?? 'Sem nome',
            'email' => $row['email'] ?? '',
            'profile_picture' => $row['profile_picture'] ?? '',
            'points' => (int)$row['points'],
            'created_at' => $row['created_at']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'users' => $users,
            'total' => count($users)
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro interno: ' . $e->getMessage()]);
}
?>
