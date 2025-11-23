<?php
/**
 * AUTO RESET - Endpoint Simplificado para Reset Automático
 * 
 * Este endpoint reseta o ranking diariamente de forma automática.
 * Executa SEMPRE que o horário configurado é atingido.
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Token de segurança
define('CRON_TOKEN', 'ym_auto_reset_2024_secure_xyz');

// Verificar token
$token = $_GET['token'] ?? '';
if ($token !== CRON_TOKEN) {
    http_response_code(401);
    die(json_encode(['success' => false, 'error' => 'Token inválido']));
}

// Incluir configuração do banco
require_once __DIR__ . '/database.php';

try {
    // Obter conexão
    $conn = getDbConnection();
    
    // Configurar timezone
    date_default_timezone_set('America/Sao_Paulo');
    
    $now = new DateTime();
    $current_time = $now->format('H:i');
    $current_date = $now->format('Y-m-d');
    
    // Obter configurações do sistema
    $result = $conn->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('reset_time', 'last_reset_date')");
    
    $settings = [];
    while ($row = $result->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    
    $reset_time = $settings['reset_time'] ?? '02:30';
    $last_reset_date = $settings['last_reset_date'] ?? null;
    
    // Verificar se precisa resetar
    $needs_reset = false;
    $reason = '';
    
    // Converter horários para minutos
    list($reset_h, $reset_m) = explode(':', $reset_time);
    list($current_h, $current_m) = explode(':', $current_time);
    
    $reset_minutes = ((int)$reset_h * 60) + (int)$reset_m;
    $current_minutes = ((int)$current_h * 60) + (int)$current_m;
    
    // Se passou do horário E ainda não resetou hoje
    if ($current_minutes >= $reset_minutes && $last_reset_date !== $current_date) {
        $needs_reset = true;
        $reason = 'Horário atingido e não resetou hoje';
    } else if ($last_reset_date === $current_date) {
        $reason = 'Já resetou hoje';
    } else {
        $reason = 'Ainda não chegou no horário';
    }
    
    $response = [
        'success' => true,
        'timestamp' => $now->format('Y-m-d H:i:s'),
        'current_time' => $current_time,
        'reset_time' => $reset_time,
        'last_reset_date' => $last_reset_date,
        'current_date' => $current_date,
        'needs_reset' => $needs_reset,
        'reason' => $reason
    ];
    
    if ($needs_reset) {
        // CONTAR USUÁRIOS ANTES
        $result = $conn->query("SELECT COUNT(*) as total FROM users WHERE daily_points > 0");
        $before = $result->fetch_assoc();
        
        // RESETAR PONTOS - QUERY SIMPLES E DIRETA
        $conn->query("UPDATE users SET daily_points = 0 WHERE daily_points > 0");
        $affected = $conn->affected_rows;
        
        // CONTAR USUÁRIOS DEPOIS
        $result = $conn->query("SELECT COUNT(*) as total FROM users WHERE daily_points > 0");
        $after = $result->fetch_assoc();
        
        // ATUALIZAR DATA DO ÚLTIMO RESET
        $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('last_reset_date', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->bind_param('ss', $current_date, $current_date);
        $stmt->execute();
        
        // REGISTRAR NO LOG (se a tabela existir)
        $log_details = json_encode([
            'type' => 'auto_reset',
            'reset_time' => $reset_time,
            'users_affected' => $affected,
            'users_before' => (int)$before['total'],
            'users_after' => (int)$after['total'],
            'timestamp' => $now->format('Y-m-d H:i:s')
        ]);
        
        try {
            $stmt = $conn->prepare("INSERT INTO admin_logs (admin_id, action, details, created_at) VALUES (0, 'auto_ranking_reset', ?, NOW())");
            $stmt->bind_param('s', $log_details);
            $stmt->execute();
        } catch (Exception $log_error) {
            // Ignorar erro de log
        }
        
        $response['reset_executed'] = true;
        $response['users_with_points_before'] = (int)$before['total'];
        $response['users_affected'] = $affected;
        $response['users_with_points_after'] = (int)$after['total'];
        $response['message'] = '✅ RESET EXECUTADO COM SUCESSO!';
    }
    
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ], JSON_PRETTY_PRINT);
}
