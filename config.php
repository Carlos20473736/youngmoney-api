<?php
// Arquivo de Configuração do Banco de Dados
// As credenciais devem ser definidas como variáveis de ambiente no servidor

define('DB_HOST', getenv('DB_HOST') ?: 'mysql-2bb9a545-project-e36d.b.aivencloud.com');
define('DB_PORT', getenv('DB_PORT') ?: '26594');
define('DB_USER', getenv('DB_USER') ?: 'avnadmin');
define('DB_PASSWORD', getenv('DB_PASSWORD') ?: '');
define('DB_NAME', getenv('DB_NAME') ?: 'defaultdb');

?>
