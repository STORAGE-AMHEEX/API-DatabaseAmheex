<?php
// =========================================================================
// 🔧 AJUSTES PHP — SUPORTE A ARQUIVOS GRANDES E UPLOADS LONGOS
// =========================================================================
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
header("Access-Control-Allow-Methods: GET, POST, PUT, PATCH, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Content-Disposition, X-File-Name");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// === Configurações de Diretório Local e Firebase ===
define('BASE_DIR', realpath(__DIR__) . '/X');
if (!is_dir(BASE_DIR)) {
    @mkdir(BASE_DIR, 0777, true);
}

$domain = $_SERVER['HTTP_HOST'] ?? 'default_domain';
$domainDir = preg_replace('/[^a-zA-Z0-9-]/', '_', $domain);

$firebaseBaseUrl = 'https://storage-amheex-default-rtdb.firebaseio.com/';
$raizStorage     = 'RAIZ_STORAGE';

// === Funções Auxiliares ===
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

function sanitize_firebase_key($path) {
    $clean = preg_replace('/[\.\#\$\[\]\s]/', '_', $path);
    return trim($clean, '_');
}

function build_clean_public_url($fileRelPath) {
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    return "{$scheme}://{$host}/" . ltrim($fileRelPath, '/');
}

function json_response(array $data, int $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Cliente cURL para a REST API do Firebase
 */
function firebase_request($url, $method = 'GET', $data = null) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 120);
    
    if ($data !== null) {
        $payload = json_encode($data);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($payload)
        ));
    }
    
    $response = curl_exec($ch);
    curl_close($ch);
    return $response;
}

function obterNodePathFirebase($relativeFilePath, $raizStorage, $domainDir) {
    $parts = explode('/', $relativeFilePath);
    $sanitizedParts = array_map('sanitize_firebase_key', $parts);
    $formattedFilePath = implode('/', array_filter($sanitizedParts));
    return $raizStorage . '/' . $domainDir . '/' . $formattedFilePath;
}

/**
 * Envia o arquivo local em partições hexadecimais para o Firebase (seguro contra estouro de RAM)
 */
function enviarBackupFirebase($fullLocalPath, $relativeFilePath, $firebaseBaseUrl, $raizStorage, $domainDir) {
    if (!file_exists($fullLocalPath) || filesize($fullLocalPath) === 0) {
        return false;
    }

    $nodePath = obterNodePathFirebase($relativeFilePath, $raizStorage, $domainDir);
    $handle = fopen($fullLocalPath, 'rb');
    if (!$handle) return false;

    $chunkSize = 524288; // 512KB de bytes binários = 1MB em Hexadecimal por partição
    $part = 1;

    while (!feof($handle)) {
        $buffer = fread($handle, $chunkSize);
        if ($buffer === false || strlen($buffer) === 0) break;

        $hexData = bin2hex($buffer);
        $partName = "part_" . $part;
        $url = $firebaseBaseUrl . $nodePath . '/' . $partName . '.json';
        
        firebase_request($url, 'PUT', $hexData);
        $part++;
    }

    fclose($handle);
    @file_put_contents('up_on.txt', '1');
    return true;
}

/**
 * Reconstrói o arquivo local a partir das partições do Firebase
 */
function restaurarArquivoFirebase($fullLocalPath, $relativeFilePath, $firebaseBaseUrl, $raizStorage, $domainDir) {
    $nodePath = obterNodePathFirebase($relativeFilePath, $raizStorage, $domainDir);
    
    $fileDir = dirname($fullLocalPath);
    if (!is_dir($fileDir)) {
        @mkdir($fileDir, 0777, true);
    }

    $part = 1;
    $foundAny = false;
    $outHandle = fopen($fullLocalPath, 'wb');
    if (!$outHandle) return false;

    while (true) {
        $partName = "part_$part";
        $url = $firebaseBaseUrl . $nodePath . '/' . $partName . '.json';
        
        $response = firebase_request($url, 'GET');
        
        if ($response !== false && $response !== 'null' && !empty($response)) {
            $data = json_decode($response, true);
            if (is_string($data) && !empty($data)) {
                $cleanHex = str_replace([' ', "\r", "\n", "\t"], '', $data);
                if (strlen($cleanHex) % 2 !== 0) {
                    $cleanHex = substr($cleanHex, 0, -1);
                }
                $binaryChunk = hex2bin($cleanHex);
                if ($binaryChunk !== false) {
                    fwrite($outHandle, $binaryChunk);
                    $foundAny = true;
                    $part++;
                    continue;
                }
            }
        }
        break;
    }

    fclose($outHandle);

    if ($foundAny && filesize($fullLocalPath) > 0) {
        @file_put_contents('up_on.txt', '1');
        return true;
    }

    @unlink($fullLocalPath);
    return false;
}

// =========================================================================
// OBTENÇÃO DO CAMINHO RELATIVO DA URL
// =========================================================================
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$requestPath = parse_url($requestUri, PHP_URL_PATH);
$fileRelPath = sanitizaCaminho(urldecode($requestPath));
$fileRelPath = neutralizaExtensaoPhp($fileRelPath);

