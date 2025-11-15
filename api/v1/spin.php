<?php
/**
 * Spin Wheel API
 * Backend decide valor aleatório e valida giros diários
 * 
 * Endpoint: POST /api/v1/spin.php
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Apenas POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Método não permitido']);
    exit;
}

// Incluir configuração do banco de dados
require_once __DIR__ . '/../../config/database.php';

// Função para validar token (simplificada - ajuste conforme sua autenticação)
function getUserFromToken() {
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? '';
    
    if (empty($authHeader) || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
        return null;
    }
    
    $token = $matches[1];
    
    // Buscar usuário pelo token (ajuste conforme sua tabela)
    global $pdo;
    $stmt = $pdo->prepare("SELECT id, name, email FROM users WHERE auth_token = ? AND token_expires_at > NOW()");
    $stmt->execute([$token]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Valores possíveis da roleta (multiplicados por 10)
$prizeValues = [100, 250, 500, 750, 1000, 1500, 2000, 5000];
$maxDailySpins = 10;

try {
    // Validar autenticação
    $user = getUserFromToken();
    if (!$user) {
        http_response_code(401);
        echo json_encode([
            'status' => 'error',
            'message' => 'Não autenticado'
        ]);
        exit;
    }
    
    $userId = $user['id'];
    
    // Obter data atual no servidor (timezone configurável)
    date_default_timezone_set('America/Sao_Paulo'); // GMT-3 (Brasília)
    $currentDate = date('Y-m-d');
    $currentDateTime = date('Y-m-d H:i:s');
    
    // Verificar giros do usuário hoje
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as spins_today 
        FROM spin_history 
        WHERE user_id = ? AND DATE(created_at) = ?
    ");
    $stmt->execute([$userId, $currentDate]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $spinsToday = (int)$result['spins_today'];
    
    // Verificar se ainda tem giros disponíveis
    if ($spinsToday >= $maxDailySpins) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Você já usou todos os giros de hoje. Volte amanhã!',
            'data' => [
                'spins_remaining' => 0,
                'spins_today' => $spinsToday,
                'max_daily_spins' => $maxDailySpins,
                'next_reset' => date('Y-m-d 00:00:00', strtotime('+1 day')),
                'server_time' => $currentDateTime
            ]
        ]);
        exit;
    }
    
    // Sortear prêmio aleatório
    $prizeIndex = array_rand($prizeValues);
    $prizeValue = $prizeValues[$prizeIndex];
    
    // Iniciar transação
    $pdo->beginTransaction();
    
    try {
        // 1. Registrar giro no histórico
        $stmt = $pdo->prepare("
            INSERT INTO spin_history (user_id, prize_value, prize_index, created_at)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$userId, $prizeValue, $prizeIndex, $currentDateTime]);
        
        // 2. Adicionar pontos ao saldo do usuário
        $stmt = $pdo->prepare("
            UPDATE users 
            SET points = points + ?,
                updated_at = ?
            WHERE id = ?
        ");
        $stmt->execute([$prizeValue, $currentDateTime, $userId]);
        
        // 3. Registrar no histórico de pontos
        $stmt = $pdo->prepare("
            INSERT INTO points_history (user_id, points, activity, description, created_at)
            VALUES (?, ?, 'spin_wheel', ?, ?)
        ");
        $description = "Roleta da Sorte - Ganhou {$prizeValue} pontos";
        $stmt->execute([$userId, $prizeValue, $description, $currentDateTime]);
        
        // Commit da transação
        $pdo->commit();
        
        // Calcular giros restantes
        $spinsRemaining = $maxDailySpins - ($spinsToday + 1);
        
        // Obter saldo atualizado
        $stmt = $pdo->prepare("SELECT points FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $userData = $stmt->fetch(PDO::FETCH_ASSOC);
        $newBalance = (int)$userData['points'];
        
        // Resposta de sucesso
        echo json_encode([
            'status' => 'success',
            'message' => "Parabéns! Você ganhou {$prizeValue} pontos!",
            'data' => [
                'prize_value' => $prizeValue,
                'prize_index' => $prizeIndex,
                'spins_remaining' => $spinsRemaining,
                'new_balance' => $newBalance,
                'spins_today' => $spinsToday + 1,
                'max_daily_spins' => $maxDailySpins,
                'server_time' => $currentDateTime,
                'server_date' => $currentDate
            ]
        ]);
        
    } catch (Exception $e) {
        // Rollback em caso de erro
        $pdo->rollBack();
        throw $e;
    }
    
} catch (Exception $e) {
    error_log("Spin execute error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Erro ao processar giro: ' . $e->getMessage()
    ]);
}
?>
