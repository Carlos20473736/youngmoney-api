<?php
// Desabilitar exibição de erros PHP (evita HTML no output)
ini_set('display_errors', '0');
error_reporting(E_ALL);

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
require_once __DIR__ . '/../middleware/auto_reset.php';

try {
    // Validar XReq token
    validateXReq();

    $conn = getDbConnection();
    
    // Verificar e fazer reset automático se necessário
    checkAndResetRanking($conn);
    
    // Buscar ranking ordenado por daily_points (pontos diários)
    // Mesmo sistema usado pelo painel admin
    $stmt = $conn->prepare("
        SELECT 
            id,
            name,
            email,
            profile_picture,
            daily_points,
            points as total_points
        FROM users 
        WHERE daily_points > 0
        ORDER BY daily_points DESC 
        LIMIT 100
    ");
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $ranking = [];
    $position = 1;
    
    while ($row = $result->fetch_assoc()) {
        $ranking[] = [
            'position' => $position++,
            'userId' => (string)$row['id'],
            'userName' => $row['name'] ?? 'Usuário',
            'userEmail' => $row['email'] ?? '',
            'userImageUrl' => $row['profile_picture'] ?? '',
            'profileImageUrl' => $row['profile_picture'] ?? '',
            'dailyPoints' => (int)$row['daily_points'],
            'totalPoints' => (int)$row['total_points'],
            'level' => 1
        ];
    }
    
    $stmt->close();
    
    // Obter timestamp do servidor (GMT-3 - Brasília)
    date_default_timezone_set('America/Sao_Paulo');
    $serverTime = date('Y-m-d H:i:s');
    $serverTimestamp = time();
    
    echo json_encode([
        'success' => true,
        'data' => [
            'rankings' => $ranking,
            'server_time' => $serverTime,
            'server_timestamp' => $serverTimestamp
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro interno: ' . $e->getMessage()]);
}
?>
