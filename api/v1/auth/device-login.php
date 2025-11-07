<?php
/**
 * Device Login com Segurança Máxima V2
 * 
 * Retorna encrypted_seed e session_salt para ativar chaves rotativas
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Req');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../../database.php';
require_once __DIR__ . '/../../../includes/SecureKeyManager.php';
require_once __DIR__ . '/../../../includes/DecryptMiddleware.php';

try {
    // 1. PROCESSAR REQUISIÇÃO (pode vir criptografada com V1)
    $data = DecryptMiddleware::processRequest();
    
    if (empty($data)) {
        // Fallback para JSON não criptografado
        $data = json_decode(file_get_contents('php://input'), true);
    }
    
    // 2. VALIDAR DADOS
    if (!isset($data['device_id']) || empty($data['device_id'])) {
        DecryptMiddleware::sendError('Device ID não fornecido');
        exit;
    }
    
    $deviceId = $data['device_id'];
    
    // 3. CONECTAR AO BANCO
    $conn = getDbConnection();
    
    // 4. VERIFICAR SE USUÁRIO JÁ EXISTE
    $stmt = $conn->prepare("SELECT * FROM users WHERE device_id = ?");
    $stmt->bind_param("s", $deviceId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    
    if ($user) {
        // Usuário já existe
        $userId = $user['id'];
        $email = $user['email'];
        $name = $user['name'];
        
        // Atualizar last_login
        $stmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        
    } else {
        // Criar novo usuário
        $email = 'user_' . substr($deviceId, 0, 8) . '@youngmoney.app';
        $name = 'Usuário ' . substr($deviceId, 0, 8);
        $inviteCode = 'YM' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
        
        $stmt = $conn->prepare(
            "INSERT INTO users (device_id, email, name, points, invite_code, created_at, last_login) 
            VALUES (?, ?, ?, 0, ?, NOW(), NOW())"
        );
        $stmt->bind_param("ssss", $deviceId, $email, $name, $inviteCode);
        $stmt->execute();
        
        $userId = $conn->insert_id;
    }
    
    // 5. GERAR MASTER SEED E SESSION SALT (V2)
    $masterSeed = SecureKeyManager::generateMasterSeed();
    $sessionSalt = SecureKeyManager::generateSessionSalt();
    
    // 6. CRIPTOGRAFAR SEED COM DEVICE_ID
    $encryptedSeed = SecureKeyManager::encryptSeedWithPassword($masterSeed, $deviceId);
    
    // 7. ARMAZENAR SEED E SALT NO BANCO
    $dbHost = getenv('DB_HOST') ?: 'localhost';
    $dbPort = getenv('DB_PORT') ?: '3306';
    $dbName = getenv('DB_NAME') ?: 'railway';
    $dbUser = getenv('DB_USER') ?: 'root';
    $dbPass = getenv('DB_PASSWORD') ?: '';
    
    $pdo = new PDO(
        "mysql:host=$dbHost;port=$dbPort;dbname=$dbName;charset=utf8mb4",
        $dbUser,
        $dbPass,
        [
            PDO::MYSQL_ATTR_SSL_CA => true,
            PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]
    );
    
    $stored = SecureKeyManager::storeUserSecrets($pdo, $userId, $masterSeed, $sessionSalt);
    
    if (!$stored) {
        error_log("Failed to store user secrets for user $userId");
    }
    
    // 8. GERAR TOKEN DE AUTENTICAÇÃO
    $token = bin2hex(random_bytes(32));
    
    $stmt = $conn->prepare("UPDATE users SET token = ? WHERE id = ?");
    $stmt->bind_param("si", $token, $userId);
    $stmt->execute();
    
    // 9. BUSCAR DADOS ATUALIZADOS DO USUÁRIO
    $stmt = $conn->prepare("SELECT id, email, name, device_id, profile_picture, points FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    
    // 10. ENVIAR RESPOSTA COM SEED CRIPTOGRAFADO
    DecryptMiddleware::sendSuccess([
        'token' => $token,
        'encrypted_seed' => $encryptedSeed,  // ⭐ SEED CRIPTOGRAFADO (V2)
        'session_salt' => $sessionSalt,      // ⭐ SALT DA SESSÃO (V2)
        'user' => [
            'id' => (int)$user['id'],
            'email' => $user['email'],
            'name' => $user['name'],
            'device_id' => $user['device_id'],
            'profile_picture' => $user['profile_picture'],
            'points' => (int)$user['points']
        ]
    ], true); // Resposta criptografada
    
    error_log("Device login V2 successful for user $userId - seed and salt generated");
    
} catch (Exception $e) {
    error_log("Device login error: " . $e->getMessage());
    DecryptMiddleware::sendError('Erro interno: ' . $e->getMessage(), 500);
}
?>
