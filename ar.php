<?php
/**
 * Create Empty File Logic
 * - Cria um arquivo de 0 bytes no diretório /X/
 * - Cria a pasta /X/ automaticamente se não existir
 */

// 1. Identifica o Path (mesma lógica dos anteriores)
$path = '';
if (!empty($_SERVER['PATH_INFO'])) {
    $path = trim($_SERVER['PATH_INFO'], '/');
} else {
    $uri = explode('?', $_SERVER['REQUEST_URI'])[0];
    $path = trim(str_replace($_SERVER['SCRIPT_NAME'], '', $uri), '/');
}

// Higienização de segurança
$path = str_replace(['../', '..\\', "\0"], '', urldecode($path));

if (empty($path)) {
    http_response_code(400);
    echo "Erro: Nome do arquivo não especificado.";
    exit;
}

// 2. Define o diretório e o caminho completo
$targetDir = __DIR__ . '/X';
$localPath = $targetDir . '/' . $path;

// 3. Verifica/Cria a pasta /X/
if (!is_dir($targetDir)) {
    if (!mkdir($targetDir, 0755, true)) {
        http_response_code(500);
        echo "Erro: Não foi possível criar o diretório /X/.";
        exit;
    }
}

// 4. Cria o arquivo com 0 bytes
// 'w' abre para escrita, cria o arquivo se não existir e limpa se já existir
$file = @fopen($localPath, 'w');

if ($file) {
    fclose($file);
    http_response_code(201); // Created
    echo "Sucesso: Arquivo de 0 bytes criado em /X/$path";
} else {
    http_response_code(500);
    echo "Erro: Não foi possível criar o arquivo. Verifique permissões de pasta.";
}

exit;
