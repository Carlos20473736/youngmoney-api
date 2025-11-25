<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/response_helper.php';

header('Content-Type: application/json');

try {
    // Buscar primeiros 10 usuários
    $stmt = $pdo->query("
        SELECT id, name, email, referral_code, created_at
        FROM users
        ORDER BY id ASC
        LIMIT 10
    ");
    
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'users' => $users,
        'total' => count($users)
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
