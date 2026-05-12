<?php
/**
 * Cyber-Sync Local Storage + Auto Database Update
 */

@ini_set('memory_limit', '16000M');
@set_time_limit(0);

header("Access-Control-Allow-Origin: *");
header("X-Powered-By: Cyber-Local-Writer");

// 1. Identificação do Path
$path = ltrim($_SERVER['PATH_INFO'] ?? '', '/');
if (!$path) {
    $uri = explode('?', $_SERVER['REQUEST_URI'])[0];
    $path = ltrim(str_replace($_SERVER['SCRIPT_NAME'], '', $uri), '/');
}

if (!$path || $path === 'index.php') exit("LOCAL_ERROR: NO_PATH");

// 2. Definição do destino
$localPath = __DIR__ . '/X/' . $path;
$localDir = dirname($localPath);

// 3. Processamento de Upload Local
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $inputData = file_get_contents('php://input');
    
    if (empty($inputData)) exit("ERROR: EMPTY_DATA");

    if (!is_dir($localDir)) @mkdir($localDir, 0777, true);

    // Salva o arquivo fisicamente
    if (@file_put_contents($localPath, $inputData)) {
        
        // --- NOVIDADE: EXECUÇÃO DO DATABASE.PHP ---
        // 'cd' garante que estamos na pasta correta antes de rodar o comando
        $cmd = "cd " . __DIR__ . " && php database.php > /dev/null 2>&1 &";
        shell_exec($cmd);
        // ------------------------------------------

        echo "SUCCESS: Saved to /X/$path and triggered database update";
    } else {
        http_response_code(500);
        echo "ERROR: PERMISSION_DENIED_ON_SERVER";
    }
    exit;
}

// 4. Leitura via GET
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
