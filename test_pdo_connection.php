<?php
/**
 * Script de teste para verificar conexão PDO com SSL
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== TESTE DE CONEXÃO PDO ===\n\n";

// 1. Verificar variáveis de ambiente
echo "1. Variáveis de ambiente:\n";
echo "   DB_HOST: " . (getenv('DB_HOST') ?: 'NÃO DEFINIDO') . "\n";
echo "   DB_PORT: " . (getenv('DB_PORT') ?: 'NÃO DEFINIDO') . "\n";
echo "   DB_NAME: " . (getenv('DB_NAME') ?: 'NÃO DEFINIDO') . "\n";
echo "   DB_USER: " . (getenv('DB_USER') ?: 'NÃO DEFINIDO') . "\n";
echo "   DB_PASS: " . (getenv('DB_PASS') ? '***' : 'NÃO DEFINIDO') . "\n\n";

// 2. Tentar conexão PDO
echo "2. Tentando conexão PDO com SSL...\n";

try {
    $pdo = new PDO(
        "mysql:host=" . getenv('DB_HOST') . ";port=" . getenv('DB_PORT') . ";dbname=" . getenv('DB_NAME'),
        getenv('DB_USER'),
        getenv('DB_PASS'),
        [
            PDO::MYSQL_ATTR_SSL_CA => true,
            PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]
    );
    
    echo "   ✅ CONEXÃO PDO SUCESSO!\n\n";
    
    // 3. Testar query
    echo "3. Testando query...\n";
    $stmt = $pdo->query("SELECT 1 as test");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "   ✅ QUERY SUCESSO: " . json_encode($result) . "\n\n";
    
    // 4. Verificar tabela users
    echo "4. Verificando tabela users...\n";
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "   ✅ Total de usuários: " . $result['total'] . "\n\n";
    
    echo "=== TODOS OS TESTES PASSARAM ✅ ===\n";
    
} catch (PDOException $e) {
    echo "   ❌ ERRO PDO: " . $e->getMessage() . "\n";
    echo "   Código: " . $e->getCode() . "\n";
    echo "   Arquivo: " . $e->getFile() . ":" . $e->getLine() . "\n\n";
    echo "=== TESTE FALHOU ❌ ===\n";
}
?>
