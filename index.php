<?php
// =========================================================================
//   API FIREBASE-LIKE LOCAL (COMPATÍVEL COM PHP 8.2 + DOCKER)
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
    $path = trim($path, "/\\");
    $path = str_replace(['..\\', '../'], '', $path);
    $path = preg_replace('/[^a-zA-Z0-9_\-\.\@\/]/', '', $path);
    $path = preg_replace('#/+#', '/', $path);
    return trim($path, "/\\");
}

function neutralizaExtensaoPhp($path) {
    if (preg_match('/\.php$/i', $path)) {
        return $path . '.txt';
    }
    return $path;
}

// =========================================================================
// OBTENÇÃO E TRATAMENTO DO CAMINHO DA URL
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

$fileRelPath = sanitizaCaminho(urldecode($cleanPathForFile));

if (empty($fileRelPath)) {
    echo json_encode(null);
    exit;
}

$fileRelPath = neutralizaExtensaoPhp($fileRelPath);

if (pathinfo($fileRelPath, PATHINFO_EXTENSION) === '') {
    $fileRelPath .= '.json';
}

$fullPath = BASE_DIR . '/' . $fileRelPath;
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
            echo json_encode(['error' => 'Falha ao criar o diretório X. Verifique permissões.']);
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
