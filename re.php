<?php
/**
 * Redirect Only If Not Empty
 * - Se > 0 bytes: Redireciona para o arquivo
 * - Se 0 bytes ou não existe: Silêncio total
 */

@ini_set('memory_limit', '512M');

// 1. Configuração de URL e Caminho
$protocol = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')) ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';

$path = '';
if (!empty($_SERVER['PATH_INFO'])) {
    $path = trim($_SERVER['PATH_INFO'], '/');
} else {
    $uri = explode('?', $_SERVER['REQUEST_URI'])[0];
    $path = trim(str_replace($_SERVER['SCRIPT_NAME'], '', $uri), '/');
}

$path = str_replace(['../', '..\\', "\0"], '', urldecode($path));
$localPath = __DIR__ . '/X/' . $path;

// 2. Lógica de Verificação de Tamanho
if (!empty($path) && file_exists($localPath) && !is_dir($localPath)) {
    
    // Verifica se o tamanho é maior que 0
    if (filesize($localPath) > 0) {
        
        // URL direta para o arquivo na pasta X
        $targetUrl = $protocol . $host . '/X/' . str_replace('\\', '/', $path);
        
        // Redireciona via cabeçalho
        header('Location: ' . $targetUrl, true, 302);
        exit;
    }
}

// 3. Se for 0 bytes ou erro, morre sem resposta (layout clean)
http_response_code(404);
exit;
