<?php


// 1. Silêncio absoluto e Performance
@error_reporting(0);
@ini_set('display_errors', 0);
@ini_set('memory_limit', '512M'); 
@set_time_limit(0);

// 2. Captura Robusta do Path (Pega o que vem depois do .php)
$path = '';
$scriptName = $_SERVER['SCRIPT_NAME']; // ex: /dw.php
$requestUri = explode('?', $_SERVER['REQUEST_URI'])[0]; // ex: /dw.php/arquivo.zip

if (!empty($_SERVER['PATH_INFO'])) {
    $path = $_SERVER['PATH_INFO'];
} else {
    // Se o PATH_INFO falhar, extrai manualmente comparando a URI com o nome do Script
    if (strpos($requestUri, $scriptName) === 0) {
        $path = substr($requestUri, strlen($scriptName));
    } else {
        // Fallback para subdiretórios
        $path = str_replace(dirname($scriptName), '', $requestUri);
    }
}

$path = trim($path, '/');

// Limpeza de segurança contra Directory Traversal
$path = str_replace(['../', '..\\', "\0"], '', urldecode($path));
$localPath = __DIR__ . '/X/' . $path;

// 3. Condição: Existe, não é pasta e tem tamanho maior que 0
if (!empty($path) && @file_exists($localPath) && !is_dir($localPath)) {
    
    @clearstatcache(true, $localPath); // Força atualização do status do arquivo
    $size = @filesize($localPath);

    if ($size !== false && $size >= 0) {
        
        // Limpa buffers para não corromper o binário e não injetar HTML do layout
        while (ob_get_level()) {
            @ob_end_clean();
        }

        // 4. Cabeçalhos de Download Binário
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($localPath) . '"');
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
        header('Content-Length: ' . $size);

        // 5. Entrega o arquivo e para tudo
        if (@readfile($localPath) !== false) {
            
        }
    }
}


include_once "re.php";

