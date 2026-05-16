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
    // Remove o nome do script atual da URL se ele estiver presente (ex: index.php/meuarquivo -> meuarquivo)
    $scriptName = basename($_SERVER['SCRIPT_NAME']);
    $path = str_replace($scriptName, '', $path);
    
    $path = ltrim($path, '/');
    $path = preg_replace('/[^a-zA-Z0-9_\-\.\@\/]/', '', $path);
    return $path;
}

function redirecionarPara($url) {
    if (ob_get_length()) ob_clean(); 
    header("Location: " . $url, true, 301);
    
    header('Content-Type: text/html; charset=utf-8');
    echo "<!DOCTYPE html><html><head><meta http-equiv='refresh' content='0;url={$url}'></head>";
    echo "<body><script>window.location.href='{$url}';</script></body></html>";
    exit;
}

$request_path = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$fileRelPath = sanitizaCaminho($request_path);

// ====== CONTEXTO DE GET (DOWNLOAD) ======
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Se a rota for vazia ou for um arquivo .php do sistema, NÃO INTERFERE
    if ($fileRelPath === '' || pathinfo($fileRelPath, PATHINFO_EXTENSION) === 'php') {
        // Deixa passar. Se houver um sistema de rotas index.php acima, ele continua o fluxo normal do servidor
        return; 
    }

    $fullPath = BASE_DIR . '/' . $fileRelPath;
    $realFullPath = realpath(dirname($fullPath)) . '/' . basename($fullPath);

    // Só interfere e processa se o arquivo realmente existir fisicamente dentro de X
    if (file_exists($fullPath) && is_file($fullPath)) {
        // 🔒 Proteção de Diretório
        if (strpos($realFullPath, BASE_DIR) === 0) {
            if (ob_get_length()) ob_clean();
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . basename($fullPath) . '"');
            header('Content-Length: ' . filesize($fullPath));
            readfile($fullPath);
            exit;
        }
    }
    
    // Se o arquivo NÃO EXISTE na pasta X, não retorna erro 400/403 do script. 
    // Deixa o fluxo seguir para o servidor entregar o erro 404 padrão do sistema de rotas.
    return; 
}

// Configuração de URLs para respostas de Upload/POST
$protocolo = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$dominio = $_SERVER['HTTP_HOST'];
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
if ($scriptDir !== '/') $scriptDir = rtrim($scriptDir, '/') . '/';
$urlFinal = $protocolo . $dominio . $scriptDir . 'X/' . $fileRelPath;

$fullPath = BASE_DIR . '/' . $fileRelPath;

// ====== UPLOAD DE ARQUIVO (POST) ======
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['file'])) {
    if ($fileRelPath === '') {
        response(400, ["erro" => "Defina o caminho final do arquivo na URL."]);
    }
    
    if ($_FILES['file']['error'] !== 0) {
        response(400, ["erro" => "Erro no upload: " . $_FILES['file']['error']]);
    }

    $arquivoTamanho = $_FILES['file']['size'];
    $espacoLivre = disk_free_space(BASE_DIR);
    if ($arquivoTamanho > $espacoLivre) {
        response(507, ["erro" => "Espaço insuficiente no servidor."]);
    }

    $dir = dirname($fullPath);
    if (!is_dir($dir)) mkdir($dir, 0777, true);

    if (move_uploaded_file($_FILES['file']['tmp_name'], $fullPath)) {
        redirecionarPara($urlFinal);
    } else {
        response(500, ["erro" => "Falha ao salvar arquivo localmente."]);
    }
}

// ====== UPLOAD DE TEXTO / URL (POST) ======
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_FILES['file'])) {
    if ($fileRelPath === '') {
        response(400, ["erro" => "Defina o caminho final do arquivo na URL."]);
    }

    $rawData = trim(file_get_contents("php://input"));
    if (empty($rawData)) {
        response(400, ["erro" => "Nenhum conteúdo recebido."]);
    }

    $dir = dirname($fullPath);
    if (!is_dir($dir)) mkdir($dir, 0777, true);

    // Se for URL externa, baixa e salva em X
    if (preg_match('/^https\/\//i', $rawData)) {
        $url = str_replace('https//', 'https://', $rawData);
        $conteudo = @file_get_contents($url);
        if ($conteudo === false) {
            response(400, ["erro" => "Falha ao baixar conteúdo da URL externa."]);
        }
        file_put_contents($fullPath, $conteudo);
        redirecionarPara($urlFinal);
    }

    // Salvamento de texto comum
    if (file_put_contents($fullPath, $rawData) !== false) {
        redirecionarPara($urlFinal);
    } else {
        response(500, ["erro" => "Falha ao salvar o texto localmente."]);
    }
}
?>
