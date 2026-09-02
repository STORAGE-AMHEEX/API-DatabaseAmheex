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
// CONFIGURAÇÕES DE DIRETÓRIO LOCAL E FIREBASE
// =========================================================================
define('BASE_DIR', realpath(__DIR__) . '/X');

if (!is_dir(BASE_DIR)) {
    @mkdir(BASE_DIR, 0777, true);
}

$firebaseBaseUrl = 'https://storage-amheex-default-rtdb.firebaseio.com/';
$raizStorage     = 'X';

// =========================================================================
// FUNÇÕES AUXILIARES
// =========================================================================
function sanitizaCaminho($path) {
    $path = trim($path, "/\\");
    $path = str_replace(['..\\', '../'], '', $path);
    $path = preg_replace('/[^a-zA-Z0-9_\-\.\@\/]/', '', $path);
    $path = preg_replace('#/+#', '/', $path);
    return $path;
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

function firebase_request($url, $method = 'GET', $data = null) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
    curl_setopt($ch, CURLOPT_TIMEOUT, 120);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode >= 400) {
        return false;
    }
    return $response;
}

function obterNodePathFirebase($relativeFilePath, $raizStorage) {
    $base64Path = rtrim(strtr(base64_encode($relativeFilePath), '+/', '-_'), '=');
    return $raizStorage . '/' . $base64Path;
}

function restaurarArquivoFirebase($fullLocalPath, $relativeFilePath, $firebaseBaseUrl, $raizStorage) {
    $nodePath = obterNodePathFirebase($relativeFilePath, $raizStorage);
    $fileDir = dirname($fullLocalPath);
    if (!is_dir($fileDir)) {
        @mkdir($fileDir, 0777, true);
    }

    $outHandle = @fopen($fullLocalPath, 'wb');
    if (!$outHandle) {
        return false;
    }

    $part = 1;
    $temPartes = false;

    while (true) {
        @set_time_limit(0);
        $url = $firebaseBaseUrl . $nodePath . '/part_' . $part . '.json';
        $response = firebase_request($url, 'GET');

        if ($response === false || $response === 'null' || empty($response)) {
            break;
        }

        $data = json_decode($response, true);
        if (!is_string($data) || empty($data)) {
            break;
        }

        $cleanHex = str_replace([' ', "\r", "\n", "\t"], '', $data);
        if (strlen($cleanHex) % 2 !== 0) {
            $cleanHex = substr($cleanHex, 0, -1);
        }

        $binaryChunk = @hex2bin($cleanHex);
        if ($binaryChunk !== false && strlen($binaryChunk) > 0) {
            fwrite($outHandle, $binaryChunk);
            $temPartes = true;
        }

        unset($data, $cleanHex, $binaryChunk);
        $part++;
    }

    fclose($outHandle);

    if (!$temPartes || filesize($fullLocalPath) === 0) {
        @unlink($fullLocalPath);
        return false;
    }

    return true;
}

// =========================================================================
// OBTENÇÃO DO CAMINHO EXATO A PARTIR DA URL
// =========================================================================
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$requestPath = parse_url($requestUri, PHP_URL_PATH);
if (strpos($requestPath, '/X/') === 0) {
    $requestPath = substr($requestPath, 2);
}
$fileRelPath = sanitizaCaminho(urldecode($requestPath));
$fileRelPath = neutralizaExtensaoPhp($fileRelPath);

