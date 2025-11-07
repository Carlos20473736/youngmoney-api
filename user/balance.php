<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Req');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../xreq/validate.php';
require_once __DIR__ . '/../includes/SecureMiddleware.php';

try {
    // Validar XReq token
    validateXReq();

    $headers = getallheaders();
    $token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : null;
    
    if (!$token) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Token não fornecido']);
        exit;
    }
    
    $conn = getDbConnection();
    
    // Buscar usuário e verificar se tem seed (V2)
    $stmt = $conn->prepare("SELECT id, points, master_seed FROM users WHERE token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Token inválido']);
        exit;
    }
    
    $user = $result->fetch_assoc();
    $userId = $user['id'];
    
    $responseData = [
        'balance' => (int)$user['points'],
        'currency' => 'points'
    ];
    
    // Se tiver seed, usar criptografia V2
    if (!empty($user['master_seed'])) {
        // Converter conexão mysqli para PDO
        $pdo = new PDO(
            "mysql:host=" . getenv('DB_HOST') . ";port=" . getenv('DB_PORT') . ";dbname=" . getenv('DB_NAME'),
            getenv('DB_USER'),
            getenv('DB_PASSWORD'),
            [
                PDO::MYSQL_ATTR_SSL_CA => true,
                PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]
        );
        
        SecureMiddleware::sendSecureResponse($responseData, $pdo, $userId);
    } else {
        // Fallback: resposta sem criptografia (usuários antigos)
        echo json_encode([
            'success' => true,
            'data' => $responseData
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro interno: ' . $e->getMessage()]);
}
?>
