<?php
// Arquivo de Conexão com o Banco de Dados

require_once 'config.php';

function getDbConnection() {
    try {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME, DB_PORT);
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
