<?php
// Arquivo de Configuração do Banco de Dados
// As credenciais devem ser definidas como variáveis de ambiente no servidor

// Usar $_ENV primeiro, depois getenv() como fallback
define('DB_HOST', $_ENV['DB_HOST'] ?? getenv('DB_HOST'));
define('DB_PORT', $_ENV['DB_PORT'] ?? getenv('DB_PORT'));
define('DB_USER', $_ENV['DB_USER'] ?? getenv('DB_USER'));
define('DB_PASSWORD', $_ENV['DB_PASSWORD'] ?? getenv('DB_PASSWORD'));
define('DB_NAME', $_ENV['DB_NAME'] ?? getenv('DB_NAME'));

// Validação crítica - garantir que todas as variáveis essenciais estão definidas
if (empty(DB_HOST) || empty(DB_USER) || empty(DB_PASSWORD) || empty(DB_NAME)) {
    error_log("=== ERRO CRÍTICO: Variáveis de ambiente do banco de dados não configuradas! ===");
    error_log("DB_HOST: " . (DB_HOST ?: 'NOT SET'));
    error_log("DB_USER: " . (DB_USER ?: 'NOT SET'));
    error_log("DB_PASSWORD: " . (DB_PASSWORD ? 'SET (hidden)' : 'NOT SET'));
    error_log("DB_NAME: " . (DB_NAME ?: 'NOT SET'));
    error_log("DB_PORT: " . (DB_PORT ?: 'NOT SET'));
    
    // Retornar erro 500 se não estiver em CLI
    if (php_sapi_name() !== 'cli') {
        http_response_code(500);
        echo json_encode([
            'success' => false, 
            'error' => 'Database configuration error - check server logs'
        ]);
        exit;
    }
}

// Log de sucesso (apenas em desenvolvimento)
if (getenv('DEBUG') === 'true') {
    error_log("=== Configuração do banco de dados carregada com sucesso ===");
    error_log("DB_HOST: " . DB_HOST);
    error_log("DB_USER: " . DB_USER);
    error_log("DB_NAME: " . DB_NAME);
    error_log("DB_PORT: " . DB_PORT);
}

?>
