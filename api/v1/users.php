<?php
// Endpoint da API para Usuários (v1)

header("Content-Type: application/json");
require_once '../../database.php';
require_once '../../middleware/auto_reset.php';
require_once __DIR__ . '/../xreq/validate.php';
require_once __DIR__ . '/../../includes/ResponseHelper.php';

$method = $_SERVER['REQUEST_METHOD'];
$conn = getDbConnection();

// Verificar e fazer reset automático se necessário
checkAndResetRanking($conn);

switch ($method) {
    case 'GET':
        // Lógica para obter dados de usuários (ex: perfil)
        // Exemplo: /api/v1/users.php?id=1
        if (isset($_GET['id'])) {
            $userId = intval($_GET['id']);
            $stmt = $conn->prepare("SELECT id, username, email, name, profile_picture, points, created_at FROM users WHERE id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                ResponseHelper::success($result->fetch_assoc());
            } else {
                ResponseHelper::error('User not found', 404);
            }
            $stmt->close();
        } else {
            ResponseHelper::error('User ID is required', 400);
        }
        break;

    case 'POST':
        // Lógica para criar um novo usuário (registro)
        $data = json_decode(file_get_contents('php://input'), true);

        if (isset($data['username']) && isset($data['password']) && isset($data['email'])) {
            $username = $data['username'];
            $password = password_hash($data['password'], PASSWORD_DEFAULT);
            $email = $data['email'];

            $stmt = $conn->prepare("INSERT INTO users (username, password, email) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $username, $password, $email);

            if ($stmt->execute()) {
                ResponseHelper::success([
                    'message' => 'User created successfully',
                    'user_id' => $conn->insert_id
                ], 201);
            } else {
                ResponseHelper::error('Failed to create user: ' . $stmt->error, 500);
            }
            $stmt->close();
        } else {
            ResponseHelper::error('Username, password, and email are required', 400);
        }
        break;

    // Implementar PUT para atualizar e DELETE para excluir usuários conforme necessário

    default:
        ResponseHelper::error('Method Not Allowed', 405);
        break;
}

$conn->close();
?>
