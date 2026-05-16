<?php

include_once "exc_re.php"; 

// 1. Configurações de Performance e Supressão de Erros
@error_reporting(0);
@ini_set('display_errors', 0);
@ini_set('memory_limit', '1024M'); 
@set_time_limit(0);

// Cabeçalhos CORS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, Accept");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// 2. Extração e Sanitização do Path após o Domínio
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

// Se o path estiver vazio, morre sem resposta visual prejudicial
if (empty($path)) {
    http_response_code(400);
    exit();
}

$localPath = __DIR__ . '/' . $path;
$method = $_SERVER['REQUEST_METHOD'];

// ==========================================
// ROTA DE UPLOAD (POST) -> Retorna apenas a URL
// ==========================================
if ($method === 'POST') {
    $dir = dirname($localPath);
    if (!@is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }

    $input = @fopen("php://input", "rb");
    $output = @fopen($localPath, "wb"); 

    if ($input && $output) {
        while ($chunk = @fread($input, 8192)) {
            @fwrite($output, $chunk);
        }
        @fclose($input);
        @fclose($output);

        // Monta dinamicamente a URL exata do arquivo para resposta limpa
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
        $host = $_SERVER['HTTP_HOST'];
        
        http_response_code(200);
        // Retorna apenas a URL crua do arquivo
        die($protocol . $host . '/' . $path);
    } else {
        http_response_code(500);
        exit();
    }
}

// ==========================================
// ROTA DE DOWNLOAD (GET) -> Sem resposta se não existir
// ==========================================
if ($method === 'GET') {
    if (@file_exists($localPath) && !@is_dir($localPath)) {
        
        @clearstatcache(true, $localPath);
        $size = @filesize($localPath);

        if ($size !== false) {
            while (ob_get_level()) {
                @ob_end_clean();
            }

            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream'); 
            header('Content-Disposition: attachment; filename="' . basename($localPath) . '"');
            header('Content-Transfer-Encoding: binary');
            header('Expires: 0');
            header('Cache-Control: no-cache, must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . $size);

            $file = @fopen($localPath, 'rb');
            if ($file) {
                while (!feof($file)) {
                    echo fread($file, 8192);
                    @flush(); 
                }
                @fclose($file);
            }
            die();
        }
    } else {
        // Se o caminho não existir, define o status 404 e fecha silenciosamente (Sem texto na tela)
        http_response_code(404);
        exit();
    }
}
