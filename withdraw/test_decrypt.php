<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../includes/CryptoManager.php';

// Capturar input
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);

$result = [
    'success' => true,
    'raw_input' => $rawInput,
    'decoded_input' => $input,
    'tests' => []
];

// Teste 1: Verificar se está criptografado
if (isset($input['encrypted']) && $input['encrypted'] === true) {
    $result['tests'][] = 'Input is marked as encrypted';
    
    if (isset($input['data'])) {
        $result['tests'][] = 'Data field exists';
        $result['encrypted_data_length'] = strlen($input['data']);
        
        // Teste 2: Tentar descriptografar
        $decrypted = CryptoManager::decrypt($input['data']);
        
        if ($decrypted === false) {
            $result['tests'][] = 'FAILED: CryptoManager::decrypt returned false';
            $result['error'] = 'Decryption failed';
            
            // Tentar descobrir por quê
            $combined = base64_decode($input['data']);
            if ($combined === false) {
                $result['tests'][] = 'FAILED: base64_decode failed';
            } else {
                $result['tests'][] = 'base64_decode successful';
                $result['decoded_length'] = strlen($combined);
                
                if (strlen($combined) < 16) {
                    $result['tests'][] = 'FAILED: Data too short (need at least 16 bytes for IV)';
                } else {
                    $result['tests'][] = 'Data length OK';
                    
                    // Tentar descriptografar manualmente
                    $iv = substr($combined, 0, 16);
                    $encrypted = substr($combined, 16);
                    $key = "young_money_crypto_key_2025_v1!!";
                    
                    $decrypted_manual = openssl_decrypt(
                        $encrypted,
                        'AES-256-CBC',
                        $key,
                        OPENSSL_RAW_DATA,
                        $iv
                    );
                    
                    if ($decrypted_manual === false) {
                        $result['tests'][] = 'FAILED: openssl_decrypt failed';
                        $result['openssl_error'] = openssl_error_string();
                    } else {
                        $result['tests'][] = 'SUCCESS: Manual decryption worked!';
                        $result['decrypted'] = $decrypted_manual;
                        $result['decrypted_json'] = json_decode($decrypted_manual, true);
                    }
                }
            }
        } else {
            $result['tests'][] = 'SUCCESS: Decryption successful';
            $result['decrypted'] = $decrypted;
            $result['decrypted_json'] = json_decode($decrypted, true);
        }
    } else {
        $result['tests'][] = 'FAILED: No data field';
    }
} else {
    $result['tests'][] = 'Input is NOT encrypted';
}

echo json_encode($result, JSON_PRETTY_PRINT);
?>
