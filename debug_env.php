<?php
/**
 * Script de Debug - Verificar Variáveis de Ambiente
 * 
 * Este script verifica se as variáveis de ambiente estão disponíveis no container
 */

header('Content-Type: text/plain; charset=utf-8');

echo "=== DEBUG DE VARIÁVEIS DE AMBIENTE ===\n\n";

// 1. Verificar variáveis específicas do banco de dados
echo "1. VARIÁVEIS DE BANCO DE DADOS:\n";
echo "--------------------------------\n";

$db_vars = ['DB_HOST', 'DB_PORT', 'DB_USER', 'DB_PASSWORD', 'DB_NAME', 'SERVER_ENCRYPTION_KEY'];

foreach ($db_vars as $var) {
    $value_env = $_ENV[$var] ?? null;
    $value_getenv = getenv($var);
    
    if ($var === 'DB_PASSWORD' || $var === 'SERVER_ENCRYPTION_KEY') {
        $display_env = $value_env ? 'SET (hidden)' : 'NOT SET';
        $display_getenv = $value_getenv ? 'SET (hidden)' : 'NOT SET';
    } else {
        $display_env = $value_env ?: 'NOT SET';
        $display_getenv = $value_getenv ?: 'NOT SET';
    }
    
    echo sprintf("%-25s | \$_ENV: %-20s | getenv(): %s\n", $var, $display_env, $display_getenv);
}

echo "\n";

// 2. Verificar se config.php está carregando corretamente
echo "2. TESTE DE CARREGAMENTO DO CONFIG.PHP:\n";
echo "---------------------------------------\n";

try {
    require_once __DIR__ . '/config.php';
    
    echo "✅ config.php carregado com sucesso\n\n";
    
    echo "Constantes definidas:\n";
    echo "DB_HOST: " . (defined('DB_HOST') ? (DB_HOST ?: 'EMPTY') : 'NOT DEFINED') . "\n";
    echo "DB_PORT: " . (defined('DB_PORT') ? (DB_PORT ?: 'EMPTY') : 'NOT DEFINED') . "\n";
    echo "DB_USER: " . (defined('DB_USER') ? (DB_USER ?: 'EMPTY') : 'NOT DEFINED') . "\n";
    echo "DB_PASSWORD: " . (defined('DB_PASSWORD') ? (DB_PASSWORD ? 'SET (hidden)' : 'EMPTY') : 'NOT DEFINED') . "\n";
    echo "DB_NAME: " . (defined('DB_NAME') ? (DB_NAME ?: 'EMPTY') : 'NOT DEFINED') . "\n";
    
} catch (Exception $e) {
    echo "❌ Erro ao carregar config.php: " . $e->getMessage() . "\n";
}

echo "\n";

// 3. Testar conexão com banco de dados
echo "3. TESTE DE CONEXÃO COM BANCO DE DADOS:\n";
echo "---------------------------------------\n";

if (defined('DB_HOST') && defined('DB_USER') && defined('DB_PASSWORD') && defined('DB_NAME')) {
    try {
        require_once __DIR__ . '/database.php';
        
        $conn = getDbConnection();
        
        if ($conn) {
            echo "✅ Conexão com banco de dados estabelecida com sucesso!\n";
            
            // Testar query simples
            $result = $conn->query("SELECT 1 as test");
            if ($result) {
                echo "✅ Query de teste executada com sucesso!\n";
                $result->close();
            }
            
            $conn->close();
        } else {
            echo "❌ Falha ao conectar ao banco de dados\n";
        }
        
    } catch (Exception $e) {
        echo "❌ Erro na conexão: " . $e->getMessage() . "\n";
    }
} else {
    echo "❌ Constantes de banco de dados não estão definidas corretamente\n";
}

echo "\n";

// 4. Informações do ambiente PHP
echo "4. INFORMAÇÕES DO AMBIENTE PHP:\n";
echo "-------------------------------\n";
echo "PHP Version: " . phpversion() . "\n";
echo "SAPI: " . php_sapi_name() . "\n";
echo "Server Software: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'NOT SET') . "\n";
echo "Document Root: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'NOT SET') . "\n";

echo "\n";

// 5. Listar TODAS as variáveis de ambiente (filtradas)
echo "5. TODAS AS VARIÁVEIS DE AMBIENTE (filtradas):\n";
echo "----------------------------------------------\n";

$all_env = getenv();
$filtered_env = [];

foreach ($all_env as $key => $value) {
    // Mostrar apenas variáveis relevantes
    if (
        strpos($key, 'DB_') === 0 || 
        strpos($key, 'SERVER_') === 0 ||
        strpos($key, 'RAILWAY_') === 0 ||
        strpos($key, 'PATH') === 0 ||
        strpos($key, 'HOME') === 0
    ) {
        if (strpos($key, 'PASSWORD') !== false || strpos($key, 'KEY') !== false) {
            $filtered_env[$key] = 'HIDDEN';
        } else {
            $filtered_env[$key] = $value;
        }
    }
}

foreach ($filtered_env as $key => $value) {
    echo "$key: $value\n";
}

echo "\n=== FIM DO DEBUG ===\n";
?>
