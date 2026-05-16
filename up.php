<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

define('BASE_DIR', realpath(__DIR__) . '/X');
if (!is_dir(BASE_DIR)) mkdir(BASE_DIR, 0777, true);

function response($status, $data) {
    header('Content-Type: application/json');
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function sanitizaCaminho($path) {
    $path = ltrim($path, '/');
    $path = preg_replace('/[^a-zA-Z0-9_\-\.\@\/]/', '', $path);
    return $path;
}

// Função para forçar redirecionamento tanto por HTTP quanto por Fallback em HTML/JS
function redirecionarPara($url) {
    // Limpa buffers anteriores para evitar conflito de Headers
    if (ob_get_length()) ob_clean(); 
    
    header("Location: " . $url, true, 301);
    
    // Fallback caso o cliente ignore o Header Location (Garante redirecionamento no navegador)
    header('Content-Type: text/html; charset=utf-8');
    echo "<!DOCTYPE html><html><head><meta http-equiv='refresh' content='0;url={$url}'></head>";
    echo "<body><script>window.location.href='{$url}';</script></body></html>";
    exit;
}

$request_path = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$fileRelPath = sanitizaCaminho($request_path);

// Se a URL for vazia (ex: apenas a raiz do script), evita processar download indevido
if ($fileRelPath === '') {
    response(400, ["erro" => "Nenhum arquivo ou rota especificada na URL."]);
}

$fullPath = BASE_DIR . '/' . $fileRelPath;
$realFullPath = realpath(dirname($fullPath)) . '/' . basename($fullPath);

// 🔒 PROTEÇÃO EXTREMA: Garante que o arquivo solicitado está obrigatoriamente dentro da pasta X
if (strpos($realFullPath, BASE_DIR) !== 0) {
    response(403, ["erro" => "Acesso proibido. O arquivo solicitado está fora do diretório autorizado."]);
}

// ====== DOWNLOAD DE ARQUIVO EXISTENTE ======
if ($_SERVER['REQUEST_METHOD'] === 'GET' && file_exists($fullPath) && is_file($fullPath)) {
    if (ob_get_length()) ob_clean();
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . basename($fullPath) . '"');
    header('Content-Length: ' . filesize($fullPath));
    readfile($fullPath);
    exit;
}

// Determinar o protocolo dinamicamente (HTTP ou HTTPS)
$protocolo = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$dominio = $_SERVER['HTTP_HOST'];

// Pasta do script para gerar a URL correta apontando para a pasta X
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
if ($scriptDir !== '/') $scriptDir = rtrim($scriptDir, '/') . '/';
$urlFinal = $protocolo . $dominio . $scriptDir . 'X/' . $fileRelPath;

// ====== UPLOAD DE ARQUIVO ======
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['file'])) {
    if ($_FILES['file']['error'] !== 0) {
        response(400, ["erro" => "Erro no upload: " . $_FILES['file']['error']]);
    }

    $arquivoTamanho = $_FILES['file']['size'];
    $espacoLivre = disk_free_space(BASE_DIR);
    if ($arquivoTamanho > $espacoLivre) {
        response(507, [
            "erro" => "Espaço insuficiente no servidor.",
            "tamanho_arquivo" => $arquivoTamanho,
            "espaco_livre" => $espacoLivre
        ]);
    }

    $dir = dirname($fullPath);
    if (!is_dir($dir)) mkdir($dir, 0777, true);

    if (is_dir($fullPath)) {
        response(400, ["erro" => "O caminho especificado é um diretório, não um arquivo."]);
    }

    if (move_uploaded_file($_FILES['file']['tmp_name'], $fullPath)) {
        redirecionarPara($urlFinal);
    } else {
        response(500, ["erro" => "Falha ao salvar arquivo localmente."]);
    }
}

// ====== UPLOAD DE TEXTO (OU DOWNLOAD DE LINK) ======
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_FILES['file'])) {
    $rawData = trim(file_get_contents("php://input"));
    if (empty($rawData)) {
        response(400, ["erro" => "Nenhum conteúdo de texto recebido."]);
    }

    $dir = dirname($fullPath);
    if (!is_dir($dir)) mkdir($dir, 0777, true);

    if (is_dir($fullPath)) {
        response(400, ["erro" => "O caminho especificado é um diretório, não um arquivo."]);
    }

    // Se o texto começar com "https//", trata como URL e faz download
    if (preg_match('/^https\/\//i', $rawData)) {
        $url = str_replace('https//', 'https://', $rawData);

        $conteudo = @file_get_contents($url);
        if ($conteudo === false) {
            response(400, ["erro" => "Falha ao baixar conteúdo da URL: {$url}"]);
        }

        if (file_put_contents($fullPath, $conteudo) === false) {
            response(500, ["erro" => "Falha ao salvar conteúdo baixado localmente."]);
        }

        redirecionarPara($urlFinal);
    }

    // Caso contrário, salva texto normalmente
    if (file_put_contents($fullPath, $rawData) !== false) {
        redirecionarPara($urlFinal);
    } else {
        response(500, ["erro" => "Falha ao salvar o texto localmente."]);
    }
}

response(400, ["erro" => "Arquivo não encontrado ou nenhuma operação válida especificada."]);
?>
