<?php

include_once "exc_re.php"; 


// 1. Performance e Supressão de Erros
@error_reporting(0);
@ini_set('display_errors', 0);
@ini_set('memory_limit', '1024M'); // Aumentado para arquivos maiores
@set_time_limit(0);

// 2. Captura do Path
$path = '';
$scriptName = $_SERVER['SCRIPT_NAME']; 
$requestUri = explode('?', $_SERVER['REQUEST_URI'])[0]; 

if (!empty($_SERVER['PATH_INFO'])) {
    $path = $_SERVER['PATH_INFO'];
} else {
    if (strpos($requestUri, $scriptName) === 0) {
        $path = substr($requestUri, strlen($scriptName));
    } else {
        $path = str_replace(dirname($scriptName), '', $requestUri);
    }
}

$path = trim($path, '/');
$path = str_replace(['../', '..\\', "\0"], '', urldecode($path));
$localPath = __DIR__ . '/./' . $path;

// 3. Verificação de existência e validade
if (!empty($path) && @file_exists($localPath) && !@is_dir($localPath)) {
    
    @clearstatcache(true, $localPath);
    $size = @filesize($localPath);

    if ($size !== false) {
        
        // Limpeza agressiva de buffer para iniciar o stream imediatamente
        while (ob_get_level()) {
            @ob_end_clean();
        }

        // 4. Cabeçalhos de Download de Alta Performance
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($localPath) . '"');
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . $size);

        // 5. Entrega otimizada (Uso de buffer de leitura para não sobrecarregar a RAM)
        $file = @fopen($localPath, 'rb');
        if ($file) {
            while (!feof($file)) {
                echo fread($file, 1024 * 8); // Lê em pedaços de 8KB
                @flush(); // Força a saída para o navegador
            }
            @fclose($file);
        }
        
        // Em vez de exit, usamos die() que é o padrão para encerrar streams
        die(); 
    }
}
