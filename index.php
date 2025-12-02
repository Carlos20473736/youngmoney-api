<?php
// Security Headers
require_once __DIR__ . '/includes/security_headers.php';
setAPISecurityHeaders();

echo json_encode([
    'status' => 'success',
    'message' => 'Young Money API is running!',
    'version' => '1.0',
    'endpoints' => [
        'POST /api/v1/auth/device-login.php' => 'Device login',
        'GET /api/v1/users.php' => 'Get user profile',
        'POST /api/v1/points.php' => 'Manage points',
        'POST /api/v1/withdrawals.php' => 'Request withdrawal'
    ]
]);
?>
