<?php
// =========================================================================
//   API FIREBASE-LIKE LOCAL (SUPORTE COMPLETO A SUBPASTAS E .JSON)
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
    // Mantém as barras para criar a árvore de subpastas
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

// =========================================================================
// OBTENÇÃO E TRATAMENTO DO CAMINHO DA URL (ÁRVORE DE SUBPASTAS)
// =========================================================================
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$parsedUrl = parse_url($requestUri);
$requestPath = $parsedUrl['path'] ?? '';

// Remove o nome do script atual ou diretórios base se necessário
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
    echo json_encode(null);
    exit;
}

// Decodifica a URL e sanitiza mantendo a estrutura de subpastas
$fileRelPath = urldecode($cleanPathForFile);
$fileRelPath = sanitizaCaminho($fileRelPath);

if (empty($fileRelPath)) {
    echo json_encode(null);
    exit;
}

$fileRelPath = neutralizaExtensaoPhp($fileRelPath);

// Se a URL já termina com .json, removemos temporariamente para tratar a extensão corretamente
$hasJsonExtension = false;
if (substr(strtolower($fileRelPath), -5) === '.json') {
    $hasJsonExtension = true;
    $fileRelPath = substr($fileRelPath, 0, -5);
}

// Reconstrói o caminho completo: se houver subpastas, a última parte vira o arquivo .json e as anteriores viram diretórios
$pathInfo = pathinfo($fileRelPath);
$dirname = ($pathInfo['dirname'] !== '.' && $pathInfo['dirname'] !== '') ? $pathInfo['dirname'] : '';
$filename = $pathInfo['filename'];

$targetRelativePath = ($dirname !== '' ? $dirname . '/' : '') . $filename . '.json';
$fullPath = BASE_DIR . '/' . $targetRelativePath;
$fileDir = dirname($fullPath);

// =========================================================================
// MÉTODO GET
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!file_exists($fullPath) || filesize($fullPath) === 0) {
        echo json_encode(null);
        exit;
    }

    $fp = fopen($fullPath, 'r');
    if ($fp) {
        flock($fp, LOCK_SH);
        $content = stream_get_contents($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
    } else {
        $content = file_get_contents($fullPath);
    }
    
    $jsonDecoded = json_decode($content);
    if (json_last_error() === JSON_ERROR_NONE) {
        echo $content;
    } else {
        echo json_encode($content);
    }
    exit;
}

// =========================================================================
// MÉTODO POST / PUT
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'PUT') {
    if (!is_dir($fileDir)) {
        if (!@mkdir($fileDir, 0777, true)) {
            http_response_code(500);
            echo json_encode(['error' => 'Falha ao criar o diretório e subpastas em X. Verifique permissões.']);
            exit;
        }
        @chown($fileDir, 'www-data');
        @chgrp($fileDir, 'www-data');
    }

    $inputData = file_get_contents('php://input');

    if (empty($inputData) && !empty($_POST)) {
        $inputData = json_encode($_POST);
    }

    if ($inputData === false || $inputData === '') {
        http_response_code(400);
        echo json_encode(null);
        exit;
    }

    $decoded = json_decode($inputData);
    $dataToSave = (json_last_error() === JSON_ERROR_NONE) ? json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : $inputData;

    $bytesWritten = @file_put_contents($fullPath, $dataToSave, LOCK_EX);

    if ($bytesWritten !== false) {
        @chmod($fullPath, 0777);
        echo $dataToSave;
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Erro ao escrever no arquivo JSON.']);
    }
    exit;
}

// =========================================================================
// MÉTODO DELETE
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    if (file_exists($fullPath)) {
        @unlink($fullPath);
        echo json_encode(null);
        exit;
    }
    http_response_code(404);
    echo json_encode(null);
    exit;
}

http_response_code(405);
echo json_encode(null);
