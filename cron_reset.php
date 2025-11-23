<?php
/**
 * Endpoint para Reset Automático do Ranking
 * 
 * Este endpoint deve ser chamado por um cron job externo a cada minuto.
 * Ele verifica se passou do horário configurado e reseta os pontos diários.
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
        'error' => 'Unauthorized',
        'message' => 'Token inválido'
    ]);
    exit;
}

// Incluir configuração do banco
require_once __DIR__ . '/database.php';

// Obter conexão
$conn = getDbConnection();
$pdo = null;

// Converter MySQLi para PDO-like para manter compatibilidade
class PDOWrapper {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    public function query($sql) {
        $result = $this->conn->query($sql);
        if (!$result) {
            throw new Exception($this->conn->error);
        }
        return new ResultWrapper($result);
    }
    
    public function prepare($sql) {
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            throw new Exception($this->conn->error);
        }
        return new StmtWrapper($stmt);
    }
}

class ResultWrapper {
    private $result;
    
    public function __construct($result) {
        $this->result = $result;
    }
    
    public function fetch($mode = null) {
        return $this->result->fetch_assoc();
    }
}

class StmtWrapper {
    private $stmt;
    
    public function __construct($stmt) {
        $this->stmt = $stmt;
    }
    
    public function execute($params = []) {
        if (!empty($params)) {
            $types = str_repeat('s', count($params));
            $this->stmt->bind_param($types, ...$params);
        }
        return $this->stmt->execute();
    }
    
    public function rowCount() {
        return $this->stmt->affected_rows;
    }
}

$pdo = new PDOWrapper($conn);

try {
    // Configurar timezone
    date_default_timezone_set('America/Sao_Paulo');
    
    // Obter horário atual
    $now = new DateTime();
    $current_hour = (int)$now->format('H');
    $current_minute = (int)$now->format('i');
    $current_date = $now->format('Y-m-d');
    
    // Obter horário de reset configurado (formato chave-valor)
    $stmt = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'reset_time' LIMIT 1");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $reset_time = $row ? $row['setting_value'] : '21:00';
    
    list($reset_hour, $reset_minute) = explode(':', $reset_time);
    $reset_hour = (int)$reset_hour;
    $reset_minute = (int)$reset_minute;
    
    // Obter data do último reset
    $stmt = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'last_reset_date' LIMIT 1");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $last_reset_date = $row ? $row['setting_value'] : null;
    
    // Verificar se precisa resetar
    $needs_reset = false;
    $reason = '';
    
    // Calcular minutos desde meia-noite
    $current_minutes = ($current_hour * 60) + $current_minute;
    $reset_minutes = ($reset_hour * 60) + $reset_minute;
    
    // Verificar se já passou do horário de reset
    if ($current_minutes >= $reset_minutes) {
        // Verificar se já resetou hoje
        if ($last_reset_date !== $current_date) {
            $needs_reset = true;
            $reason = 'Horário de reset atingido e ainda não resetou hoje';
        } else {
            $reason = 'Já resetou hoje';
        }
    } else {
        $reason = 'Ainda não chegou no horário de reset';
    }
    
    $response = [
        'success' => true,
        'timestamp' => $now->format('Y-m-d H:i:s'),
        'current_time' => sprintf('%02d:%02d', $current_hour, $current_minute),
        'reset_time' => $reset_time,
        'last_reset_date' => $last_reset_date,
        'current_date' => $current_date,
        'needs_reset' => $needs_reset,
        'reason' => $reason
    ];
    
    if ($needs_reset) {
        // Contar usuários antes do reset
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE daily_points > 0");
        $before = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Resetar pontos diários
        $stmt = $pdo->prepare("UPDATE users SET daily_points = 0");
        $stmt->execute();
        $affected = $stmt->rowCount();
        
        // Atualizar data do último reset usando INSERT ON DUPLICATE KEY UPDATE
        $update_stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('last_reset_date', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $update_stmt->bind_param('ss', $current_date, $current_date);
        $update_stmt->execute();
        
        // Registrar no log
        $stmt = $pdo->prepare("
            INSERT INTO admin_logs (admin_id, action, details, ip_address, created_at) 
            VALUES (0, 'ranking_reset', ?, '0.0.0.0', NOW())
        ");
        $log_details = json_encode([
            'type' => 'auto_reset',
            'reset_time' => $reset_time,
            'users_affected' => $affected,
            'users_with_points' => $before['total'],
            'timestamp' => $now->format('Y-m-d H:i:s')
        ]);
        $stmt->execute([$log_details]);
        
        $response['reset_executed'] = true;
        $response['users_affected'] = $affected;
        $response['users_with_points_before'] = $before['total'];
        $response['message'] = 'Reset executado com sucesso!';
    }
    
    echo json_encode($response, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal Server Error',
        'message' => $e->getMessage()
    ]);
}
