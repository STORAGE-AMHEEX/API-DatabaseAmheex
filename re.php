<?php
/**
 * Silent Redirector Logic
 * Ação: Redireciona apenas se o arquivo existir. Caso contrário, morre em silêncio.
 */

// 1. Silêncio e Performance
@error_reporting(0);
@ini_set('display_errors', 0);
@ini_set('memory_limit', '64M'); 

// 2. Configuração de Host e Base
$protocol = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')) ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$baseDir = rtrim(str_replace(basename($_SERVER['SCRIPT_NAME']), '', $_SERVER['SCRIPT_NAME']), '/');

// 3. Captura do Path
$path = '';
if (!empty($_SERVER['PATH_INFO'])) {
    $path = trim($_SERVER['PATH_INFO'], '/');
} else {
    $uri = explode('?', $_SERVER['REQUEST_URI'])[0];
    $path = trim(str_replace($_SERVER['SCRIPT_NAME'], '', $uri), '/');
}

// Limpeza de segurança
$path = str_replace(['../', '..\\', "\0"], '', urldecode($path));
$localPath = __DIR__ . '/X/' . $path;

// 4. Validação e Redirecionamento
if (!empty($path) && @file_exists($localPath) && !is_dir($localPath)) {
    
    @clearstatcache(true, $localPath);
    $size = @filesize($localPath);

    // Só redireciona se o arquivo for maior que 0 bytes
    if ($size !== false && $size > 0) {
        
        $targetUrl = $protocol . $host . $baseDir . '/X/' . str_replace('\\', '/', $path);
        
        // Limpa a URL de barras duplas extras
        $targetUrl = preg_replace('/([^:])(\/{2,})/', '$1/', $targetUrl);

        header('Location: ' . $targetUrl, true, 302);
        exit;
    }
}

// 5. Se falhar, termina sem renderizar nada e sem disparar código de erro visível
exit;
