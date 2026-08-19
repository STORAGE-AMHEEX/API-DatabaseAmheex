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
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

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

/**
 * Monta o caminho da chave do nó do Firebase
 */
function obterNodePathFirebase($relativeFilePath, $raizStorage, $domainDir) {
    $parts = explode('/', $relativeFilePath);
    $sanitizedParts = array_map('sanitize_firebase_key', $parts);
    $formattedFilePath = implode('/', array_filter($sanitizedParts));
    return $raizStorage . '/' . $domainDir . '/' . $formattedFilePath;
}

/**
 * Faz o Backup/Upload das partes de um arquivo local para o Firebase
 */
function enviarBackupFirebase($fullLocalPath, $relativeFilePath, $firebaseBaseUrl, $raizStorage, $domainDir) {
    if (!file_exists($fullLocalPath) || filesize($fullLocalPath) === 0) {
        return false;
    }

    $nodePath = obterNodePathFirebase($relativeFilePath, $raizStorage, $domainDir);
    $inputData = file_get_contents($fullLocalPath);
    $hexData = bin2hex($inputData);
    
    // Particionamento: 1MB de caracteres por parte (1048576)
    $chunks = str_split($hexData, 1048576); 

    foreach ($chunks as $idx => $chunk) {
        $partName = "part_" . ($idx + 1);
        $url = $firebaseBaseUrl . $nodePath . '/' . $partName . '.json';
        firebase_request($url, 'PUT', $chunk);
    }

    @file_put_contents('up_on.txt', '1');
    return true;
}

/**
 * Puxa as partições da API do Firebase e reconstrói o arquivo local na pasta X
 */
function restaurarArquivoFirebase($fullLocalPath, $relativeFilePath, $firebaseBaseUrl, $raizStorage, $domainDir) {
    $nodePath = obterNodePathFirebase($relativeFilePath, $raizStorage, $domainDir);
    
    $fullHexContent = "";
    $part = 1;
    $foundAny = true;

    while ($foundAny) {
        $partName = "part_$part";
        $url = $firebaseBaseUrl . $nodePath . '/' . $partName . '.json';
        
        $response = firebase_request($url, 'GET');
        
        if ($response !== false && $response !== 'null' && !empty($response)) {
            $data = json_decode($response, true);
            if (is_string($data) && !empty($data)) {
                $cleanHex = str_replace(array(' ', "\r", "\n", "\t"), '', $data);
                $fullHexContent .= $cleanHex;
                $part++;
            } else {
                $foundAny = false;
            }
        } else {
            $foundAny = false;
        }
    }

    if (!empty($fullHexContent)) {
        if (strlen($fullHexContent) % 2 !== 0) {
            $fullHexContent = substr($fullHexContent, 0, -1);
        }

        $originalBinary = @hex2bin($fullHexContent);
        
        if ($originalBinary !== false) {
            $fileDir = dirname($fullLocalPath);
            if (!is_dir($fileDir)) {
                @mkdir($fileDir, 0777, true);
            }
            @file_put_contents($fullLocalPath, $originalBinary);
            @file_put_contents('up_on.txt', '1');
            return true;
        }
    }

    return false;
}

// =========================================================================
// OBTENÇÃO DO CAMINHO RELATIVO DA URL (SEM PARAMETROS GET)
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

    // Se o arquivo não existir ou for de tamanho zerado, busca e reconstitui do Firebase
    if (!file_exists($fullPath) || filesize($fullPath) <= 1) {
        $restaurado = restaurarArquivoFirebase($fullPath, $fileRelPath, $firebaseBaseUrl, $raizStorage, $domainDir);
        
        if (!$restaurado) {
            http_response_code(404);
            echo "Arquivo não encontrado no servidor ou no banco de dados.";
            exit;
        }
        
        // Redireciona para atualizar a chamada após reconstruir localmente o binário na pasta X
        header("Location: " . $requestUri);
        exit;
    }

    // Servir o arquivo localmente
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
// ROTA POST — UPLOAD EM PARTES OU VIA STREAMING + ENVIO FIREBASE
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $json = function(array $data, int $code = 200) {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    };

    // --- Upload via Chunks ($_FILES) ---
    if (!empty($_FILES['file'])) {
        $fileId      = $_POST['fileId'] ?? uniqid('upload_', true);
        $chunkIndex  = intval($_POST['chunkIndex'] ?? 0);
        $totalChunks = intval($_POST['totalChunks'] ?? 1);
        $fileName    = basename($_POST['fileName'] ?? ('file_' . time()));

        $chunkDir = BASE_DIR . '/.parts_' . md5($fileId);
        if (!is_dir($chunkDir)) @mkdir($chunkDir, 0777, true);

        $tmpName = $_FILES['file']['tmp_name'] ?? null;
        if (!$tmpName || !is_uploaded_file($tmpName)) {
            $json(["status" => "error", "message" => "Nenhum chunk válido."], 400);
        }

        $partPath = "{$chunkDir}/part_{$chunkIndex}";
        move_uploaded_file($tmpName, $partPath);

        // Quando o último chunk for recebido, realiza a união
        if ($chunkIndex + 1 === $totalChunks) {
            $finalRel = $fileRelPath !== '' ? $fileRelPath : $fileName;
            $finalRel = sanitizaCaminho($finalRel);
            $finalRel = neutralizaExtensaoPhp($finalRel);
            
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

            // Envia backup das partes ao Firebase Realtime Database
            enviarBackupFirebase($finalPath, $finalRel, $firebaseBaseUrl, $raizStorage, $domainDir);

            $url = build_clean_public_url($finalRel);
            $json(["status" => "complete", "url" => $url]);
        }

        $json(["status" => "partial", "progress" => $chunkIndex + 1]);
    }

    // --- Upload via Direct Streaming (php://input) ---
    $input = fopen('php://input', 'rb');
    if ($input) {
        $finalRel = $fileRelPath ?: ('upload_' . time());
        $finalRel = sanitizaCaminho($finalRel);
        $finalRel = neutralizaExtensaoPhp($finalRel);
        
        $finalPath = BASE_DIR . '/' . $finalRel;
        if (!is_dir(dirname($finalPath))) @mkdir(dirname($finalPath), 0777, true);
        
        $out = fopen($finalPath, 'wb');
        stream_copy_to_stream($input, $out);
        fclose($out);
        fclose($input);

        if (filesize($finalPath) > 0) {
            // Envia backup das partes ao Firebase Realtime Database
            enviarBackupFirebase($finalPath, $finalRel, $firebaseBaseUrl, $raizStorage, $domainDir);

            $url = build_clean_public_url($finalRel);
            $json(["status" => "complete", "url" => $url]);
        }
    }

    $json(["status" => "error", "message" => "Nenhum conteúdo recebido."], 400);
}

http_response_code(405);
echo "Método não permitido.";
exit;
?>
