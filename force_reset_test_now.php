<?php
/**
 * ENDPOINT DE TESTE - Força reset imediato
 * ATENÇÃO: Usar apenas para testes!
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// Token de segurança
$token = $_GET['token'] ?? '';
if ($token !== 'ym_force_reset_test_2024') {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Token inválido'
    ]);
    exit;
}

try {
    // Conectar ao banco
    require_once __DIR__ . '/database.php';
    
    $pdo = getConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Buscar quantos usuários têm pontos antes do reset
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE daily_points > 0");
    $beforeCount = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $stmt = $pdo->query("SELECT SUM(daily_points) as total_points FROM users");
    $beforePoints = $stmt->fetch(PDO::FETCH_ASSOC)['total_points'];
    
    // FORÇAR RESET - Zerar todos os pontos
    $stmt = $pdo->prepare("UPDATE users SET daily_points = 0");
    $stmt->execute();
    $affected = $stmt->rowCount();
    
    // Buscar quantos usuários têm pontos depois do reset
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE daily_points > 0");
    $afterCount = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $stmt = $pdo->query("SELECT SUM(daily_points) as total_points FROM users");
    $afterPoints = $stmt->fetch(PDO::FETCH_ASSOC)['total_points'];
    
    // Atualizar data do último reset
    $current_date = date('Y-m-d');
    $stmt = $pdo->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = 'last_reset_date'");
    $stmt->execute([$current_date]);
    
    if ($stmt->rowCount() == 0) {
        $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('last_reset_date', ?)");
        $stmt->execute([$current_date]);
    }
    
    // Registrar no log
    $stmt = $pdo->prepare("
        INSERT INTO admin_logs (admin_id, action, details, ip_address, created_at) 
        VALUES (0, 'ranking_reset_test', ?, ?, NOW())
    ");
    $details = "Reset forçado via teste. Usuários afetados: $affected. Pontos antes: $beforePoints, depois: $afterPoints";
    $stmt->execute([$details, $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
    
    echo json_encode([
        'success' => true,
        'message' => 'Reset executado com sucesso!',
        'timestamp' => date('Y-m-d H:i:s'),
        'before' => [
            'users_with_points' => (int)$beforeCount,
            'total_points' => (int)$beforePoints
        ],
        'after' => [
            'users_with_points' => (int)$afterCount,
            'total_points' => (int)$afterPoints
        ],
        'affected_users' => $affected,
        'reset_date' => $current_date
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao executar reset',
        'details' => $e->getMessage()
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
