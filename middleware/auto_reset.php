<?php
/**
 * Middleware de Reset Automático do Ranking
 * 
 * Verifica se passou do horário configurado e faz reset automático
 * Deve ser incluído nos endpoints principais da API
 */

function checkAndResetRanking($conn) {
    try {
        // Buscar horário de reset configurado
        $stmt = $conn->prepare("
            SELECT setting_value 
            FROM system_settings 
            WHERE setting_key = 'reset_time'
        ");
        $stmt->execute();
        $result = $stmt->get_result();
        
        $resetTime = '21:00'; // Valor padrão
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $resetTime = $row['setting_value'];
        }
        
        // Extrair hora e minuto
        list($resetHour, $resetMinute) = explode(':', $resetTime);
        $resetHour = (int)$resetHour;
        $resetMinute = (int)$resetMinute;
        
        // Pegar hora atual do servidor (GMT-3 / America/Sao_Paulo)
        date_default_timezone_set('America/Sao_Paulo');
        $currentHour = (int)date('H');
        $currentMinute = (int)date('i');
        $currentDate = date('Y-m-d');
        
        // Buscar última vez que foi feito reset
        $stmt = $conn->prepare("
            SELECT setting_value 
            FROM system_settings 
            WHERE setting_key = 'last_reset_date'
        ");
        $stmt->execute();
        $result = $stmt->get_result();
        
        $lastResetDate = null;
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $lastResetDate = $row['setting_value'];
        }
        
        // Verificar se precisa resetar
        $needsReset = false;
        
        // Caso 1: Nunca foi resetado
        if ($lastResetDate === null) {
            // Resetar se já passou do horário hoje
            $currentTimeInMinutes = ($currentHour * 60) + $currentMinute;
            $resetTimeInMinutes = ($resetHour * 60) + $resetMinute;
            $needsReset = ($currentTimeInMinutes >= $resetTimeInMinutes);
        }
        // Caso 2: Já foi resetado antes
        else {
            // Verificar se é um dia diferente E já passou do horário
            if ($lastResetDate !== $currentDate) {
                $currentTimeInMinutes = ($currentHour * 60) + $currentMinute;
                $resetTimeInMinutes = ($resetHour * 60) + $resetMinute;
                $needsReset = ($currentTimeInMinutes >= $resetTimeInMinutes);
            }
        }
        
        // Fazer reset se necessário
        if ($needsReset) {
            $conn->begin_transaction();
            
            try {
                // Resetar daily_points
                $stmt = $conn->prepare("UPDATE users SET daily_points = 0");
                $stmt->execute();
                
                // Atualizar última data de reset
                if ($lastResetDate === null) {
                    $stmt = $conn->prepare("
                        INSERT INTO system_settings (setting_key, setting_value) 
                        VALUES ('last_reset_date', ?)
                    ");
                } else {
                    $stmt = $conn->prepare("
                        UPDATE system_settings 
                        SET setting_value = ? 
                        WHERE setting_key = 'last_reset_date'
                    ");
                }
                $stmt->bind_param("s", $currentDate);
                $stmt->execute();
                
                // Registrar log
                $action = 'auto_reset_ranking';
                $details = json_encode([
                    'reset_time_configured' => $resetTime,
                    'reset_date' => $currentDate,
                    'current_time' => date('H:i:s')
                ]);
                
                $stmt = $conn->prepare("
                    INSERT INTO admin_logs (action, details, created_at) 
                    VALUES (?, ?, NOW())
                ");
                $stmt->bind_param("ss", $action, $details);
                $stmt->execute();
                
                $conn->commit();
                
                error_log("Auto-reset do ranking executado: " . $currentDate . " às " . date('H:i:s'));
                
            } catch (Exception $e) {
                $conn->rollback();
                error_log("Erro ao fazer auto-reset: " . $e->getMessage());
            }
        }
        
    } catch (Exception $e) {
        error_log("Erro no middleware de auto-reset: " . $e->getMessage());
    }
}
?>
