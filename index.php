<?php
// =========================================================================
//   API FIREBASE-LIKE LOCAL (BASEADA EM ÁRVORE DE DIRETÓRIOS JSON)
// =========================================================================
@ini_set('memory_limit', '512M');
@ini_set('max_execution_time', '60');

ignore_user_abort(true);
set_time_limit(60);

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
    @mkdir(BASE_DIR, 0755, true);
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

// Remove o nome do script atual do caminho, se presente (ex: /api.php/caminho -> /caminho)
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
if (strpos($requestPath, $scriptName) === 0) {
    $requestPath = substr($requestPath, strlen($scriptName));
} else {
    $scriptDir = dirname($scriptName);
    if ($scriptDir !== '/' && strpos($requestPath, $scriptDir) === 0) {
        $requestPath = substr($requestPath, strlen($scriptDir));
    }
}

// Remove todas as repetições de /X do início para mapear corretamente na BASE_DIR
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

// Força a extensão .json caso o caminho não possua nenhuma extensão
if (pathinfo($fileRelPath, PATHINFO_EXTENSION) === '') {
    $fileRelPath .= '.json';
}

$fullPath = BASE_DIR . '/' . $fileRelPath;
$fileDir = dirname($fullPath);

// =========================================================================
// MÉTODO GET: LÊ O CONTEÚDO DO ARQUIVO OU RETORNA NULL
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!file_exists($fullPath) || filesize($fullPath) === 0) {
        echo json_encode(null);
        exit;
    }

    $content = file_file_get_contents_safe($fullPath);
    $jsonDecoded = json_decode($content);
    
    if (json_last_error() === JSON_ERROR_NONE) {
        echo $content;
    } else {
        echo json_encode($content);
    }
    exit;
}

// Função auxiliar para leitura segura
function file_file_get_contents_safe($filepath) {
    $fp = fopen($filepath, 'r');
    if ($fp) {
        flock($fp, LOCK_SH);
        $content = stream_get_contents($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
        return $content;
    }
    return file_get_contents($filepath);
}

// =========================================================================
// MÉTODO POST / PUT: GRAVAÇÃO DIRETA DOS DADOS (JSON OU RAW)
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'PUT') {
    if (!is_dir($fileDir)) {
        if (!@mkdir($fileDir, 0755, true)) {
            http_response_code(500);
            echo json_encode(['error' => 'Não foi possível criar o diretório. Verifique as permissões.']);
            exit;
        }
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
        echo $dataToSave;
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Erro ao escrever no arquivo. Verifique permissões de escrita.']);
    }
    exit;
}

// =========================================================================
// MÉTODO DELETE: REMOVE O ARQUIVO
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