// =========================================================================
// ROTA GET — DOWNLOAD / EXIBIÇÃO / RESTAURAÇÃO
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (empty($fileRelPath)) {
        http_response_code(200);
        echo "Servidor Ativo.";
        exit;
    }

    $fullPath = BASE_DIR . '/' . $fileRelPath;

    if (!file_exists($fullPath) || filesize($fullPath) <= 1) {
        $restaurado = restaurarArquivoFirebase($fullPath, $fileRelPath, $firebaseBaseUrl, $raizStorage, $domainDir);
        
        if (!$restaurado) {
            http_response_code(404);
            echo "Arquivo não encontrado no servidor ou no banco de dados.";
            exit;
        }
        
        header("Location: " . $requestUri);
        exit;
    }

    $mime = mime_content_type($fullPath) ?: 'application/octet-stream';
    header('Content-Description: File Transfer');
    header('Content-Type: ' . $mime);
    header('Content-Disposition: inline; filename="' . basename($fullPath) . '"');
    header('Content-Length: ' . filesize($fullPath));
    flush();
    readfile($fullPath);
    exit;
}

// =========================================================================
// ROTA POST / PUT / PATCH — SUPORTE UNIVERSAL DE UPLOAD
// =========================================================================
if (in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT', 'PATCH'])) {

    // 1. Upload via Formulário Multipart ($_FILES Tradicional)
    if (!empty($_FILES)) {
        $fileKey = key($_FILES);
        
        // Trata Envio de Chunks via FormData
        if (isset($_POST['chunkIndex'])) {
            $fileId      = $_POST['fileId'] ?? uniqid('upload_', true);
            $chunkIndex  = intval($_POST['chunkIndex']);
            $totalChunks = intval($_POST['totalChunks'] ?? 1);
            $fileName    = basename($_POST['fileName'] ?? ('file_' . time()));

            $chunkDir = BASE_DIR . '/.parts_' . md5($fileId);
            if (!is_dir($chunkDir)) @mkdir($chunkDir, 0777, true);

            $tmpName = $_FILES[$fileKey]['tmp_name'] ?? null;
            if (!$tmpName || !is_uploaded_file($tmpName)) {
                json_response(["status" => "error", "message" => "Chunk inválido."], 400);
            }

            move_uploaded_file($tmpName, "{$chunkDir}/part_{$chunkIndex}");

            if ($chunkIndex + 1 === $totalChunks) {
                $finalRel = $fileRelPath !== '' ? $fileRelPath : $fileName;
                $finalRel = neutralizaExtensaoPhp(sanitizaCaminho($finalRel));
                $finalPath = BASE_DIR . '/' . $finalRel;

                if (!is_dir(dirname($finalPath))) @mkdir(dirname($finalPath), 0777, true);
                
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

                enviarBackupFirebase($finalPath, $finalRel, $firebaseBaseUrl, $raizStorage, $domainDir);
                json_response(["status" => "complete", "url" => build_clean_public_url($finalRel)]);
            }

            json_response(["status" => "partial", "progress" => $chunkIndex + 1]);
        } 
        
        // Upload Único Padrão ($_FILES)
        else if (isset($_FILES[$fileKey]['tmp_name']) && is_uploaded_file($_FILES[$fileKey]['tmp_name'])) {
            $fileName = basename($_FILES[$fileKey]['name'] ?? ('file_' . time()));
            $finalRel = $fileRelPath !== '' ? $fileRelPath : $fileName;
            $finalRel = neutralizaExtensaoPhp(sanitizaCaminho($finalRel));
            $finalPath = BASE_DIR . '/' . $finalRel;

            if (!is_dir(dirname($finalPath))) @mkdir(dirname($finalPath), 0777, true);

            if (move_uploaded_file($_FILES[$fileKey]['tmp_name'], $finalPath)) {
                enviarBackupFirebase($finalPath, $finalRel, $firebaseBaseUrl, $raizStorage, $domainDir);
                json_response(["status" => "complete", "url" => build_clean_public_url($finalRel)]);
            }
        }
    }

    // 2. Upload via Raw Stream / Texto / Binário / Base64 (php://input)
    $finalRel = $fileRelPath !== '' ? $fileRelPath : ($_SERVER['HTTP_X_FILE_NAME'] ?? ('upload_' . time()));
    $finalRel = neutralizaExtensaoPhp(sanitizaCaminho($finalRel));
    $finalPath = BASE_DIR . '/' . $finalRel;

    if (!is_dir(dirname($finalPath))) @mkdir(dirname($finalPath), 0777, true);

    $rawContent = file_get_contents('php://input');

    if (!empty($rawContent)) {
        // Verifica e Decodifica caso o payload enviado seja Base64 puro
        if (preg_match('/^data:(.*?);base64,/', $rawContent, $matches)) {
            $rawContent = base64_decode(substr($rawContent, strpos($rawContent, ',') + 1));
        } elseif (base64_encode(base64_decode($rawContent, true)) === $rawContent && !mb_check_encoding($rawContent, 'UTF-8')) {
            $rawContent = base64_decode($rawContent);
        }

        file_put_contents($finalPath, $rawContent);

        if (filesize($finalPath) > 0) {
            enviarBackupFirebase($finalPath, $finalRel, $firebaseBaseUrl, $raizStorage, $domainDir);
            json_response(["status" => "complete", "url" => build_clean_public_url($finalRel)]);
        }
    }

    json_response(["status" => "error", "message" => "Nenhum conteúdo válido foi recebido."], 400);
}

http_response_code(405);
echo "Método não permitido.";
exit;
?>
