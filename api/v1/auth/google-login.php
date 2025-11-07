<?php
/**
 * Google Login com Segurança Máxima V2
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
    if (!isset($data['google_token']) || !isset($data['device_id'])) {
        DecryptMiddleware::sendError('Google token e device_id são obrigatórios');
        exit;
    }
    
    $googleToken = $data['google_token'];
    $deviceId = $data['device_id'];
    
    // 3. VERIFICAR TOKEN DO GOOGLE
    $tokenParts = explode('.', $googleToken);
    if (count($tokenParts) !== 3) {
        DecryptMiddleware::sendError('Token do Google inválido', 401);
        exit;
    }
    
    $payload = json_decode(base64_decode($tokenParts[1]), true);
    $googleId = $payload['sub'] ?? null;
    $email = $payload['email'] ?? null;
    $name = $payload['name'] ?? null;
    $profilePicture = $payload['picture'] ?? null;
    
    if (!$googleId || !$email) {
        DecryptMiddleware::sendError('Não foi possível extrair dados do token do Google', 401);
        exit;
    }
    
    // 4. CONECTAR AO BANCO
    $conn = getDbConnection();
    
    // 5. VERIFICAR SE USUÁRIO JÁ EXISTE
    $stmt = $conn->prepare("SELECT * FROM users WHERE google_id = ? OR device_id = ?");
    $stmt->bind_param("ss", $googleId, $deviceId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Usuário existe - atualizar dados
        $user = $result->fetch_assoc();
        $userId = $user['id'];
        
        $stmt = $conn->prepare("UPDATE users SET google_id = ?, device_id = ?, email = ?, name = ?, profile_picture = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param("sssssi", $googleId, $deviceId, $email, $name, $profilePicture, $userId);
        $stmt->execute();
        
    } else {
        // Criar novo usuário
        $inviteCode = 'YM' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
        $stmt = $conn->prepare("INSERT INTO users (google_id, device_id, email, name, profile_picture, invite_code, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("ssssss", $googleId, $deviceId, $email, $name, $profilePicture, $inviteCode);
        $stmt->execute();
        $userId = $conn->insert_id;
    }
    
    // 6. GERAR MASTER SEED E SESSION SALT (V2)
    $masterSeed = SecureKeyManager::generateMasterSeed();
    $sessionSalt = SecureKeyManager::generateSessionSalt();
    
    // 7. CRIPTOGRAFAR SEED COM DEVICE_ID
    $encryptedSeed = SecureKeyManager::encryptSeedWithPassword($masterSeed, $deviceId);
    
    // 8. ARMAZENAR SEED E SALT NO BANCO
    // Converter conexão mysqli para PDO temporariamente
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
    
    // 9. GERAR TOKEN DE AUTENTICAÇÃO
    $token = bin2hex(random_bytes(32));
    
    $stmt = $conn->prepare("UPDATE users SET token = ? WHERE id = ?");
    $stmt->bind_param("si", $token, $userId);
    $stmt->execute();
    
    // 10. BUSCAR DADOS ATUALIZADOS DO USUÁRIO
    $stmt = $conn->prepare("SELECT id, email, name, device_id, google_id, profile_picture, points FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    
    // 11. ENVIAR RESPOSTA COM SEED CRIPTOGRAFADO
    DecryptMiddleware::sendSuccess([
        'token' => $token,
        'encrypted_seed' => $encryptedSeed,  // ⭐ SEED CRIPTOGRAFADO (V2)
        'session_salt' => $sessionSalt,      // ⭐ SALT DA SESSÃO (V2)
        'user' => [
            'id' => (int)$user['id'],
            'email' => $user['email'],
            'name' => $user['name'],
            'device_id' => $user['device_id'],
            'google_id' => $user['google_id'],
            'profile_picture' => $user['profile_picture'],
            'points' => (int)$user['points']
        ]
    ], true); // Resposta criptografada
    
    error_log("Google login V2 successful for user $userId - seed and salt generated");
    
} catch (Exception $e) {
    error_log("Google login error: " . $e->getMessage());
    DecryptMiddleware::sendError('Erro interno: ' . $e->getMessage(), 500);
}
?>
