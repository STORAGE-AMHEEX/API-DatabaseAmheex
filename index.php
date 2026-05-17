<?php
// ===============================================
// 🔧 AJUSTES PHP — SUPORTE A ARQUIVOS GRANDES E UPLOADS LONGOS
// ===============================================

@ini_set('upload_max_filesize', '16000M');
@ini_set('post_max_size', '16000M');
@ini_set('memory_limit', '16000M');
@ini_set('max_execution_time', '86400');
@ini_set('max_input_time', '86400');
@ini_set('output_buffering', '0');
@ini_set('implicit_flush', '1');

ignore_user_abort(true);
set_time_limit(0);

// === CORS / Headers ===
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// === Configurações ===
define('BASE_DIR', realpath(__DIR__) . '/X');
if (!is_dir(BASE_DIR)) {
    if (!mkdir(BASE_DIR, 0777, true)) {
        error_log("Falha ao criar BASE_DIR: " . BASE_DIR);
        http_response_code(500);
        echo "Erro no servidor.";
        exit;
    }
}

// === Funções auxiliares ===
function sanitizaCaminho($path) {
    $path = trim($path, "/\\");
    $path = str_replace(['..\\', '../'], '', $path);
    $path = preg_replace('/[^a-zA-Z0-9_\-\.\@\/]/', '', $path);
    $path = preg_replace('#/+#', '/', $path);
    return $path;
}

/**
 * 🔒 PROTEÇÃO CONTRA EXECUÇÃO DE SCRIPT (PHP para PHP.TXT)
 */
function neutralizaExtensaoPhp($path) {
    if (preg_match('/\.php$/i', $path)) {
        return $path . '.txt';
    }
    return $path;
}

function build_public_url($fileRelPath) {
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    
    // 🔥 CORREÇÃO: Remove apenas as contrabarras '\' e limpa repetições sem sumir com a pasta inicial
    $cleanPath = str_replace('\\', '/', $fileRelPath);
    $cleanPath = preg_replace('#/+#', '/', $cleanPath);
    $cleanPath = ltrim($cleanPath, '/');
    
    return "{$scheme}://{$host}/X/{$cleanPath}";
}

function build_clean_public_url($fileRelPath) {
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    
    // 🔥 CORREÇÃO: Transforma '\' em '/' e limpa excessos, mantendo o subcaminho /TESTE/ intacto
    $cleanPath = str_replace('\\', '/', $fileRelPath);
    $cleanPath = preg_replace('#/+#', '/', $cleanPath);
    $cleanPath = ltrim($cleanPath, '/');
    
    return "{$scheme}://{$host}/{$cleanPath}";
}

/**
 * 🧠 INTELIGÊNCIA DE RESTAURAÇÃO (Aguarda até 30 segundos pelo outro PHP)
 */
function aguardarRestauracaoArquivo($fullPath) {
    if (!is_file($fullPath) || filesize($fullPath) === 0) {
        if (!is_dir(dirname($fullPath))) {
            mkdir(dirname($fullPath), 0777, true);
        }
        touch($fullPath);

        $tentativas = 30;
        while ($tentativas > 0) {
            sleep(1);
            clearstatcache(true, $fullPath);

            if (is_file($fullPath) && filesize($fullPath) > 0) {
                return true;
            }
            $tentativas--;
        }

        if (is_file($fullPath) && filesize($fullPath) === 0) {
            @unlink($fullPath);
        }
        return false;
    }
    return true;
}


// ======================================================
// SISTEMA DE DOWNLOAD DIRETO
// ======================================================
if (isset($_GET['download'])) {
    $fileParam = sanitizaCaminho($_GET['download']);
    $fileParam = neutralizaExtensaoPhp($fileParam); 
    $fullPath = BASE_DIR . '/' . $fileParam;

    if (!aguardarRestauracaoArquivo($fullPath)) {
        http_response_code(404);
        echo "Arquivo não encontrado ou restauração expirou.";
        exit;
    }

    $mime = mime_content_type($fullPath) ?: 'application/octet-stream';
    header('Content-Description: File Transfer');
    header('Content-Type: ' . $mime);
    header('Content-Disposition: attachment; filename="' . basename($fullPath) . '"');
    header('Content-Length: ' . filesize($fullPath));
    flush();
    readfile($fullPath);
    exit;
}

