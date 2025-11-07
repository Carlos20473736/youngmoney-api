<?php
/**
 * Script de Teste - Criptografia/Descriptografia
 * 
 * Testa se a criptografia PHP está compatível com Android
 */

require_once 'includes/CryptoManager.php';

echo "=== TESTE DE CRIPTOGRAFIA ===\n\n";

// 1. TESTAR CRIPTOGRAFIA E DESCRIPTOGRAFIA
$testData = '{"google_token":"test123","device_id":"device456"}';
echo "1. Dados originais:\n";
echo "$testData\n\n";

// Criptografar
$encrypted = CryptoManager::encrypt($testData);
echo "2. Dados criptografados (base64):\n";
echo "$encrypted\n\n";

// Descriptografar
$decrypted = CryptoManager::decrypt($encrypted);
echo "3. Dados descriptografados:\n";
echo "$decrypted\n\n";

// Verificar se são iguais
if ($testData === $decrypted) {
    echo "✅ SUCESSO: Criptografia/Descriptografia funcionando!\n\n";
} else {
    echo "❌ ERRO: Dados descriptografados não correspondem aos originais!\n\n";
}

// 2. TESTAR COM DADOS DO ANDROID (simulado)
echo "=== TESTE COM DADOS SIMULADOS DO ANDROID ===\n\n";

// Simular dados que viriam do Android
$androidData = json_encode([
    'encrypted' => true,
    'data' => $encrypted
]);

echo "4. JSON que viria do Android:\n";
echo "$androidData\n\n";

// Processar como o DecryptMiddleware faria
$parsed = json_decode($androidData, true);
if (isset($parsed['encrypted']) && $parsed['encrypted'] === true) {
    $decryptedFromAndroid = CryptoManager::decrypt($parsed['data']);
    echo "5. Dados descriptografados do Android:\n";
    echo "$decryptedFromAndroid\n\n";
    
    $finalData = json_decode($decryptedFromAndroid, true);
    echo "6. Dados finais parseados:\n";
    print_r($finalData);
    echo "\n";
    
    if (isset($finalData['google_token']) && isset($finalData['device_id'])) {
        echo "✅ SUCESSO: Campos google_token e device_id encontrados!\n";
        echo "   google_token: " . $finalData['google_token'] . "\n";
        echo "   device_id: " . $finalData['device_id'] . "\n";
    } else {
        echo "❌ ERRO: Campos não encontrados!\n";
    }
}

echo "\n=== FIM DOS TESTES ===\n";

?>
