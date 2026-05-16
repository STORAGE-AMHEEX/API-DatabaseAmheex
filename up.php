<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Nota: O Content-Type JSON continua aqui para o caso de erros, 
// mas será sobrescrevido no redirecionamento.
header('Content-Type: application/json');

define('BASE_DIR', realpath(__DIR__) . '/X');
if (!is_dir(BASE_DIR)) mkdir(BASE_DIR, 0777, true);

function response($status, $data) {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function sanitizaCaminho($path) {
    $path = ltrim($path, '/');
    $path = preg_replace('/[^a-zA-Z0-9_\-\.\@\/]/', '', $path);
    return $path;
}

$request_path = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$fileRelPath = sanitizaCaminho($request_path);
$fullPath = BASE_DIR . '/' . $fileRelPath;

// ====== DOWNLOAD DE ARQUIVO EXISTENTE ======
if ($_SERVER['REQUEST_METHOD'] === 'GET' && file_exists($fullPath) && is_file($fullPath)) {
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . basename($fullPath) . '"');
    header('Content-Length: ' . filesize($fullPath));
    readfile($fullPath);
    exit;
}

// ====== UPLOAD DE ARQUIVO ======
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

    if (is_dir($fullPath)) {
        response(400, ["erro" => "O caminho especificado é um diretório, não um arquivo."]);
    }

    if (move_uploaded_file($_FILES['file']['tmp_name'], $fullPath)) {
        $dominio = $_SERVER['HTTP_HOST'];
        
        // Mantive o padrão do seu código original (usando /#/ antes do caminho)
        // Se você quiser que o navegador acesse o arquivo direto na pasta X, mude para: "https://{$dominio}/X/" . $fileRelPath
        $urlFinal = "https://{$dominio}/#/" . $fileRelPath; 
        
        // Lógica de Redirecionamento Imediato
        header("Location: " . $urlFinal, true, 303);
        exit;
    } else {
        response(500, ["erro" => "Falha ao salvar arquivo localmente."]);
    }
}

// ====== UPLOAD DE TEXTO (OU DOWNLOAD DE LINK) ======
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_FILES['file'])) {
    if ($fileRelPath === '') {
        response(400, ["erro" => "Defina o caminho do arquivo na URL. Ex: /minhapasta/meuarquivo.txt"]);
    }

    $rawData = trim(file_get_contents("php://input"));
    if (empty($rawData)) {
        response(400, ["erro" => "Nenhum conteúdo de texto recebido."]);
    }

    $dir = dirname($fullPath);
    if (!is_dir($dir)) mkdir($dir, 0777, true);

    if (is_dir($fullPath)) {
        response(400, ["erro" => "O caminho especificado é um diretório, não um arquivo."]);
    }

    // 🔍 Se o texto começar com "https//", trata como URL e faz download
    if (preg_match('/^https\/\//i', $rawData)) {
        $url = str_replace('https//', 'https://', $rawData);

        $conteudo = @file_get_contents($url);
        if ($conteudo === false) {
            response(400, ["erro" => "Falha ao baixar conteúdo da URL: {$url}"]);
        }

        if (file_put_contents($fullPath, $conteudo) === false) {
            response(500, ["erro" => "Falha ao salvar conteúdo baixado localmente."]);
        }

        $dominio = $_SERVER['HTTP_HOST'];
        $urlFinal = "https://{$dominio}/#/" . $fileRelPath;

        // Lógica de Redirecionamento Imediato
        header("Location: " . $urlFinal, true, 303);
        exit;
    }

    // Caso contrário, salva texto normalmente
    if (file_put_contents($fullPath, $rawData) !== false) {
        $dominio = $_SERVER['HTTP_HOST'];
        $urlFinal = "https://{$dominio}/#/" . $fileRelPath;
        
        // Lógica de Redirecionamento Imediato
        header("Location: " . $urlFinal, true, 303);
        exit;
    } else {
        response(500, ["erro" => "Falha ao salvar o texto localmente."]);
    }
}

response(400, ["erro" => "Arquivo não encontrado ou nenhuma operação válida especificada."]);
?>
