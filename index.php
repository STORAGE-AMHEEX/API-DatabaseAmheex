<?php
// =========================================================================
//   API FIREBASE-LIKE LOCAL (SUPORTE COMPLETO A ÁRVORES E SUBPASTAS)
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

// Função recursiva para ler diretórios e montar a árvore JSON estilo Firebase
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
            // Se for pasta, lê recursivamente
            $subArvore = lerDiretorioComoArvore($caminhoItem);
            if (!empty($subArvore)) {
                $resultado[$item] = $subArvore;
            }
        } elseif (pathinfo($item, PATHINFO_EXTENSION) === 'json') {
            // Se for arquivo .json, lê o conteúdo
            $nomeChave = pathinfo($item, PATHINFO_FILENAME);
            $conteudo = file_get_contents($caminhoItem);
            $jsonDecoded = json_decode($conteudo);
            
            $resultado[$nomeChave] = (json_last_error() === JSON_ERROR_NONE) ? $jsonDecoded : $conteudo;
        }
    }
    return empty($resultado) ? null : $resultado;
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
    // Raiz da API: Retorna toda a árvore do diretório X
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

$hasJsonExtension = false;
if (substr(strtolower($fileRelPath), -5) === '.json') {
    $hasJsonExtension = true;
    $fileRelPath = substr($fileRelPath, 0, -5);
}

$fullPathJson = BASE_DIR . '/' . $fileRelPath . '.json';
$fullDirPath = BASE_DIR . '/' . $fileRelPath;

// =========================================================================
// MÉTODO GET: LÊ ARQUIVO OU MONTA ÁRVORE DA PASTA
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // 1. Se existir um arquivo JSON exato para este caminho
    if (file_exists($fullPathJson) && filesize($fullPathJson) > 0) {
        $content = file_get_contents($fullPathJson);
        $jsonDecoded = json_decode($content);
        if (json_last_error() === JSON_ERROR_NONE) {
            echo $content;
        } else {
            echo json_encode($content);
        }
        exit;
    }

    // 2. Se o caminho for uma pasta, monta a árvore de tudo o que está dentro dela
    if (is_dir($fullDirPath)) {
        $arvore = lerDiretorioComoArvore($fullDirPath);
        echo json_encode($arvore, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    echo json_encode(null);
    exit;
}

// =========================================================================
// MÉTODO POST / PUT: GRAVAÇÃO E CRIAÇÃO DE ESTRUTURA
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'PUT') {
    // Define o destino final (prioriza salvar como arquivo .json correspondente à rota)
    $targetPath = $fullPathJson;
    $fileDir = dirname($targetPath);

    if (!is_dir($fileDir)) {
        if (!@mkdir($fileDir, 0777, true)) {
            http_response_code(500);
            echo json_encode(['error' => 'Falha ao criar diretórios. Verifique permissões.']);
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

    $bytesWritten = @file_put_contents($targetPath, $dataToSave, LOCK_EX);

    if ($bytesWritten !== false) {
        @chmod($targetPath, 0777);
        echo $dataToSave;
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Erro ao escrever no arquivo JSON.']);
    }
    exit;
}

// =========================================================================
// MÉTODO DELETE: REMOVE O ARQUIVO OU PASTA CORRESPONDENTE
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    if (file_exists($fullPathJson)) {
        @unlink($fullPathJson);
        echo json_encode(null);
        exit;
    }
    if (is_dir($fullDirPath)) {
        // Remove diretório recursivamente se necessário
        array_map('unlink', glob("$fullDirPath/*.*"));
        @rmdir($fullDirPath);
        echo json_encode(null);
        exit;
    }
    http_response_code(404);
    echo json_encode(null);
    exit;
}

http_response_code(405);
echo json_encode(null);
