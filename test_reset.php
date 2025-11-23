<?php
/**
 * Endpoint de Teste para Diagnóstico do Reset
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Token de segurança
$CRON_TOKEN = 'ym_cron_secure_2024_abc123xyz';

// Verificar token
$token = $_GET['token'] ?? '';
if ($token !== $CRON_TOKEN) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Unauthorized'
    ]);
    exit;
}

// Incluir configuração do banco
require_once __DIR__ . '/database.php';

// Obter conexão
$conn = getDbConnection();

try {
    date_default_timezone_set('America/Sao_Paulo');
    
    $response = [
        'success' => true,
        'timestamp' => date('Y-m-d H:i:s'),
        'diagnostics' => []
    ];
    
    // 1. Verificar estrutura da tabela users
    $result = $conn->query("DESCRIBE users");
    $columns = [];
    while ($row = $result->fetch_assoc()) {
        $columns[] = $row['Field'];
    }
    $response['diagnostics']['users_table_columns'] = $columns;
    $response['diagnostics']['has_daily_points_column'] = in_array('daily_points', $columns);
    
    // 2. Contar usuários com pontos
    $result = $conn->query("SELECT COUNT(*) as total FROM users WHERE daily_points > 0");
    $row = $result->fetch_assoc();
    $response['diagnostics']['users_with_points'] = (int)$row['total'];
    
    // 3. Contar total de usuários
    $result = $conn->query("SELECT COUNT(*) as total FROM users");
    $row = $result->fetch_assoc();
    $response['diagnostics']['total_users'] = (int)$row['total'];
    
    // 4. Mostrar alguns usuários com pontos (primeiros 5)
    $result = $conn->query("SELECT id, username, daily_points FROM users WHERE daily_points > 0 LIMIT 5");
    $users_sample = [];
    while ($row = $result->fetch_assoc()) {
        $users_sample[] = [
            'id' => $row['id'],
            'username' => $row['username'],
            'daily_points' => (int)$row['daily_points']
        ];
    }
    $response['diagnostics']['users_sample'] = $users_sample;
    
    // 5. Verificar configurações do sistema
    $result = $conn->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('reset_time', 'last_reset_date')");
    $settings = [];
    while ($row = $result->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    $response['diagnostics']['system_settings'] = $settings;
    
    // 6. Se force_reset=1, executar reset manual
    if (isset($_GET['force_reset']) && $_GET['force_reset'] == '1') {
        // Contar antes
        $result = $conn->query("SELECT COUNT(*) as total FROM users WHERE daily_points > 0");
        $before = $result->fetch_assoc();
        
        // Executar reset
        $conn->query("UPDATE users SET daily_points = 0");
        $affected = $conn->affected_rows;
        
        // Contar depois
        $result = $conn->query("SELECT COUNT(*) as total FROM users WHERE daily_points > 0");
        $after = $result->fetch_assoc();
        
        // Atualizar last_reset_date
        $current_date = date('Y-m-d');
        $stmt = $conn->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = 'last_reset_date'");
        $stmt->bind_param('s', $current_date);
        $stmt->execute();
        
        if ($stmt->affected_rows == 0) {
            $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('last_reset_date', ?)");
            $stmt->bind_param('s', $current_date);
            $stmt->execute();
        }
        
        $response['force_reset_executed'] = true;
        $response['reset_details'] = [
            'users_with_points_before' => (int)$before['total'],
            'affected_rows' => $affected,
            'users_with_points_after' => (int)$after['total'],
            'last_reset_date_updated' => $current_date
        ];
    }
    
    echo json_encode($response, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
