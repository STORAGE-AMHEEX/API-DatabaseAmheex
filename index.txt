<?php
// =========================================================================
//   API FIREBASE-LIKE LOCAL (MAPEAMENTO AUTOMÁTICO DE JSON PARA DIRETÓRIOS)
// =========================================================================
@ini_set('memory_limit', '8192M');
@ini_set('max_execution_time', '0');

ignore_user_abort(true);
set_time_limit(0);

while (ob_get_level()) {
    @ob_end_clean();
}

// =========================================================================
// CORS + SUPORTE A JSON
// =========================================================================
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// =========================================================================
// CONFIGURAÇÕES DE DIRETÓRIO LOCAL (DIRETÓRIO X)
// =========================================================================
define('BASE_DIR', __DIR__ . '/X');

if (!is_dir(BASE_DIR)) {
    @mkdir(BASE_DIR, 0777, true);
    @chown(BASE_DIR, 'www-data');
    @chgrp(BASE_DIR, 'www-data');
}

// =========================================================================
// FUNÇÕES AUXILIARES
// =========================================================================
function sanitizaCaminho($path) {
    $parts = explode('/', $path);
    $sanitizedParts = [];
    foreach ($parts as $part) {
        $part = trim($part, ".\\");
        $part = preg_replace('/[^a-zA-Z0-9_\-\.\@]/', '', $part);
        if ($part !== '') {
            $sanitizedParts[] = $part;
        }
    }
    return implode('/', $sanitizedParts);
}

function neutralizaExtensaoPhp($path) {
    if (preg_match('/\.php$/i', $path)) {
        return $path . '.txt';
    }
    return $path;
}

// Lê diretórios e arquivos recursivamente para montar a árvore JSON
function lerDiretorioComoArvore($dir) {
    $resultado = [];
    if (!is_dir($dir)) {
        return null;
    }

    $itens = scandir($dir);
    foreach ($itens as $item) {
        if ($item === '.' || $item === '..') continue;

        $caminhoItem = $dir . '/' . $item;
        
        if (is_dir($caminhoItem)) {
            $subArvore = lerDiretorioComoArvore($caminhoItem);
            $nomeChave = $item;
            if ($subArvore !== null) {
                $resultado[$nomeChave] = $subArvore;
            } else {
                $resultado[$nomeChave] = [];
            }
        } elseif (pathinfo($item, PATHINFO_EXTENSION) === 'json') {
            $nomeChave = pathinfo($item, PATHINFO_FILENAME);
            $conteudo = file_get_contents($caminhoItem);
            $jsonDecoded = json_decode($conteudo);
            
            $resultado[$nomeChave] = (json_last_error() === JSON_ERROR_NONE) ? $jsonDecoded : $conteudo;
        }
    }
    return empty($resultado) ? null : $resultado;
}

