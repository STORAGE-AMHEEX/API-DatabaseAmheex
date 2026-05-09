<?php
// ===============================================
// 🔧 AJUSTES PHP — SUPORTE A ARQUIVOS GRANDES E UPLOADS LONGOS
// ===============================================

// require_once "protection.php";

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

$indexJson = __DIR__ . '/index.json';
$remoteServers = is_file($indexJson) ? (json_decode(file_get_contents($indexJson), true) ?: []) : [];

// === Funções auxiliares ===
function sanitizaCaminho($path) {
    $path = trim($path, "/\\");
    $path = str_replace(['..\\', '../'], '', $path);
    $path = preg_replace('/[^a-zA-Z0-9_\-\.\@\/]/', '', $path);
    $path = preg_replace('#/+#', '/', $path);
    return $path;
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

// ======================================================
// 🔄 REPLICAÇÃO E DOWNLOAD USANDO FTP
// ======================================================
function try_download_from_server($cfg, $fileRelPath, &$outContent) {
    if (!isset($cfg['host'], $cfg['user'], $cfg['pass'])) return false;
    $ftp = @ftp_connect($cfg['host'], 21, 15);
    if (!$ftp || !@ftp_login($ftp, $cfg['user'], $cfg['pass'])) return false;
    ftp_pasv($ftp, true);
    $tmp = tempnam(sys_get_temp_dir(), "ftpget_");
    $fp = fopen($tmp, 'w+b');
    $ok = @ftp_fget($ftp, $fp, '#/'.$fileRelPath, FTP_BINARY);
    fclose($fp);
    ftp_close($ftp);
    if ($ok && filesize($tmp) > 0) {
        $outContent = file_get_contents($tmp);
        unlink($tmp);
        return true;
    }
    @unlink($tmp);
    return false;
}

function send_file_to_server($cfg, $fileRelPath, $localFilePath) {
    if (!isset($cfg['host'], $cfg['user'], $cfg['pass'])) return [false, "Configuração FTP inválida"];
    if (!is_file($localFilePath)) return [false, "Arquivo local não encontrado"];
    $ftp = @ftp_connect($cfg['host'], 21, 20);
    if (!$ftp || !@ftp_login($ftp, $cfg['user'], $cfg['pass'])) return [false, "Falha FTP login"];
    ftp_pasv($ftp, true);
    $dir = '#/'.(dirname($fileRelPath)!='.'?trim(dirname($fileRelPath),'/'):'');
    $t='';
    foreach(explode('/',$dir) as $dp){
        if($dp==='') continue;
        $t.=(($t==='')?'':'/').$dp;
        @ftp_mkdir($ftp,$t);
    }
    $stream=fopen($localFilePath,'r');
    $ok=@ftp_fput($ftp,"#/".$fileRelPath,$stream,FTP_BINARY);
    fclose($stream);
    ftp_close($ftp);
    return [$ok,"FTP ".($ok?"OK":"Falhou")];
}

function send_file_to_servers_fallback($servers,$fileRelPath,$localFilePath){
    if (empty($servers)) {
        error_log("⚠️ Nenhum servidor remoto configurado.");
        return false;
    }
    foreach($servers as $srv){
        [$ok,$resp]=send_file_to_server($srv,$fileRelPath,$localFilePath);
        if($ok){
            error_log("✅ Upload replicado com sucesso via FTP para {$srv['host']}");
            return true;
        } else {
            error_log("⚠️ Falha ao enviar via FTP para {$srv['host']}: {$resp}");
        }
    }
    error_log("❌ Nenhum servidor aceitou o envio de {$fileRelPath}");
    return false;
}

// ======================================================
// SISTEMA DE DOWNLOAD DIRETO
// ======================================================
if (isset($_GET['download'])) {
    $fileParam = sanitizaCaminho($_GET['download']);
    $fullPath = BASE_DIR . '/' . $fileParam;

    if (!is_file($fullPath)) {
        foreach ($remoteServers as $srv) {
            if (try_download_from_server($srv, $fileParam, $content)) {
                if (!is_dir(dirname($fullPath))) mkdir(dirname($fullPath), 0777, true);
                file_put_contents($fullPath, $content);
                break;
            }
        }
    }

    if (!is_file($fullPath)) {
        http_response_code(404);
        echo "Arquivo não encontrado.";
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
$fullPath = BASE_DIR . '/' . $fileRelPath;

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
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

    foreach ($remoteServers as $srv) {
        if (try_download_from_server($srv, $fileRelPath, $content)) {
            if (!is_dir(dirname($fullPath))) mkdir(dirname($fullPath), 0777, true);
            file_put_contents($fullPath, $content);
            header("Location: " . build_public_url($fileRelPath));
            exit;
        }
    }
    // Aruqivo não encontrado...
    http_response_code(404);
    echo "null";
    exit;
}

// ======================================================
// POST — Upload inteligente com fallback automático
// ======================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $targetRel = $fileRelPath;
    $json = function(array $data, int $code = 200) {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
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
            $replicado = send_file_to_servers_fallback($GLOBALS['remoteServers'],$finalRel,$finalPath);
            $url = build_clean_public_url($finalRel);
            $json(["status"=>"complete","url"=>$url,"replicado"=>$replicado]);
        }

        $json(["status"=>"partial","progress"=>$chunkIndex+1]);
    }

    // --- Upload via php://input (streaming) ---
    $input = fopen('php://input','rb');
    if ($input) {
        $finalRel = $fileRelPath ?: ('upload_' . time());
        $finalRel = sanitizaCaminho($finalRel);
        $finalPath = BASE_DIR . '/' . $finalRel;
        if (!is_dir(dirname($finalPath))) mkdir(dirname($finalPath), 0777, true);
        $out = fopen($finalPath,'wb');
        stream_copy_to_stream($input,$out);
        fclose($out); fclose($input);
        $replicado = send_file_to_servers_fallback($GLOBALS['remoteServers'],$finalRel,$finalPath);
        $url = build_clean_public_url($finalRel);
        $json(["status"=>"complete","url"=>$url,"replicado"=>$replicado]);
    }

    $json(["status"=>"error","message"=>"Nenhum conteúdo recebido."],400);
}

http_response_code(405);
echo "Método inválido.";
exit;
?>