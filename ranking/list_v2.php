<?php
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
require_once __DIR__ . '/../includes/SecureMiddleware.php';

try {
    // Validar XReq token
    validateXReq();

    $conn = getDbConnection();
    
    // Obter tipo de período (daily, weekly, monthly)
    $periodType = isset($_GET['period']) ? $_GET['period'] : 'daily';
    
    if (!in_array($periodType, ['daily', 'weekly', 'monthly'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Tipo de período inválido']);
        exit;
    }
    
    // Obter período ativo
    $stmt = $conn->prepare("CALL get_active_period(?)");
    $stmt->bind_param("s", $periodType);
    $stmt->execute();
    $result = $stmt->get_result();
    $periodRow = $result->fetch_assoc();
    $periodId = $periodRow['period_id'];
    $stmt->close();
    
    // Buscar informações do período
    $stmt = $conn->prepare("SELECT start_date, end_date FROM ranking_periods WHERE id = ?");
    $stmt->bind_param("i", $periodId);
    $stmt->execute();
    $result = $stmt->get_result();
    $periodInfo = $result->fetch_assoc();
    $stmt->close();
    
    // Buscar top 100 usuários do período
    $stmt = $conn->prepare("
        SELECT 
            u.id,
            u.name,
            u.email,
            u.profile_picture,
            rp.points as period_points,
            u.points as total_points
        FROM ranking_points rp
        INNER JOIN users u ON rp.user_id = u.id
        WHERE rp.period_id = ?
        ORDER BY rp.points DESC
        LIMIT 100
    ");
    $stmt->bind_param("i", $periodId);
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
            'dailyPoints' => (int)$row['period_points'],  // Pontos do período
            'totalPoints' => (int)$row['total_points'],   // Pontos totais
            'level' => 1
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'rankings' => $ranking,
            'period' => [
                'type' => $periodType,
                'start_date' => $periodInfo['start_date'],
                'end_date' => $periodInfo['end_date'],
                'period_id' => $periodId
            ]
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro interno: ' . $e->getMessage()]);
}
?>
