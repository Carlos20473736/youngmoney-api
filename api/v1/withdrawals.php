<?php
// Endpoint da API para Saques (v1)

header("Content-Type: application/json");
require_once '../../database.php';
require_once __DIR__ . '/../xreq/validate.php';

$method = $_SERVER['REQUEST_METHOD'];
$conn = getDbConnection();

switch ($method) {
    case 'GET':
        // Lógica para obter o histórico de saques de um usuário
        // Exemplo: /api/v1/withdrawals.php?user_id=1
        if (isset($_GET['user_id'])) {
            $userId = intval($_GET['user_id']);
            $stmt = $conn->prepare("SELECT id, pix_key_type, amount, status, created_at FROM withdrawals WHERE user_id = ? ORDER BY created_at DESC");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            $withdrawals = [];
            while ($row = $result->fetch_assoc()) {
                $withdrawals[] = $row;
            }
            echo json_encode($withdrawals);
            $stmt->close();
        } else {
            http_response_code(400);
            echo json_encode(['message' => 'User ID is required']);
        }
        break;

    case 'POST':
        // Lógica para solicitar um novo saque
        $data = json_decode(file_get_contents('php://input'), true);

        if (isset($data['user_id']) && isset($data['pix_key']) && isset($data['pix_key_type']) && isset($data['amount'])) {
            $userId = intval($data['user_id']);
            $pixKey = $data['pix_key'];
            $pixKeyType = $data['pix_key_type'];
            $amount = floatval($data['amount']);

            // Lógica adicional: verificar se o usuário tem pontos suficientes

            $stmt = $conn->prepare("INSERT INTO withdrawals (user_id, pix_key, pix_key_type, amount) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("issd", $userId, $pixKey, $pixKeyType, $amount);

            if ($stmt->execute()) {
                http_response_code(201);
                echo json_encode(['message' => 'Withdrawal request created successfully']);
            } else {
                http_response_code(500);
                echo json_encode(['message' => 'Failed to create withdrawal request', 'error' => $stmt->error]);
            }
            $stmt->close();
        } else {
            http_response_code(400);
            echo json_encode(['message' => 'User ID, pix_key, pix_key_type, and amount are required']);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(['message' => 'Method Not Allowed']);
        break;
}

$conn->close();
?>