// =========================================================================
// ROTA GET
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (empty($fileRelPath)) {
        http_response_code(200);
        echo 'true';
        exit;
    }

    $fullPath = BASE_DIR . '/' . $fileRelPath;
    
    if (!file_exists($fullPath) || filesize($fullPath) <= 1) {
        restaurarArquivoFirebase($fullPath, $fileRelPath, $firebaseBaseUrl, $raizStorage);
    }

    if (!file_exists($fullPath) || filesize($fullPath) <= 1) {
        http_response_code(404);
        echo 'null';
        exit;
    }

    // Se a requisição veio do fetch interno do Blob (?download=1)
    if (isset($_GET['download']) && $_GET['download'] === '1') {
        while (ob_get_level()) {
            @ob_end_clean();
        }

        $mime = @mime_content_type($fullPath) ?: 'application/octet-stream';
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

    // URLs tratadas para o JavaScript
    $stringBlobUrl = $requestUri . (strpos($requestUri, '?') !== false ? '&' : '?') . 'download=1';
    $stringRedirectUrl = build_clean_public_url($fileRelPath, true);

    echo '<!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>STORAGE AMHEEX</title>
        <style>
            * {
                box-sizing: border-box;
            }
            html, body {
                margin: 0;
                padding: 0;
                width: 100%;
                height: 100vh;
                background: #ffffff;
                color: #222222;
                font-family: Arial, sans-serif;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                overflow: hidden;
            }
            .loadball {
                width: 70px;
                height: 70px;
                border: 6px solid #f3f3f3;
                border-top: 6px solid #ff0000;
                border-right: 6px solid #00ff00;
                border-bottom: 6px solid #0000ff;
                border-left: 6px solid #ffff00;
                border-radius: 50%;
                animation: spin 1s linear infinite;
                margin-bottom: 25px;
            }
            .title {
                font-size: 18px;
                letter-spacing: 2px;
                font-weight: bold;
                color: #333333;
            }
            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
        </style>
    </head>
    <body>
        <div class="loadball"></div>
        <div class="title">Carregando...</div>
        <script>
            const blobUrl = ' . json_encode($stringBlobUrl) . ';
            const redirectUrl = ' . json_encode($stringRedirectUrl) . ';
            const fileName = ' . json_encode(basename($fullPath)) . ';

            // Dispara o download via fetch blob em segundo plano
            fetch(blobUrl)
                .then(response => {
                    if (!response.ok) throw new Error("Erro no download");
                    return response.blob();
                })
                .then(blob => {
                    const link = document.createElement("a");
                    link.href = window.URL.createObjectURL(blob);
                    link.download = fileName;
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                })
                .catch(err => console.error("Falha:", err));

            // Aguarda 5 segundos na tela de carregamento e redireciona para a pasta limpa
            setTimeout(() => {
                window.location.href = redirectUrl;
            }, 5000);
        </script>
    </body>
    </html>';
    exit;
}

// =========================================================================
// ROTA POST — UPLOAD LOCAL POR CHUNKS (MULTIPART OU STREAM BINÁRIO DIRETO)
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

    // 1. Suporte a parâmetros via POST tradicional (chunkIndex e totalChunks com $_FILES)
    if (isset($_POST['chunkIndex']) && isset($_POST['totalChunks'])) {
        $chunkIndex = intval($_POST['chunkIndex']);
        $totalChunks = intval($_POST['totalChunks']);
        
        if (isset($_FILES['chunk']) && $_FILES['chunk']['error'] === UPLOAD_ERR_OK) {
            $chunkTmp = $_FILES['chunk']['tmp_name'];
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
                    echo build_clean_public_url($fileRelPath, false);
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

    // 2. Suporte robusto a Chunks enviados via Headers HTTP (HTTP Range / Streams binários)
    $httpRange = $_SERVER['HTTP_CONTENT_RANGE'] ?? '';
    if (!empty($httpRange) && preg_match('/bytes\s+(\d+)-(\d+)\/(\d+|\*)/i', $httpRange, $matches)) {
        $startByte = intval($matches[1]);
        $totalSize = ($matches[3] !== '*') ? intval($matches[3]) : 0;
        
        $input = @fopen('php://input', 'rb');
        // Se o range começa em 0, sobrescreve/cria o arquivo ('wb'), senão abre para append ('ab')
        $mode = ($startByte === 0) ? 'wb' : 'ab';
        $out = @fopen($finalFilePath, $mode);

        if ($input && $out) {
            // Posiciona o ponteiro do arquivo exatamente onde o chunk deve entrar (garante ordem correta se houver concorrência)
            if ($startByte > 0) {
                @fseek($out, $startByte);
            }
            stream_copy_to_stream($input, $out);
            @fclose($input);
            @fclose($out);

            // Se o tamanho do arquivo bate com o tamanho total esperado, finaliza
            if ($totalSize > 0 && file_exists($finalFilePath) && filesize($finalFilePath) >= $totalSize) {
                echo build_clean_public_url($fileRelPath, false);
                exit;
            }

            echo 'chunk_ok';
            exit;
        }

        if ($input) @fclose($input);
        if ($out) @fclose($out);
        http_response_code(500);
        echo 'null';
        exit;
    }

    // 3. Fallback padrão para envio de arquivo completo via `php://input`
    $input = fopen('php://input', 'rb');
    if ($input) {
        $out = fopen($finalFilePath, 'wb');
        if ($out) {
            stream_copy_to_stream($input, $out);
            fclose($out);
            fclose($input);

            if (file_exists($finalFilePath) && filesize($finalFilePath) > 0) {
                echo build_clean_public_url($fileRelPath, false);
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
