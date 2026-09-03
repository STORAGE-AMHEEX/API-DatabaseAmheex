<?php
// =========================================================================
//   AJUSTES PHP — SUPORTE A ARQUIVOS DE ATÉ 1 TERABYTE (1048576M)
// =========================================================================
@ini_set('upload_max_filesize', '1048576M');
@ini_set('post_max_size', '1048576M');
@ini_set('memory_limit', '4096M');
@ini_set('max_execution_time', '0');
@ini_set('max_input_time', '0');
@ini_set('output_buffering', '0');
@ini_set('implicit_flush', '1');

ignore_user_abort(true);
set_time_limit(0);

while (ob_get_level()) {
    @ob_end_clean();
}

// =========================================================================
// CORS + PERMISSÃO DE IFRAME
// =========================================================================
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Range');
header('Access-Control-Expose-Headers: Content-Length, Content-Range, Accept-Ranges, Content-Type');
header('Content-Security-Policy: frame-ancestors *;');
header('Cross-Origin-Resource-Policy: cross-origin');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// =========================================================================
// CONFIGURAÇÕES DE DIRETÓRIO LOCAL (DIRETÓRIO X)
// =========================================================================
define('BASE_DIR', realpath(__DIR__) . '/X');

if (!is_dir(BASE_DIR)) {
    @mkdir(BASE_DIR, 0777, true);
}

// =========================================================================
// FUNÇÕES AUXILIARES
// =========================================================================
function sanitizaCaminho($path) {
    $path = trim($path, "/\\");
    $path = str_replace(['..\\', '../'], '', $path);
    $path = preg_replace('/[^a-zA-Z0-9_\-\.\@\/]/', '', $path);
    $path = preg_replace('#/+#', '/', $path);
    
    $path = preg_replace('#/(chunk|chuck|chunks|chucks)(/.*)?$#i', '', $path);
    $path = preg_replace('#^(chunk|chuck|chunks|chucks)/?#i', '', $path);
    
    return trim($path, "/\\");
}

function neutralizaExtensaoPhp($path) {
    if (preg_match('/\.php$/i', $path)) {
        return $path . '.txt';
    }
    return $path;
}

function build_clean_public_url($fileRelPath, $comPastaX = false) {
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $pastaX = $comPastaX ? '/X' : '';
    return "{$scheme}://{$host}{$pastaX}/" . ltrim($fileRelPath, '/');
}

// =========================================================================
// OBTENÇÃO DO CAMINHO EXATO A PARTIR DA URL (LIMPAR DUPLICAÇÕES /X/)
// =========================================================================
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$requestPath = parse_url($requestUri, PHP_URL_PATH);

// DETECÇÃO SE A URL POSSUI A PASTA /X/ EXPLICITAMENTE NA FRENTE DO DOMÍNIO
$veioComPastaXExplicitamente = (stripos($requestPath, '/X/') === 0 || preg_match('#^/X(/|$)#i', $requestPath));

// Remove todas as repetições de /X do início para mapear o arquivo corretamente na BASE_DIR
$cleanPathForFile = preg_replace('#^(/X)+#i', '', $requestPath);

// REDIRECIONAMENTO SE PARÂMETRO PATH NÃO ESTIVER CONFIGURADO (VAZIO)
if (empty($cleanPathForFile) || $cleanPathForFile === '/' || $cleanPathForFile === '') {
    header("Location: https://storage-amheex.onrender.com/erro_parm.php");
    exit;
}

$fileRelPath = sanitizaCaminho(urldecode($cleanPathForFile));

if (empty($fileRelPath)) {
    header("Location: https://storage-amheex.onrender.com/erro_parm.php");
    exit;
}

$fileRelPath = neutralizaExtensaoPhp($fileRelPath);

