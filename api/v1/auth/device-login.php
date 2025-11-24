<?php
/**
 * Login Endpoint - Aceita Google Token (Criptografado ou JSON Puro)
 * 
 * Este endpoint redireciona para google-login.php
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Req');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../../includes/DecryptMiddleware.php';

/**
 * Descriptografa dados criptografados
 */
function decryptData($encryptedBase64, $key) {
    try {
        $method = 'AES-256-CBC';
        $ivLength = openssl_cipher_iv_length($method);
        
        // Decodificar base64
        $data = base64_decode($encryptedBase64);
        
        if ($data === false) {
            error_log("device-login.php - Failed to decode base64");
            return false;
        }
        
        // Extrair IV e dados criptografados
        $iv = substr($data, 0, $ivLength);
        $ciphertext = substr($data, $ivLength);
        
        // Derivar chave de 32 bytes
        $derivedKey = hash('sha256', $key, true);
        
        // Descriptografar
        $decrypted = openssl_decrypt($ciphertext, $method, $derivedKey, OPENSSL_RAW_DATA, $iv);
        
        if ($decrypted === false) {
            error_log("device-login.php - openssl_decrypt failed");
            return false;
        }
        
        error_log("device-login.php - Decryption successful, length: " . strlen($decrypted));
        return $decrypted;
        
    } catch (Exception $e) {
        error_log("device-login.php - Decryption exception: " . $e->getMessage());
        return false;
    }
}

try {
    // 1. LER RAW INPUT
    $rawInput = file_get_contents('php://input');
    error_log("device-login.php - Raw input: " . substr($rawInput, 0, 200) . "...");
    
    $rawData = json_decode($rawInput, true);
    
    // 2. VERIFICAR SE É REQUISIÇÃO CRIPTOGRAFADA
    if (isset($rawData['encrypted']) && $rawData['encrypted'] === true && isset($rawData['data'])) {
        error_log("device-login.php - Detected encrypted request");
        
        // Obter o header X-Req (chave de descriptografia)
        $headers = getallheaders();
        $xReq = $headers['X-Req'] ?? $headers['x-req'] ?? null;
        
        if (!$xReq) {
            error_log("device-login.php - ERROR: X-Req header missing");
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'Header X-Req é obrigatório para requisições criptografadas'
            ]);
            exit;
        }
        
        error_log("device-login.php - X-Req header found: " . substr($xReq, 0, 50) . "...");
        
        // Descriptografar o campo 'data'
        $decrypted = decryptData($rawData['data'], $xReq);
        
        if ($decrypted === false) {
            error_log("device-login.php - Decryption failed");
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'Falha ao descriptografar requisição'
            ]);
            exit;
        }
        
        // Decodificar JSON descriptografado
        $data = json_decode($decrypted, true);
        
        if (!$data) {
            error_log("device-login.php - Failed to decode decrypted JSON");
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'Dados descriptografados inválidos'
            ]);
            exit;
        }
        
        error_log("device-login.php - Data after decryption: " . json_encode($data));
        $isEncrypted = true;
        
    } else {
        // 3. JSON PURO (não criptografado)
        error_log("device-login.php - Plain JSON request");
        $data = $rawData;
        $isEncrypted = false;
    }
    
    // 4. VALIDAR DADOS
    if (!isset($data['google_token']) || empty($data['google_token'])) {
        error_log("device-login.php - ERROR: google_token is missing or empty");
        error_log("device-login.php - Available keys: " . implode(', ', array_keys($data ?: [])));
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Google token é obrigatório'
        ]);
        exit;
    }
    
    error_log("device-login.php - google_token received: " . substr($data['google_token'], 0, 50) . "...");
    
    // 5. REDIRECIONAR PARA GOOGLE-LOGIN
    error_log("device-login.php - Redirecting to google-login.php");
    
    // Passar dados via $_POST para o google-login.php
    $_POST = $data;
    $_POST['_is_encrypted'] = $isEncrypted;
    $_POST['_x_req'] = $headers['X-Req'] ?? $headers['x-req'] ?? null;
    
    include __DIR__ . '/google-login.php';
    
} catch (Exception $e) {
    error_log("device-login.php - Exception: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Erro interno: ' . $e->getMessage()
    ]);
}
?>
