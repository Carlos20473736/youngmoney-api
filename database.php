<?php
// Arquivo de Conexão com o Banco de Dados

require_once 'config.php';

function getDbConnection() {
    try {
        // Criar conexão MySQLi com SSL
        $conn = mysqli_init();
        
        if (!$conn) {
            throw new Exception("mysqli_init failed");
        }
        
        // Configurar SSL (Aiven requer SSL)
        $conn->ssl_set(NULL, NULL, NULL, NULL, NULL);
        $conn->options(MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, false);
        
        // Conectar ao banco
        $conn->real_connect(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME, DB_PORT, NULL, MYSQLI_CLIENT_SSL);
        
        if ($conn->connect_error) {
            throw new Exception("Connection failed: " . $conn->connect_error);
        }
        
        return $conn;
    } catch (Exception $e) {
        // Em um ambiente de produção, você pode querer logar o erro em vez de exibi-lo
        http_response_code(500);
        echo json_encode(['error' => 'Database connection error: ' . $e->getMessage()]);
        exit;
    }
}

?>
