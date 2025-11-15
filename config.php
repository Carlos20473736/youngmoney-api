<?php
// Configurações do Banco de Dados usando variáveis de ambiente
define('DB_HOST', $_ENV['DB_HOST'] ?? $_SERVER['DB_HOST'] ?? 'mysql-2bb9a545-project-e36d.b.aivencloud.com');
define('DB_USER', $_ENV['DB_USER'] ?? $_SERVER['DB_USER'] ?? 'avnadmin');
define('DB_PASSWORD', $_ENV['DB_PASSWORD'] ?? $_SERVER['DB_PASSWORD'] ?? '');
define('DB_NAME', $_ENV['DB_NAME'] ?? $_SERVER['DB_NAME'] ?? 'defaultdb');
define('DB_PORT', $_ENV['DB_PORT'] ?? $_SERVER['DB_PORT'] ?? 26594);

// Timezone
date_default_timezone_set('America/Sao_Paulo');
?>
