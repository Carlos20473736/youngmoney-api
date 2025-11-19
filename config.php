<?php
// Configurações do Banco de Dados usando variáveis de ambiente
define('DB_HOST', $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: 'mysql-2bb9a545-project-e36d.b.aivencloud.com');
define('DB_USER', $_ENV['DB_USER'] ?? getenv('DB_USER') ?: 'avnadmin');
define('DB_PASSWORD', $_ENV['DB_PASSWORD'] ?? getenv('DB_PASSWORD') ?: '');
define('DB_NAME', $_ENV['DB_NAME'] ?? getenv('DB_NAME') ?: 'defaultdb');
define('DB_PORT', $_ENV['DB_PORT'] ?? getenv('DB_PORT') ?: 26594);

// Timezone
date_default_timezone_set('America/Sao_Paulo');
?>