// =========================================================================
// ROTA GET
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $fullPath = BASE_DIR . '/' . $fileRelPath;
    $fileDir = dirname($fullPath);
    
    if (!is_dir($fileDir)) {
        @mkdir($fileDir, 0777, true);
    }

    // CORREÇÃO: Não criamos mais arquivo de 0 bytes automaticamente no GET.
    // Se o arquivo não existir, tratamos como ausente para permitir o teste de remoção e redirecionamento.

    if (isset($_GET['check_size']) && $_GET['check_size'] === '1') {
        while (ob_get_level()) {
            @ob_end_clean();
        }
        
        // Se o arquivo não existe, avisa para redirecionar para a URL de erro/atual
        if (!file_exists($fullPath)) {
            header('Content-Type: application/json');
            $currentRequestedUrl = $requestUri;
            echo json_encode(['error' => 'file_deleted', 'redirect' => $currentRequestedUrl]);
            exit;
        }

        header('Content-Type: application/json');
        clearstatcache(true, $fullPath);
        $currentSize = filesize($fullPath);
        echo json_encode(['size' => $currentSize]);
        exit;
    }

    if (isset($_GET['download']) && $_GET['download'] === '1') {
        while (ob_get_level()) {
            @ob_end_clean();
        }

        if (!file_exists($fullPath)) {
            header("Location: https://storage-amheex.onrender.com/erro_server_exiting.php");
            exit;
        }

        $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
        if ($ext === 'apk') {
            $mime = 'application/vnd.android.package-archive';
        } else {
            $mime = @mime_content_type($fullPath) ?: 'application/octet-stream';
        }

        $fileSize = filesize($fullPath);
        $fileName = basename($fullPath);

        header('Content-Description: File Transfer');
        header('Content-Type: ' . $mime);
        header('Content-Disposition: attachment; filename="' . addslashes($fileName) . '"');
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . $fileSize);
        @set_time_limit(0);

        $handle = @fopen($fullPath, 'rb');
        if ($handle) {
            while (!feof($handle)) {
                echo fread($handle, 8192 * 1024);
                flush();
            }
            fclose($handle);
        } else {
            readfile($fullPath);
        }
        exit;
    }

    // Checagem sem pasta X na URL, mas redirecionamento final com a pasta X (true)
    $baseCleanUri = '/' . ltrim($fileRelPath, '/');
    $stringBlobUrl = $baseCleanUri . '?download=1';
    $stringCheckUrl = $baseCleanUri . '?check_size=1';
    $stringRedirectUrl = build_clean_public_url($fileRelPath, true);
    $jsVeioComPastaX = $veioComPastaXExplicitamente ? 'true' : 'false';
    $currentRequestUri = $requestUri;

    echo '<!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>STORAGE AMHEEX</title>
        <link rel="icon" href="https://amheex.web.app/icon.jpg" type="image/jpeg">
        <style>
            * { box-sizing: border-box; }
            html, body {
                margin: 0; padding: 0; width: 100%; height: 100vh;
                background: #ffffff; color: #222222; font-family: Arial, sans-serif;
                display: flex; flex-direction: column; align-items: center; justify-content: center; overflow: hidden;
            }
            .loadball {
                width: 70px; height: 70px; border: 6px solid #f3f3f3;
                border-top: 6px solid #ff0000; border-right: 6px solid #00ff00;
                border-bottom: 6px solid #0000ff; border-left: 6px solid #ffff00;
                border-radius: 50%; animation: spin 1s linear infinite; margin-bottom: 25px;
            }
            .title { font-size: 18px; letter-spacing: 2px; font-weight: bold; color: #333333; }
            @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        </style>
    </head>
    <body>
        <div class="loadball"></div>
        <div class="title" id="statusText">Aguardando arquivo...</div>
        <script>
            const checkUrl = ' . json_encode($stringCheckUrl) . ';
            const blobUrl = ' . json_encode($stringBlobUrl) . ';
            const redirectUrl = ' . json_encode($stringRedirectUrl) . ';
            const currentRequestUri = ' . json_encode($currentRequestUri) . ';
            const veioComPastaX = ' . $jsVeioComPastaX . ';
            const statusEl = document.getElementById("statusText");

            function startDownload() {
                statusEl.innerText = "Iniciando download...";
                window.location.href = blobUrl;

                setTimeout(() => {
                    if (veioComPastaX) {
                        window.location.reload();
                    } else {
                        window.location.href = redirectUrl;
                    }
                }, 5000);
            }

            function pollFileSize() {
                fetch(checkUrl, { cache: "no-store" })
                    .then(res => res.json())
                    .then(data => {
                        if (data && data.error === "file_deleted") {
                            window.location.href = data.redirect || currentRequestUri;
                            return;
                        }
                        if (data && data.size > 0) {
                            statusEl.innerText = "Arquivo detectado!";
                            startDownload();
                        } else {
                            setTimeout(pollFileSize, 1000);
                        }
                    })
                    .catch(() => {
                        setTimeout(pollFileSize, 1000);
                    });
            }

            pollFileSize();
        </script>
    </body>
    </html>';
    exit;
}

