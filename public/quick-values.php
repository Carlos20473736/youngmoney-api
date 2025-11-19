<?php
/**
 * Endpoint Público de Valores Rápidos (SEM criptografia)
 * Para uso interno do WebView do app Android
 * 
 * GET /public/quick-values.php
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Responder OPTIONS para CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

date_default_timezone_set('America/Sao_Paulo');

require_once __DIR__ . '/../database.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        echo json_encode(['success' => false, 'error' => 'Método não permitido']);
        exit;
    }
    
    $conn = getDbConnection();
    
    // Buscar valores rápidos de saque
    $stmt = $conn->prepare("SELECT value_amount FROM withdrawal_quick_values WHERE is_active = 1 ORDER BY value_amount ASC");
    
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $quick_values = [];
    while ($row = $result->fetch_assoc()) {
        $value = (int)$row['value_amount'];
        // Converter -1 para "TUDO"
        if ($value === -1) {
            $quick_values[] = 'TUDO';
        } else {
            $quick_values[] = $value;
        }
    }
    
    // Se não houver valores, usar padrão
    if (empty($quick_values)) {
        $quick_values = [1, 10, 20, 50];
    }
    
    $stmt->close();
    $conn->close();
    
    // Enviar resposta SEM criptografia
    echo json_encode([
        'success' => true,
        'values' => $quick_values
    ]);
    
} catch (Exception $e) {
    error_log("Quick values endpoint error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao buscar valores: ' . $e->getMessage()
    ]);
}
?>
