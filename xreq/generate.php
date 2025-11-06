<?php
/**
 * Endpoint para gerar token XReq único
 * Cada token só pode ser usado uma vez
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../database.php';

try {
    // Obter informações da requisição
    $headers = getallheaders();
    $token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : null;
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    
    $userId = null;
    
    // Se tiver Bearer token, buscar user_id
    if ($token) {
        $conn = getDbConnection();
        $stmt = $conn->prepare("SELECT id FROM users WHERE token = ?");
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            $userId = $user['id'];
        }
    }
    
    // Gerar token XReq único (64 caracteres)
    $xreqToken = bin2hex(random_bytes(32));
    
    // Salvar no banco de dados
    $conn = getDbConnection();
    $stmt = $conn->prepare("INSERT INTO xreq_tokens (token, user_id, ip_address, user_agent) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("siss", $xreqToken, $userId, $ipAddress, $userAgent);
    
    if (!$stmt->execute()) {
        throw new Exception("Erro ao gerar XReq token");
    }
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data' => [
            'xreq' => $xreqToken,
            'expires_in' => 300 // 5 minutos em segundos
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao gerar XReq: ' . $e->getMessage()
    ]);
}
?>
