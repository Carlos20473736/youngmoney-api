<?php
/**
 * MoniTag Postback Endpoint - Macros Oficiais
 * Recebe postbacks do MoniTag via GET
 * 
 * URL para configurar no painel do MoniTag:
 * https://reasonable-perfection-production.up.railway.app/monetag/track.php?event_type={event_type}&zone_id={zone_id}&sub_id={sub_id}&sub_id2={sub_id2}&ymid={ymid}&revenue={estimated_price}&request_var={request_var}
 * 
 * Macros oficiais suportadas:
 * - {event_type}: impression ou click
 * - {zone_id}: ID da zona do MoniTag
 * - {sub_id}: user_id (passado no SDK)
 * - {sub_id2}: email (passado no SDK)
 * - {ymid}: ID único da sessão (gerado pelo MoniTag)
 * - {estimated_price}: Receita estimada
 * - {request_var}: Variável customizada (passada no SDK)
 */

// CORS MUST be first
require_once __DIR__ . '/../cors.php';

header('Content-Type: application/json');

require_once __DIR__ . '/../database.php';

function sendSuccess($data = []) {
    echo json_encode(['success' => true, 'data' => $data]);
    exit;
}

function sendError($message, $code = 400) {
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
}

// Log completo para debug
error_log("=== MoniTag Postback ===");
error_log("Method: " . $_SERVER['REQUEST_METHOD']);
error_log("GET params: " . json_encode($_GET));
error_log("POST params: " . json_encode($_POST));
error_log("User-Agent: " . ($_SERVER['HTTP_USER_AGENT'] ?? 'N/A'));
error_log("IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'N/A'));

// Aceitar GET ou POST (MoniTag usa GET por padrão)
$event_type = $_GET['event_type'] ?? $_POST['event_type'] ?? null;
$zone_id = $_GET['zone_id'] ?? $_POST['zone_id'] ?? null;
$sub_id = $_GET['sub_id'] ?? $_POST['sub_id'] ?? null; // user_id
$sub_id2 = $_GET['sub_id2'] ?? $_POST['sub_id2'] ?? null; // email
$ymid = $_GET['ymid'] ?? $_POST['ymid'] ?? $_GET['click_id'] ?? $_POST['click_id'] ?? null; // ID único da sessão
$revenue = $_GET['revenue'] ?? $_POST['revenue'] ?? $_GET['estimated_price'] ?? $_POST['estimated_price'] ?? 0;
$request_var = $_GET['request_var'] ?? $_POST['request_var'] ?? null;

error_log("Parsed params:");
error_log("  - event_type: $event_type");
error_log("  - zone_id: $zone_id");
error_log("  - sub_id: $sub_id");
error_log("  - sub_id2: $sub_id2");
error_log("  - ymid: $ymid");
error_log("  - revenue: $revenue");
error_log("  - request_var: $request_var");

// Validar event_type
if (!$event_type) {
    error_log("ERROR: event_type é obrigatório");
    sendError('event_type é obrigatório');
}

if (!in_array($event_type, ['impression', 'click'])) {
    error_log("ERROR: event_type inválido: $event_type");
    sendError('event_type deve ser impression ou click');
}

// Validar user_id (sub_id)
$user_id = null;
if ($sub_id && is_numeric($sub_id)) {
    $user_id = (int)$sub_id;
} else {
    error_log("ERROR: sub_id inválido ou ausente: $sub_id");
    sendError('sub_id (user_id) é obrigatório e deve ser numérico');
}

// Gerar session_id único (usar ymid se disponível)
$session_id = $ymid ?? ($user_id . '_' . time() . '_' . uniqid());

error_log("Final values:");
error_log("  - user_id: $user_id");
error_log("  - session_id: $session_id");
error_log("  - event_type: $event_type");

try {
    $conn = getDbConnection();
    
    // Verificar se já existe evento idêntico (evitar duplicatas)
    if ($ymid) {
        $stmt = $conn->prepare("
            SELECT id FROM monetag_events 
            WHERE user_id = ? AND session_id = ? AND event_type = ?
            LIMIT 1
        ");
        $stmt->bind_param("iss", $user_id, $session_id, $event_type);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $existing = $result->fetch_assoc();
            error_log("WARN: Evento duplicado detectado (ID: {$existing['id']})");
            $stmt->close();
            
            // Retornar sucesso mas indicar que é duplicata
            sendSuccess([
                'event_registered' => false,
                'reason' => 'duplicate',
                'existing_event_id' => $existing['id']
            ]);
        }
        $stmt->close();
    }
    
    // Inserir evento
    $stmt = $conn->prepare("
        INSERT INTO monetag_events (user_id, event_type, session_id, revenue, created_at)
        VALUES (?, ?, ?, ?, NOW())
    ");
    $revenue_float = (float)$revenue;
    $stmt->bind_param("issd", $user_id, $event_type, $session_id, $revenue_float);
    $stmt->execute();
    $event_id = $stmt->insert_id;
    $stmt->close();
    
    error_log("SUCCESS: Event registered - ID=$event_id, user_id=$user_id, event_type=$event_type");
    
    // Buscar progresso atualizado do dia
    $today = date('Y-m-d');
    $stmt = $conn->prepare("
        SELECT 
            COUNT(CASE WHEN event_type = 'impression' THEN 1 END) as impressions,
            COUNT(CASE WHEN event_type = 'click' THEN 1 END) as clicks
        FROM monetag_events
        WHERE user_id = ? AND DATE(created_at) = ?
    ");
    $stmt->bind_param("is", $user_id, $today);
    $stmt->execute();
    $result = $stmt->get_result();
    $progress = $result->fetch_assoc();
    $stmt->close();
    $conn->close();
    
    // Metas
    $required_impressions = 5;
    $required_clicks = 1;
    
    $response = [
        'event_registered' => true,
        'event_id' => $event_id,
        'event_type' => $event_type,
        'user_id' => $user_id,
        'session_id' => $session_id,
        'zone_id' => $zone_id,
        'revenue' => $revenue_float,
        'progress' => [
            'impressions' => [
                'current' => (int)$progress['impressions'],
                'required' => $required_impressions,
                'completed' => (int)$progress['impressions'] >= $required_impressions
            ],
            'clicks' => [
                'current' => (int)$progress['clicks'],
                'required' => $required_clicks,
                'completed' => (int)$progress['clicks'] >= $required_clicks
            ],
            'all_completed' => (int)$progress['impressions'] >= $required_impressions && (int)$progress['clicks'] >= $required_clicks
        ]
    ];
    
    error_log("Response: " . json_encode($response));
    error_log("======================");
    sendSuccess($response);
    
} catch (Exception $e) {
    error_log("ERROR: " . $e->getMessage());
    error_log("======================");
    sendError('Erro ao registrar evento: ' . $e->getMessage(), 500);
}
?>
