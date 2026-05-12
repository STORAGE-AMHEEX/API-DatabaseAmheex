<?php
/**
 * Redirect Only If Not Empty - Corrigido
 */

@ini_set('memory_limit', '128M'); // Redirecionamento não gasta quase nada de RAM

// 1. Configuração de URL e Caminho
$protocol = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')) ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';

// Captura o diretório base onde este script está (importante se não estiver na raiz)
$baseDir = str_replace(basename($_SERVER['SCRIPT_NAME']), '', $_SERVER['SCRIPT_NAME']);
$baseDir = rtrim($baseDir, '/');

$path = '';
if (!empty($_SERVER['PATH_INFO'])) {
    $path = trim($_SERVER['PATH_INFO'], '/');
} else {
    $uri = explode('?', $_SERVER['REQUEST_URI'])[0];
    $scriptName = $_SERVER['SCRIPT_NAME'];
    $path = trim(str_replace($scriptName, '', $uri), '/');
}

// Limpeza de segurança
$path = str_replace(['../', '..\\', "\0"], '', urldecode($path));
$localPath = __DIR__ . '/X/' . $path;

// 2. Lógica de Verificação
if (!empty($path) && file_exists($localPath) && !is_dir($localPath)) {
    
    // clearstatcache garante que o PHP não leia um tamanho antigo em cache
    clearstatcache(true, $localPath);
    $size = @filesize($localPath);

    if ($size !== false && $size > 0) {
        
        // 3. Montagem da URL de Redirecionamento
        // Usamos o $baseDir para garantir que a pasta /X/ seja encontrada em relação ao script
        $targetUrl = $protocol . $host . $baseDir . '/X/' . str_replace('\\', '/', $path);
        
        // Remove barras duplas acidentais (exceto a do protocolo)
        $targetUrl = preg_replace('/([^:])(\/{2,})/', '$1/', $targetUrl);

        header('Location: ' . $targetUrl, true, 302);
        exit;
    }
}

// 4. Fallback (0 bytes ou não existe)
http_response_code(404);
exit;
