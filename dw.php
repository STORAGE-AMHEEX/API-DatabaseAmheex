<?php
/**
 * Silent Downloader Logic - Corrigido
 */

// 1. Configurações de Segurança e Performance
// Cuidado: 16GB de memória pode travar o servidor se houver muitos acessos.
// Para readfile(), o PHP não carrega o arquivo na memória, então '128M' basta.
@ini_set('memory_limit', '256M'); 
@set_time_limit(0);

// 2. Identifica o arquivo pelo Path
$path = '';
if (!empty($_SERVER['PATH_INFO'])) {
    $path = trim($_SERVER['PATH_INFO'], '/');
} else {
    // Melhor forma de capturar o path após o .php
    $scriptName = $_SERVER['SCRIPT_NAME'];
    $requestUri = explode('?', $_SERVER['REQUEST_URI'])[0];
    $path = str_replace($scriptName, '', $requestUri);
    $path = trim($path, '/');
}

// Higienização rigorosa contra Directory Traversal
$path = str_replace(['../', '..\\', "\0"], '', urldecode($path));
$localPath = __DIR__ . '/X/' . $path;

// 3. Validação de existência e tamanho
if (!empty($path) && file_exists($localPath) && !is_dir($localPath)) {
    
    $size = @filesize($localPath);

    if ($size !== false && $size > 0) {
        
        // Limpa buffers para não corromper o arquivo binário
        while (ob_get_level()) {
            ob_end_clean();
        }

        // 4. Cabeçalhos de Download
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($localPath) . '"');
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
        header('Content-Length: ' . $size);

        // 5. Entrega o arquivo de forma eficiente
        // readfile é melhor que file_get_contents para arquivos grandes
        if (readfile($localPath) === false) {
            http_response_code(500);
        }
        exit;
    }
}

// 6. Fallback caso o arquivo não exista ou tenha 0 bytes
http_response_code(404);
echo "Arquivo não encontrado ou vazio."; // Remova o echo se quiser silêncio total
exit;
