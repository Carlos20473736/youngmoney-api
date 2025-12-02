<?php
/**
 * Security Headers Configuration
 * 
 * Este arquivo configura todos os headers HTTP de segurança necessários
 * para proteger a API contra ataques comuns.
 * 
 * Headers implementados:
 * - Strict-Transport-Security (HSTS)
 * - X-Content-Type-Options
 * - X-Frame-Options
 * - X-XSS-Protection
 * - Content-Security-Policy (CSP)
 * - Referrer-Policy
 * - Permissions-Policy
 * 
 * @version 1.0.0
 * @date 2025-12-02
 */

/**
 * Define todos os headers de segurança HTTP
 * 
 * Esta função deve ser chamada no início de TODOS os endpoints da API
 * para garantir que as proteções sejam aplicadas.
 */
function setSecurityHeaders() {
    // 1. HSTS (HTTP Strict Transport Security)
    // Força o navegador a usar HTTPS por 1 ano (31536000 segundos)
    // includeSubDomains: aplica a todos os subdomínios
    // preload: permite inclusão na lista de preload do navegador
    header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");
    
    // 2. X-Content-Type-Options
    // Previne MIME type sniffing
    // Força o navegador a respeitar o Content-Type declarado
    header("X-Content-Type-Options: nosniff");
    
    // 3. X-Frame-Options
    // Previne clickjacking attacks
    // DENY: não permite que o site seja exibido em iframes
    // Alternativa: SAMEORIGIN (permite apenas no mesmo domínio)
    header("X-Frame-Options: DENY");
    
    // 4. X-XSS-Protection
    // Ativa proteção XSS do navegador (legacy, mas ainda útil)
    // 1; mode=block: bloqueia a página se XSS for detectado
    header("X-XSS-Protection: 1; mode=block");
    
    // 5. Content-Security-Policy (CSP)
    // Política de segurança de conteúdo
    // default-src 'self': apenas recursos do mesmo domínio
    // script-src 'self': apenas scripts do mesmo domínio
    // object-src 'none': bloqueia plugins (Flash, etc)
    // frame-ancestors 'none': não permite ser incorporado em frames
    // base-uri 'self': previne injeção de <base> tag
    // form-action 'self': formulários só podem enviar para o mesmo domínio
    header("Content-Security-Policy: default-src 'self'; script-src 'self'; object-src 'none'; frame-ancestors 'none'; base-uri 'self'; form-action 'self';");
    
    // 6. Referrer-Policy
    // Controla quanta informação de referência é enviada
    // strict-origin-when-cross-origin: envia origin completa apenas para mesmo domínio
    header("Referrer-Policy: strict-origin-when-cross-origin");
    
    // 7. Permissions-Policy (antigo Feature-Policy)
    // Controla quais APIs do navegador podem ser usadas
    // Desabilita geolocalização, microfone e câmera
    header("Permissions-Policy: geolocation=(), microphone=(), camera=()");
    
    // 8. Remover headers que expõem informações sensíveis
    // X-Powered-By revela a tecnologia usada (PHP, versão, etc)
    header_remove("X-Powered-By");
    
    // 9. CORS Headers (opcional - ajuste conforme necessário)
    // Descomente e configure se precisar permitir requisições cross-origin
    // header("Access-Control-Allow-Origin: https://seu-app.com");
    // header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
    // header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Request-ID");
    // header("Access-Control-Max-Age: 86400");
}

/**
 * Define headers de segurança com CORS habilitado
 * 
 * Use esta função se precisar permitir requisições cross-origin
 * de domínios específicos.
 * 
 * @param string $allowedOrigin Domínio permitido (ex: https://seu-app.com)
 */
function setSecurityHeadersWithCORS($allowedOrigin = '*') {
    // Aplicar headers de segurança padrão
    setSecurityHeaders();
    
    // CORS Headers
    header("Access-Control-Allow-Origin: $allowedOrigin");
    header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Request-ID, X-Device-ID, X-Session-ID");
    header("Access-Control-Max-Age: 86400"); // 24 horas
    
    // Permitir credenciais (cookies, headers de autenticação)
    if ($allowedOrigin !== '*') {
        header("Access-Control-Allow-Credentials: true");
    }
    
    // Tratar requisições OPTIONS (preflight)
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

/**
 * Define headers de segurança específicos para API JSON
 * 
 * Inclui configurações otimizadas para APIs que retornam JSON
 */
function setAPISecurityHeaders() {
    // Headers de segurança padrão
    setSecurityHeaders();
    
    // Content-Type sempre JSON
    header("Content-Type: application/json; charset=utf-8");
    
    // Cache Control para APIs
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Pragma: no-cache");
    header("Expires: 0");
}

/**
 * Verifica se a conexão é HTTPS
 * Redireciona para HTTPS se não for
 */
function enforceHTTPS() {
    if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
        $redirect = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        header('Location: ' . $redirect);
        exit;
    }
}

/**
 * Log de segurança
 * Registra eventos de segurança importantes
 * 
 * @param string $event Tipo de evento
 * @param array $details Detalhes do evento
 */
function logSecurityEvent($event, $details = []) {
    $logEntry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'event' => $event,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'details' => $details
    ];
    
    $logFile = __DIR__ . '/../logs/security.log';
    $logDir = dirname($logFile);
    
    // Criar diretório de logs se não existir
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    
    // Escrever log
    $logLine = json_encode($logEntry) . "\n";
    @file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);
}

// Exemplo de uso:
// require_once __DIR__ . '/security_headers.php';
// setSecurityHeaders();
// ou
// setAPISecurityHeaders();
// ou
// setSecurityHeadersWithCORS('https://seu-app.com');
