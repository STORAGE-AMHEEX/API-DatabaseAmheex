<?php
/**
 * Silent Downloader Logic
 * - Verifica tamanho (ignora 0 bytes)
 * - Força download via headers
 * - Sem output visual, apenas binário
 */

@ini_set('memory_limit', '16000M');
@set_time_limit(0);

// 1. Identifica o arquivo pelo Path
$path = '';
if (!empty($_SERVER['PATH_INFO'])) {
    $path = trim($_SERVER['PATH_INFO'], '/');
} else {
    $uri = explode('?', $_SERVER['REQUEST_URI'])[0];
    $path = trim(str_replace($_SERVER['SCRIPT_NAME'], '', $uri), '/');
}

$path = str_replace(['../', '..\\', "\0"], '', urldecode($path));
$localPath = __DIR__ . '/X/' . $path;

// 2. Validação de existência e tamanho
if (!empty($path) && file_exists($localPath) && !is_dir($localPath)) {
    
    $size = filesize($localPath);

    // Só prossegue se for maior que 0 bytes
    if ($size > 0) {
        
        // Limpa qualquer lixo no buffer de saída
        if (ob_get_level()) ob_end_clean();

        // 3. Cabeçalhos de Download
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($localPath) . '"');
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . $size);

        // 4. Entrega o arquivo e para a execução
        readfile($localPath);
        exit;
    }
}

// Se chegar aqui (0 bytes ou não existe), morre em silêncio
http_response_code(404);
exit;
