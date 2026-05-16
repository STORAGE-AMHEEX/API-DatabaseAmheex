<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

header('Content-Type: application/json');

// Pasta base para armazenar arquivos
define('BASE_DIR', __DIR__ . '/#'); // criar a pasta 'uploads' na raiz

function response($status, $data) {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// Sanitiza o caminho removendo caracteres estranhos
function sanitizaCaminho($path) {
    $path = ltrim($path, '/');
    $path = preg_replace('/[^a-zA-Z0-9_\-\.\@\/]/', '', $path);
    return $path;
}

// ================= PATH e URL via GET =================
$getPath = isset($_GET['path']) ? sanitizaCaminho($_GET['path']) : '';
$getUrl  = isset($_GET['url']) ? $_GET['url'] : '';

// Determina o caminho final do arquivo
if ($getPath !== '') {
    $fullPath = BASE_DIR . '/' . $getPath;
    $fileRelPath = $getPath;
} else {
    $request_path = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
    $fileRelPath = sanitizaCaminho($request_path);
    $fullPath = BASE_DIR . '/' . $fileRelPath;
}

// ====== DOWNLOAD ======
if ($_SERVER['REQUEST_METHOD'] === 'GET' && file_exists($fullPath) && is_file($fullPath)) {
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . basename($fullPath) . '"');
    header('Content-Length: ' . filesize($fullPath));
    readfile($fullPath);
    exit;
}

// ====== UPLOAD via arquivo enviado ======
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['file'])) {
    if ($_FILES['file']['error'] !== 0) {
        response(400, ["erro" => "Erro no upload: " . $_FILES['file']['error']]);
    }

    if ($fileRelPath === '') {
        response(400, ["erro" => "Defina o caminho do arquivo na URL. Ex: /minhapasta/meuarquivo.txt"]);
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

    if (move_uploaded_file($_FILES['file']['tmp_name'], $fullPath)) {
        $dominio = $_SERVER['HTTP_HOST'];
        $urlFinal = "https://{$dominio}/" . $fileRelPath;
        response(200, [
            "sucesso" => "Arquivo enviado",
            "url" => $urlFinal,
            "arquivo" => basename($fullPath),
            "tamanho_bytes" => filesize($fullPath),
            "espaco_restante" => disk_free_space(BASE_DIR)
        ]);
    } else {
        response(500, ["erro" => "Falha ao salvar arquivo localmente."]);
    }
}

// ====== UPLOAD via URL (Download direto) ======
if ($getUrl !== '' && $fileRelPath !== '') {
    $conteudo = @file_get_contents($getUrl);
    if ($conteudo === false) {
        response(500, ["erro" => "Falha ao baixar o arquivo da URL fornecida."]);
    }

    $dir = dirname($fullPath);
    if (!is_dir($dir)) mkdir($dir, 0777, true);

    if (file_put_contents($fullPath, $conteudo) !== false) {
        $dominio = $_SERVER['HTTP_HOST'];
        $urlFinal = "https://{$dominio}/" . $fileRelPath;
        response(200, [
            "sucesso" => "Arquivo enviado via download",
            "url" => $urlFinal,
            "arquivo" => basename($fullPath),
            "tamanho_bytes" => filesize($fullPath),
            "espaco_restante" => disk_free_space(BASE_DIR)
        ]);
    } else {
        response(500, ["erro" => "Falha ao salvar arquivo baixado."]);
    }
}

// Nenhuma operação válida
response(400, ["erro" => "Arquivo não encontrado ou nenhuma operação válida especificada."]);
?>