<?php
// Ativa a exibição de erros para debug
ini_set('display_errors', 1);
error_reporting(E_ALL);

include_once "up.php";
flush(); // Força o envio da saída para o navegador/cliente
sleep(1);

include_once "ar.php";
flush();
sleep(1);

// ... restante do código



// 1. Silêncio e Performance
@error_reporting(0);
@ini_set('display_errors', 0);
@ini_set('memory_limit', '64M'); 

// 2. Configuração de Host e Base
$protocol = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')) ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scriptName = $_SERVER['SCRIPT_NAME'];
$baseDir = rtrim(str_replace(basename($scriptName), '', $scriptName), '/');

// 3. Captura Precisa do Path (Compatível com vários servidores)
$path = '';
$requestUri = explode('?', $_SERVER['REQUEST_URI'])[0];

if (!empty($_SERVER['PATH_INFO'])) {
    $path = $_SERVER['PATH_INFO'];
} else {
    // Extração manual caso o PATH_INFO esteja desativado
    if (strpos($requestUri, $scriptName) === 0) {
        $path = substr($requestUri, strlen($scriptName));
    } else {
        $path = str_replace(dirname($scriptName), '', $requestUri);
    }
}

$path = trim($path, '/');

// Limpeza de segurança contra Directory Traversal
$path = str_replace(['../', '..\\', "\0"], '', urldecode($path));
$localPath = __DIR__ . '/X/' . $path;

// 4. Validação de Conteúdo e Redirecionamento
if (!empty($path) && @file_exists($localPath) && !is_dir($localPath)) {
    
    // Atualiza o cache de status para ler o tamanho real do arquivo
    @clearstatcache(true, $localPath);
    $size = @filesize($localPath);

    // Só redireciona se o arquivo já tiver recebido dados (maior que >0 bytes)
    if ($size !== false && $size >= 0) {
        
        $targetUrl = $protocol . $host . $baseDir . '/X/' . str_replace('\\', '/', $path);
        
        // Remove possíveis barras duplas na URL (evita //X//arquivo.txt)
        $targetUrl = preg_replace('/([^:])(\/{2,})/', '$1/', $targetUrl);

        header('Location: ' . $targetUrl, true, 302);
        
    }
}

