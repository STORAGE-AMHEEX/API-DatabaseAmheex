<?php

include_once "exc_dw.php"; 

// 1. Silêncio absoluto
@error_reporting(0);
@ini_set('display_errors', 0);

// 2. Captura Robusta do Path (Pega tudo após o .php)
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

// Limpeza de segurança
$path = str_replace(['../', '..\\', "\0"], '', urldecode($path));

if (empty($path)) {
    
}

// 3. Define diretório base e caminho completo
$baseDir = __DIR__ . '/X';
$localPath = $baseDir . '/' . $path;
$subFolder = dirname($localPath);

// 4. Cria a estrutura de pastas necessária
// Se o path for "pasta1/arquivo.txt", ele cria a "X/pasta1" primeiro.
if (!is_dir($subFolder)) {
    if (!@mkdir($subFolder, 0777, true)) {
        // Se falhar ao criar pasta, morre para não dar erro no fopen
        
    }
}

// 5. Cria o arquivo vazio (0 bytes)
// Usamos 'w' para garantir que ele seja criado ou resetado para 0 bytes
$file = @fopen($localPath, 'w');

if ($file) {
    @fclose($file);
    @chmod($localPath, 0666); // Permissão de escrita/leitura
}

