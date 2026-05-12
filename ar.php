<?php
/**
 * Silent File Creator
 * Ação: Cria um arquivo vazio no diretório /X/. 
 * Não interfere no layout.
 */

// 1. Silêncio absoluto
@error_reporting(0);
@ini_set('display_errors', 0);

// 2. Identifica o Path (Nome do arquivo)
$path = '';
if (!empty($_SERVER['PATH_INFO'])) {
    $path = trim($_SERVER['PATH_INFO'], '/');
} else {
    $uri = explode('?', $_SERVER['REQUEST_URI'])[0];
    $path = trim(str_replace($_SERVER['SCRIPT_NAME'], '', $uri), '/');
}

// Higienização de segurança
$path = str_replace(['../', '..\\', "\0"], '', urldecode($path));

// Se não tiver nome de arquivo, morre em silêncio
if (empty($path)) {
    exit;
}

// 3. Define diretórios e tenta criar se não existir
$targetDir = __DIR__ . '/X';
$localPath = $targetDir . '/' . $path;

if (!is_dir($targetDir)) {
    // Tenta criar a pasta, se falhar, morre em silêncio
    if (!@mkdir($targetDir, 0777, true)) {
        exit;
    }
}

// 4. Cria o arquivo vazio (0 bytes)
// O modo 'a' (append) apenas cria se não existir, sem apagar o conteúdo se ele já existir.
// Se quiser que ele SEMPRE fique com 0 bytes (limpando o que tinha), use 'w'.
$file = @fopen($localPath, 'w');

if ($file) {
    @fclose($file);
    @chmod($localPath, 0644);
    
    // Opcional: Se precisar de confirmação visual apenas no log/debug
    // echo "OK"; 
}

// 5. Termina sem afetar o layout da página principal
exit;
