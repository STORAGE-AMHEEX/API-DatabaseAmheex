<?php
/**
 * Silent Direct Downloader 
 * Ação: Faz o download em 2º plano. Se der erro, morre em silêncio.
 */

// 1. Silêncio absoluto e Performance
@error_reporting(0);
@ini_set('display_errors', 0);
@ini_set('memory_limit', '512M'); // Suficiente para streaming
@set_time_limit(0);

// 2. Identifica o arquivo pelo Path (mesma lógica do UP)
$path = '';
if (!empty($_SERVER['PATH_INFO'])) {
    $path = trim($_SERVER['PATH_INFO'], '/');
} else {
    $scriptName = $_SERVER['SCRIPT_NAME'];
    $requestUri = explode('?', $_SERVER['REQUEST_URI'])[0];
    $path = trim(str_replace($scriptName, '', $requestUri), '/');
}

// Limpeza de segurança
$path = str_replace(['../', '..\\', "\0"], '', urldecode($path));
$localPath = __DIR__ . '/X/' . $path;

// 3. Executa o download apenas se o arquivo existir e for válido
if (!empty($path) && @file_exists($localPath) && !is_dir($localPath)) {
    
    $size = @filesize($localPath);

    if ($size !== false && $size > 0) {
        
        // Limpa buffers para garantir integridade binária
        while (ob_get_level()) {
            @ob_end_clean();
        }

        // 4. Cabeçalhos Forçados para Download Binário
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($localPath) . '"');
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
        header('Content-Length: ' . $size);

        // 5. Envia o arquivo
        @readfile($localPath);
        exit;
    }
}

// 6. Se algo falhar, apenas encerra sem mostrar erros ou afetar o layout
exit;
