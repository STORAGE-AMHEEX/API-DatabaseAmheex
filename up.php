<?php

include_once "exc_re.php"; 

// 1. Configurações de Performance e Supressão de Erros
@error_reporting(0);
@ini_set('display_errors', 0);
@ini_set('memory_limit', '1024M'); // Suporta arquivos grandes sem estourar a RAM
@set_time_limit(0);

// Cabeçalhos CORS para permitir conexões de qualquer origem (Front-end)
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, Accept");

// Resposta imediata para o "Preflight" do navegador
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// 2. Extração e Sanitização do Path após o Domínio
$path = '';
$scriptName = $_SERVER['SCRIPT_NAME']; 
$requestUri = explode('?', $_SERVER['REQUEST_URI'])[0]; 

if (!empty($_SERVER['PATH_INFO'])) {
    $path = $_SERVER['PATH_INFO'];
} else {
    if (strpos($requestUri, $scriptName) === 0) {
        $path = substr($requestUri, strlen($scriptName));
    } else {
        $path = str_replace(dirname($scriptName), '', $requestUri);
    }
}

$path = trim($path, '/');
// Segurança essencial: remove tentativas de burlar diretórios (Path Traversal)
$path = str_replace(['../', '..\\', "\0"], '', urldecode($path));

// Se o path estiver vazio após a limpeza, define um nome padrão ou impede o acesso
if (empty($path)) {
    http_response_code(400);
    die("Erro: Nenhum caminho de arquivo foi especificado na URL.");
}

$localPath = __DIR__ . '/' . $path;
$method = $_SERVER['REQUEST_METHOD'];

// ==========================================
// ROTA DE UPLOAD ULTRA-AGNÓSTICA (POST)
// ==========================================
if ($method === 'POST') {
    // Cria os diretórios dinamicamente baseados no path fornecido
    $dir = dirname($localPath);
    if (!@is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }

    // Abre o fluxo de entrada bruta (lê texto corrido ou binário puro do HTTP body)
    $input = @fopen("php://input", "rb");
    $output = @fopen($localPath, "wb"); // 'wb' garante gravação binária sem alteração de quebra de linha

    if ($input && $output) {
        // Transferência de dados em blocos de 8KB (Bufferizado/Streaming rápido)
        while ($chunk = @fread($input, 8192)) {
            @fwrite($output, $chunk);
        }
        @fclose($input);
        @fclose($output);

        http_response_code(200);
        die("OK: Arquivo processado e salvo com sucesso em /" . $path);
    } else {
        http_response_code(500);
        die("Erro: Falha no barramento interno de leitura/escrita.");
    }
}

// ==========================================
// ROTA DE LEITURA / DOWNLOAD (GET)
// ==========================================
if ($method === 'GET') {
    if (@file_exists($localPath) && !@is_dir($localPath)) {
        
        @clearstatcache(true, $localPath);
        $size = @filesize($localPath);

        if ($size !== false) {
            // Limpa qualquer buffer residual do PHP para evitar corrupção de bytes
            while (ob_get_level()) {
                @ob_end_clean();
            }

            // Cabeçalhos universais para transferência de dados brutos (Agnóstico)
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream'); // Força a interpretação como fluxo de bytes puro
            header('Content-Disposition: attachment; filename="' . basename($localPath) . '"');
            header('Content-Transfer-Encoding: binary');
            header('Expires: 0');
            header('Cache-Control: no-cache, must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . $size);

            // Transmite o arquivo direto do disco para a rede em chunks de 8KB
            $file = @fopen($localPath, 'rb');
            if ($file) {
                while (!feof($file)) {
                    echo fread($file, 8192);
                    @flush(); // Libera o bloco imediatamente para a rede
                }
                @fclose($file);
            }
            die();
        }
    } else {
        http_response_code(404);
        die("Erro: O arquivo solicitado nao existe neste servidor.");
    }
}
