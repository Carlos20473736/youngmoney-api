<?php
require_once __DIR__ . '/../../admin/cors.php';
require_once __DIR__ . '/../../database.php';

header('Content-Type: application/json');

try {
    $conn = getDbConnection();
    
    // Resetar apenas daily_points (manter points totais)
    $stmt = $conn->prepare("UPDATE users SET daily_points = 0");
    $stmt->execute();
    
    echo json_encode([
        'success' => true,
        'message' => 'Ranking resetado com sucesso!'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

$conn->close();
?>