// =========================================================================
// ROTA POST — GRAVAÇÃO DIRETA DE CHUNKS NO ARQUIVO FINAL
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    while (ob_get_level()) {
        @ob_end_clean();
    }

    if (empty($fileRelPath)) {
        http_response_code(400);
        echo 'null';
        exit;
    }

    $finalFilePath = BASE_DIR . '/' . $fileRelPath;
    $fileDir = dirname($finalFilePath);
    
    if (!is_dir($fileDir)) {
        @mkdir($fileDir, 0777, true);
    }

    if (isset($_POST['chunkIndex']) && isset($_POST['totalChunks'])) {
        $chunkIndex = intval($_POST['chunkIndex']);
        $totalChunks = intval($_POST['totalChunks']);
        
        $chunkTmp = null;
        if (!empty($_FILES)) {
            foreach ($_FILES as $fileKey => $fileInfo) {
                if (isset($fileInfo['tmp_name']) && $fileInfo['error'] === UPLOAD_ERR_OK) {
                    $chunkTmp = $fileInfo['tmp_name'];
                    break;
                }
            }
        }

        if ($chunkTmp && file_exists($chunkTmp)) {
            $mode = ($chunkIndex === 0) ? 'wb' : 'ab';
            
            $out = @fopen($finalFilePath, $mode);
            $in = @fopen($chunkTmp, 'rb');
            
            if ($out && $in) {
                stream_copy_to_stream($in, $out);
                @fclose($in);
                @fclose($out);
            } else {
                http_response_code(500);
                echo 'null';
                exit;
            }

            if ($chunkIndex === ($totalChunks - 1)) {
                if (file_exists($finalFilePath) && filesize($finalFilePath) > 0) {
                    echo build_clean_public_url($fileRelPath, true);
                    exit;
                }
                http_response_code(500);
                echo 'null';
                exit;
            }

            echo 'chunk_ok';
            exit;
        }
        
        http_response_code(400);
        echo 'null';
        exit;
    }

    $httpRange = $_SERVER['HTTP_CONTENT_RANGE'] ?? '';
    if (!empty($httpRange) && preg_match('/bytes\s+(\d+)-(\d+)\/(\d+|\*)/i', $httpRange, $matches)) {
        $startByte = intval($matches[1]);
        $totalSize = ($matches[3] !== '*') ? intval($matches[3]) : 0;
        
        $input = @fopen('php://input', 'rb');
        $mode = ($startByte === 0) ? 'wb' : 'ab';
        $out = @fopen($finalFilePath, $mode);

        if ($input && $out) {
            if ($startByte > 0) {
                @fseek($out, $startByte);
            }
            stream_copy_to_stream($input, $out);
            @fclose($input);
            @fclose($out);

            if ($totalSize > 0 && file_exists($finalFilePath) && filesize($finalFilePath) >= $totalSize) {
                echo build_clean_public_url($fileRelPath, true);
                exit;
            }

            echo 'chunk_ok';
            exit;
        }

        if ($input) @fclose($input);
        if ($out) @fclose($out);
        http_response_code(500);
        exit;
    }

    $input = fopen('php://input', 'rb');
    if ($input) {
        $out = fopen($finalFilePath, 'wb');
        if ($out) {
            stream_copy_to_stream($input, $out);
            fclose($out);
            fclose($input);

            if (file_exists($finalFilePath) && filesize($finalFilePath) > 0) {
                echo build_clean_public_url($fileRelPath, true);
                exit;
            }
        } else {
            fclose($input);
        }
    }

    http_response_code(500);
    echo 'null';
    exit;
}

http_response_code(405);
echo 'null';