// ======================================================
// GET normal — exibição + download automático
// ======================================================
$request_path = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$fileRelPath = sanitizaCaminho($request_path);
$fileRelPath = neutralizaExtensaoPhp($fileRelPath); 
$fullPath = BASE_DIR . '/' . $fileRelPath;

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    
    if (aguardarRestauracaoArquivo($fullPath)) {
        $fileUrl = build_public_url($fileRelPath);
        $downloadUrl = "/?download=" . urlencode($fileRelPath);

        echo "<!DOCTYPE html>
        <html lang='pt-BR'>
        <head>
            <meta charset='utf-8'>
            <title>Download Automático</title>
            <style>
                html, body { 
                    margin:0; 
                    padding:0; 
                    height:100%; 
                    background:#fff; 
                    font-family: Arial, sans-serif;
                }
                iframe { width:100%; height:100%; border:0; display:block; }
                .loading-container {
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: #fff;
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    font-size: 24px;
                    color: #333;
                    z-index: 9999;
                    pointer-events: none;
                }
            </style>
        </head>
        <body>
            <div class='loading-container'>
                Carregando...
            </div>

            <iframe src='{$fileUrl}'></iframe>
            <iframe src='{$downloadUrl}' style='display:none'></iframe>
            
            <script>
                setTimeout(() => window.location.href = '{$fileUrl}', 5000);
            </script>
        </body>
        </html>";

        exit;
    }

    http_response_code(404);
    echo "Arquivo não encontrado, No Servidor!!";
    exit;
}

// ======================================================
// POST — Upload inteligente
// ======================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $targetRel = $fileRelPath;
    
    $json = function(array $data, int $code = 200) {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    };

    // --- Upload em partes (chunks) ---
    if (!empty($_FILES['file'])) {
        $fileId     = $_POST['fileId'] ?? uniqid('upload_', true);
        $chunkIndex = intval($_POST['chunkIndex'] ?? 0);
        $totalChunks= intval($_POST['totalChunks'] ?? 1);
        $fileName   = basename($_POST['fileName'] ?? ('file_' . time()));

        $uploadDir = BASE_DIR;
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        $chunkDir = $uploadDir . '/.parts_' . md5($fileId);
        if (!is_dir($chunkDir)) mkdir($chunkDir, 0777, true);

        $tmpName = $_FILES['file']['tmp_name'] ?? null;
        if (!$tmpName || !is_uploaded_file($tmpName)) {
            $json(["status" => "error", "message" => "Nenhum chunk válido."], 400);
        }

        $partPath = "{$chunkDir}/part_{$chunkIndex}";
        move_uploaded_file($tmpName, $partPath);

        if ($chunkIndex + 1 === $totalChunks) {
            $finalRel = $targetRel !== '' ? $targetRel : $fileName;
            $finalRel = sanitizaCaminho($finalRel);
            $finalRel = neutralizaExtensaoPhp($finalRel); 
            
            $finalPath = BASE_DIR . '/' . $finalRel;
            if (!is_dir(dirname($finalPath))) mkdir(dirname($finalPath), 0777, true);
            
            $out = fopen($finalPath, 'wb');
            for ($i = 0; $i < $totalChunks; $i++) {
                $p = "{$chunkDir}/part_{$i}";
                $in = fopen($p, 'rb');
                stream_copy_to_stream($in, $out);
                fclose($in);
                @unlink($p);
            }
            fclose($out);
            @rmdir($chunkDir);
            $url = build_clean_public_url($finalRel);
            $json(["status" => "complete", "url" => $url]);
        }

        $json(["status" => "partial", "progress" => $chunkIndex + 1]);
    }

    // --- Upload via php://input (streaming) ---
    $input = fopen('php://input', 'rb');
    if ($input) {
        $finalRel = $fileRelPath ?: ('upload_' . time());
        $finalRel = sanitizaCaminho($finalRel);
        $finalRel = neutralizaExtensaoPhp($finalRel); 
        
        $finalPath = BASE_DIR . '/' . $finalRel;
        if (!is_dir(dirname($finalPath))) mkdir(dirname($finalPath), 0777, true);
        $out = fopen($finalPath, 'wb');
        stream_copy_to_stream($input, $out);
        fclose($out); fclose($input);
        $url = build_clean_public_url($finalRel);
        $json(["status" => "complete", "url" => $url]);
    }

    $json(["status" => "error", "message" => "Nenhum conteúdo recebido."], 400);
}

http_response_code(405);
echo "Método inválido.";
exit;
?>
