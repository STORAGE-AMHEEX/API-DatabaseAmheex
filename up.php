<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Resposta para requisições de preflight (CORS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Pasta base para armazenar arquivos
define('BASE_DIR', __DIR__ . '/X'); 

// Função padrão para retornar JSON e encerrar
function response($status, $data) {
    header('Content-Type: application/json');
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

// Determina o caminho final do arquivo no servidor
if ($getPath !== '') {
    $fullPath = BASE_DIR . '/' . $getPath;
    $fileRelPath = $getPath;
} else {
    $request_path = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
    $fileRelPath = sanitizaCaminho($request_path);
    $fullPath = BASE_DIR . '/' . $fileRelPath;
}

// ====== 1. DOWNLOAD EM SEGUNDO PLANO (GET) ======
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $getUrl === '' && file_exists($fullPath) && is_file($fullPath)) {
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . basename($fullPath) . '"');
    header('Content-Length: ' . filesize($fullPath));
    readfile($fullPath);
    exit;
}

// ====== 2. UPLOAD COMPLETO VIA POST ======
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    if ($fileRelPath === '') {
        response(400, ["erro" => "Defina o caminho/nome do arquivo (ex: ?path=pasta/arquivo.txt)."]);
    }

    $dir = dirname($fullPath);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    $espacoLivre = disk_free_space(BASE_DIR);

    // MODO A: Upload tradicional por formulário ($_FILES)
    if (!empty($_FILES['file'])) {
        if ($_FILES['file']['error'] !== 0) {
            response(400, ["erro" => "Erro no upload: " . $_FILES['file']['error']]);
        }

        $arquivoTamanho = $_FILES['file']['size'];
        if ($arquivoTamanho > $espacoLivre) {
            response(507, ["erro" => "Espaço insuficiente no servidor.", "requerido" => $arquivoTamanho, "disponivel" => $espacoLivre]);
        }

        if (move_uploaded_file($_FILES['file']['tmp_name'], $fullPath)) {
            enviarRespostaSucesso($fullPath, $fileRelPath);
        } else {
            response(500, ["erro" => "Falha ao salvar arquivo localmente."]);
        }
    } 
    // MODO B: Upload Binário / Texto Puro direto no corpo (Body) do POST
    else {
        // Lê os bytes brutos enviados no corpo da requisição
        $dadosBrutos = file_get_contents('php://input');

        if ($dadosBrutos === false || strlen($dadosBrutos) === 0) {
            response(400, ["erro" => "Nenhum dado recebido no corpo do POST ou arquivo vazio."]);
        }

        $arquivoTamanho = strlen($dadosBrutos);
        if ($arquivoTamanho > $espacoLivre) {
            response(507, ["erro" => "Espaço insuficiente para dados binários.", "requerido" => $arquivoTamanho, "disponivel" => $espacoLivre]);
        }

        // Salva os dados diretamente criando o arquivo
        if (file_put_contents($fullPath, $dadosBrutos) !== false) {
            enviarRespostaSucesso($fullPath, $fileRelPath);
        } else {
            response(500, ["erro" => "Falha ao gravar os dados binários no servidor."]);
        }
    }
}

// ====== 3. UPLOAD VIA URL (DOWNLOAD DIRETO DO SERVIDOR) ======
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $getUrl !== '' && $fileRelPath !== '') {
    $conteudo = @file_get_contents($getUrl);
    if ($conteudo === false) {
        response(500, ["erro" => "Falha ao baixar o arquivo da URL fornecida."]);
    }

    $arquivoTamanho = strlen($conteudo);
    $espacoLivre = disk_free_space(BASE_DIR);
    if ($arquivoTamanho > $espacoLivre) {
        response(507, ["erro" => "Espaço insuficiente para baixar o arquivo da URL."]);
    }

    $dir = dirname($fullPath);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    if (file_put_contents($fullPath, $conteudo) !== false) {
        enviarRespostaSucesso($fullPath, $fileRelPath);
    } else {
        response(500, ["erro" => "Falha ao salvar arquivo baixado da URL."]);
    }
}

// Função auxiliar para estruturar o retorno de sucesso do Upload
function enviarRespostaSucesso($fullPath, $fileRelPath) {
    $dominio = $_SERVER['HTTP_HOST'];
    $urlFinal = "https://{$dominio}/" . $fileRelPath;
    response(200, [
        "sucesso" => true,
        "mensagem" => "Arquivo processado com sucesso",
        "url" => $urlFinal,
        "arquivo" => basename($fullPath),
        "tamanho_bytes" => filesize($fullPath),
        "espaco_restante" => disk_free_space(BASE_DIR)
    ]);
}

// Se chegou até aqui, nenhuma rota foi acionada corretamente
response(400, ["erro" => "Arquivo não encontrado ou nenhuma operação válida especificada."]);
?>
