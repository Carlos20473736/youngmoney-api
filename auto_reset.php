<?php
/**
 * Endpoint de Reset Automático do Ranking Diário
 * YoungMoney API - Railway
 * 
 * Este endpoint reseta o ranking diário quando chamado pelo cron-job.org
 * Versão simplificada: Sempre reseta quando chamado (sem verificação de horário)
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Configurar timezone para São Paulo (GMT-3)
date_default_timezone_set('America/Sao_Paulo');

// Tratar requisições OPTIONS (CORS preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Permitir GET ou POST
if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Método não permitido. Use GET ou POST.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Validar token de segurança
$token = $_GET['token'] ?? $_POST['token'] ?? '';
$expectedToken = 'ym_auto_reset_2024_secure_xyz'; // Mesmo token que está no cron-job.org

if ($token !== $expectedToken) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Token de autenticação inválido'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Configurações do banco de dados
$host = getenv('DB_HOST') ?: 'localhost';
$port = getenv('DB_PORT') ?: '3306';
$dbname = getenv('DB_NAME') ?: 'youngmoney';
$username = getenv('DB_USER') ?: 'root';
$password = getenv('DB_PASS') ?: '';

try {
    // Conectar ao banco de dados
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    
    // Iniciar transação
    $pdo->beginTransaction();
    
    // Contar usuários com pontos diários antes do reset
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE daily_points > 0");
    $result = $stmt->fetch();
    $usersAffected = $result['total'] ?? 0;
    
    // Resetar pontos diários de todos os usuários
    $stmt = $pdo->exec("UPDATE users SET daily_points = 0");
    
    // Registrar log do reset (se a tabela existir)
    try {
        $stmt = $pdo->prepare("
            INSERT INTO ranking_reset_logs 
            (reset_type, triggered_by, users_affected, status, reset_time) 
            VALUES ('automatic', 'cron-job.org', ?, 'success', NOW())
        ");
        $stmt->execute([$usersAffected]);
    } catch (Exception $e) {
        // Tabela de log pode não existir, ignorar erro
    }
    
    // Atualizar última data de reset (se a tabela de configurações existir)
    try {
        $currentDate = date('Y-m-d');
        $stmt = $pdo->prepare("
            INSERT INTO system_settings (setting_key, setting_value) 
            VALUES ('last_reset_date', ?)
            ON DUPLICATE KEY UPDATE setting_value = ?
        ");
        $stmt->execute([$currentDate, $currentDate]);
    } catch (Exception $e) {
        // Tabela pode não existir, ignorar erro
    }
    
    // Commit da transação
    $pdo->commit();
    
    // Resposta de sucesso
    echo json_encode([
        'success' => true,
        'message' => 'Ranking diário resetado com sucesso!',
        'data' => [
            'users_affected' => $usersAffected,
            'reset_time' => date('Y-m-d H:i:s'),
            'reset_hour' => date('H:i'),
            'timezone' => 'America/Sao_Paulo (GMT-3)',
            'timestamp' => time()
        ]
    ], JSON_UNESCAPED_UNICODE);
    
} catch (PDOException $e) {
    // Rollback em caso de erro
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Erro no reset do ranking: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao resetar ranking',
        'details' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    // Rollback em caso de erro
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Erro no reset do ranking: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao resetar ranking',
        'details' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