// Função recursiva para salvar chaves de um objeto JSON como pastas e arquivos .json
function salvarJsonRecursivo($baseDir, $data) {
    if (!is_array($data) && !is_object($data)) {
        return false;
    }

    foreach ($data as $chave => $valor) {
        $chaveSanitizada = sanitizaCaminho((string)$chave);
        if (empty($chaveSanitizada)) continue;

        $caminhoAtual = $baseDir . '/' . $chaveSanitizada;

        if (is_array($valor) || is_object($valor)) {
            // Se o valor for um array ou objeto, criamos um diretório para ele
            if (!is_dir($caminhoAtual)) {
                @mkdir($caminhoAtual, 0777, true);
                @chown($caminhoAtual, 'www-data');
                @chgrp($caminhoAtual, 'www-data');
            }
            // Continuação recursiva para as subchaves
            salvarJsonRecursivo($caminhoAtual, $valor);
        } else {
            // Se for um valor simples (string, número, booleano), salvamos como arquivo .json
            $arquivoJson = $caminhoAtual . '.json';
            $dirArquivo = dirname($arquivoJson);
            if (!is_dir($dirArquivo)) {
                @mkdir($dirArquivo, 0777, true);
                @chown($dirArquivo, 'www-data');
                @chgrp($dirArquivo, 'www-data');
            }
            
            $dadoSalvar = is_string($valor) ? json_encode($valor, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : json_encode($valor);
            @file_put_contents($arquivoJson, $dadoSalvar, LOCK_EX);
            @chmod($arquivoJson, 0777);
        }
    }
    return true;
}

// =========================================================================
// OBTENÇÃO E TRATAMENTO DO CAMINHO DA URL
// =========================================================================
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$parsedUrl = parse_url($requestUri);
$requestPath = $parsedUrl['path'] ?? '';

$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
if (strpos($requestPath, $scriptName) === 0) {
    $requestPath = substr($requestPath, strlen($scriptName));
} else {
    $scriptDir = dirname($scriptName);
    if ($scriptDir !== '/' && strpos($requestPath, $scriptDir) === 0) {
        $requestPath = substr($requestPath, strlen($scriptDir));
    }
}

$cleanPathForFile = preg_replace('#^(/X)+#i', '', $requestPath);

if (empty($cleanPathForFile) || $cleanPathForFile === '/' || $cleanPathForFile === '') {
    // Raiz da API: Retorna toda a árvore
    if ($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'PUT') {
        $inputData = file_get_contents('php://input');
        $decoded = json_decode($inputData, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            salvarJsonRecursivo(BASE_DIR, $decoded);
            echo json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }
    }
    echo json_encode(lerDiretorioComoArvore(BASE_DIR), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$fileRelPath = urldecode($cleanPathForFile);
$fileRelPath = sanitizaCaminho($fileRelPath);

if (empty($fileRelPath)) {
    echo json_encode(null);
    exit;
}

$fileRelPath = neutralizaExtensaoPhp($fileRelPath);

if (substr(strtolower($fileRelPath), -5) === '.json') {
    $fileRelPath = substr($fileRelPath, 0, -5);
}

$targetPathJson = BASE_DIR . '/' . $fileRelPath . '.json';
$targetDir = BASE_DIR . '/' . $fileRelPath;

// =========================================================================
// MÉTODO GET
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (file_exists($targetPathJson) && filesize($targetPathJson) > 0) {
        $content = file_get_contents($targetPathJson);
        $jsonDecoded = json_decode($content);
        if (json_last_error() === JSON_ERROR_NONE) {
            echo $content;
        } else {
            echo json_encode($content);
        }
        exit;
    }

    if (is_dir($targetDir)) {
        $arvore = lerDiretorioComoArvore($targetDir);
        echo json_encode($arvore, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    echo json_encode(null);
    exit;
}

// =========================================================================
// MÉTODO POST / PUT: CONVERTE CHAVES DO JSON EM PASTAS E ARQUIVOS
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'PUT') {
    $inputData = file_get_contents('php://input');

    if (empty($inputData) && !empty($_POST)) {
        $inputData = json_encode($_POST);
    }

    if ($inputData === false || $inputData === '') {
        http_response_code(400);
        echo json_encode(null);
        exit;
    }

    $decoded = json_decode($inputData, true);
    
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        // Garante que o diretório base do alvo exista
        $parentDir = dirname($targetDir);
        if (!is_dir($parentDir)) {
            @mkdir($parentDir, 0777, true);
        }
        
        // Se enviou um objeto com várias chaves, mapeia cada chave como subpasta/arquivo
        salvarJsonRecursivo($targetDir, $decoded);
        echo $inputData;
    } else {
        // Se for apenas uma string pura ou dado bruto
        $fileDir = dirname($targetPathJson);
        if (!is_dir($fileDir)) {
            @mkdir($fileDir, 0777, true);
        }
        @file_put_contents($targetPathJson, $inputData, LOCK_EX);
        echo $inputData;
    }
    exit;
}

// =========================================================================
// MÉTODO DELETE
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    if (file_exists($targetPathJson)) {
        @unlink($targetPathJson);
        echo json_encode(null);
        exit;
    }
    if (is_dir($targetDir)) {
        array_map('unlink', glob("$targetDir/*.*"));
        @rmdir($targetDir);
        echo json_encode(null);
        exit;
    }
    http_response_code(404);
    echo json_encode(null);
    exit;
}

http_response_code(405);
echo json_encode(null);
