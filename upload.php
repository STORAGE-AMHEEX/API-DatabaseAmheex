<?php
/**
 * Cyber-Sync Local Storage Only
 * Apenas salvamento local na pasta X/
 */

@ini_set('memory_limit', '16000M');
@set_time_limit(0);

header("Access-Control-Allow-Origin: *");
header("X-Powered-By: Cyber-Local-Writer");

// 1. Identificação do Path (O que vem depois do domínio)
$path = ltrim($_SERVER['PATH_INFO'] ?? '', '/');
if (!$path) {
    $uri = explode('?', $_SERVER['REQUEST_URI'])[0];
    $path = ltrim(str_replace($_SERVER['SCRIPT_NAME'], '', $uri), '/');
}

// Erro se não houver um nome de arquivo/caminho na URL
if (!$path || $path === 'index.php') exit("LOCAL_ERROR: NO_PATH");

// 2. Definição do destino na pasta raiz 'X'
$localPath = __DIR__ . '/X/' . $path;
$localDir = dirname($localPath);

// 3. Processamento de Upload Local
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Pega o conteúdo bruto do upload
    $inputData = file_get_contents('php://input');
    
    if (empty($inputData)) exit("ERROR: EMPTY_DATA");

    // Cria as pastas se não existirem
    if (!is_dir($localDir)) @mkdir($localDir, 0777, true);

    // Salva o arquivo fisicamente
    if (@file_put_contents($localPath, $inputData)) {
        echo "SUCCESS: Saved to /X/$path";
    } else {
        http_response_code(500);
        echo "ERROR: PERMISSION_DENIED_ON_SERVER";
    }
    exit;
}

// 4. Caso tente acessar via GET, apenas verifica se o arquivo existe
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (file_exists($localPath)) {
        header("Content-Type: application/octet-stream");
        readfile($localPath);
    } else {
        http_response_code(404);
        echo "ERROR: FILE_NOT_FOUND_IN_X";
    }
    exit;
}
