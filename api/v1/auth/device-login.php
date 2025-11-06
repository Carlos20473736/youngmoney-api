<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../../../database.php';

// Verificar método HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Método não permitido'
    ]);
    exit;
}

// Obter dados JSON
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!isset($data['device_id']) || empty($data['device_id'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Device ID não fornecido'
    ]);
    exit;
}

$device_id = $data['device_id'];

try {
    $db = getDatabase();
    
    // Verificar se usuário já existe com este device_id
    $stmt = $db->prepare("SELECT * FROM users WHERE device_id = ?");
    $stmt->execute([$device_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        // Usuário já existe, fazer login
        $user_id = $user['id'];
        $email = $user['email'];
        $name = $user['name'];
        
        // Atualizar last_login
        $stmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        $stmt->execute([$user_id]);
        
    } else {
        // Criar novo usuário
        $email = 'user_' . substr($device_id, 0, 8) . '@youngmoney.app';
        $name = 'Usuário ' . substr($device_id, 0, 8);
        
        $stmt = $db->prepare("
            INSERT INTO users (device_id, email, name, points, created_at, last_login) 
            VALUES (?, ?, ?, 0, NOW(), NOW())
        ");
        $stmt->execute([$device_id, $email, $name]);
        
        $user_id = $db->lastInsertId();
    }
    
    // Gerar token de autenticação (simples para este exemplo)
    $token = bin2hex(random_bytes(32));
    
    // Salvar token no banco (você pode criar uma tabela de tokens se quiser)
    // Por enquanto, vamos apenas retornar o token
    
    // Retornar resposta de sucesso
    echo json_encode([
        'success' => true,
        'data' => [
            'token' => $token,
            'user' => [
                'id' => $user_id,
                'email' => $email,
                'name' => $name,
                'device_id' => $device_id
            ]
        ]
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro no banco de dados: ' . $e->getMessage()
    ]);
}
?>
