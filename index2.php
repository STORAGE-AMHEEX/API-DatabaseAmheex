<?php
@ini_set('memory_limit', '16000M');
@set_time_limit(0);

// Limpa buffers de saída para garantir que o HTML de espera seja enviado imediatamente
while (ob_get_level()) ob_end_clean();

header("Access-Control-Allow-Origin: *");
header("X-Powered-By: Cyber-Sync-Core");

$jsonUrl = 'https://api-amheex.onrender.com/index.json';
$context = stream_context_create([
    "http" => ["header" => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) CyberSync/7.2\r\n"]
]);

$jsonRaw = @file_get_contents($jsonUrl, false, $context);
$logins = $jsonRaw ? json_decode($jsonRaw, true) : [];

$path = ltrim($_SERVER['PATH_INFO'] ?? '', '/');
if (!$path) {
    $uri = explode('?', $_SERVER['REQUEST_URI'])[0];
    $path = ltrim(str_replace($_SERVER['SCRIPT_NAME'], '', $uri), '/');
}

if (!$path || $path === 'index.php') exit("CORE_ERROR: INVALID_PATH");

// Caminho local onde o arquivo será salvo
$relativeFilePath = 'X/' . $path;
$localPath = __DIR__ . '/' . $relativeFilePath;

// ======================================================
// 📥 PROCESSAMENTO GET (DOWNLOAD / SYNC)
// ======================================================
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!file_exists($localPath)) {
        $fullContent = "";
        $part = 1; 
        $foundAny = true;

        if (empty($logins)) exit("CORE_ERROR: NO_REMOTE_SERVERS_LOADED");

        while ($foundAny) {
            $partName = "part_$part.txt"; 
            $foundPart = false;
            
            foreach ($logins as $cfg) {
                $conn = @ftp_connect($cfg['host'], 21, 10);
                if ($conn && @ftp_login($conn, $cfg['user'], $cfg['pass'])) {
                    ftp_pasv($conn, true);
                    $targets = ["htdocs/X/$path/$partName", "public_html/X/$path/$partName", "X/$path/$partName"];
                    foreach ($targets as $t) {
                        $tmp = fopen('php://temp', 'r+b');
                        if (@ftp_fget($conn, $tmp, $t, FTP_BINARY)) {
                            rewind($tmp); 
                            $fullContent .= stream_get_contents($tmp);
                            $foundPart = true; 
                            fclose($tmp); 
                            break;
                        }
                        fclose($tmp);
                    }
                    @ftp_close($conn); 
                    if ($foundPart) break;
                }
            }
            if ($foundPart) $part++; else $foundAny = false;
        }

        if ($fullContent !== "") {
            $localDir = dirname($localPath);
            if (!is_dir($localDir)) @mkdir($localDir, 0777, true);
            @file_put_contents($localPath, $fullContent);
        } else { 
            http_response_code(404); 
            exit("FILE_NOT_FOUND_IN_CLUSTER"); 
        }
    }

    // ======================================================
    // 🔄 REDIRECIONAMENTO COM DELAY (10 SEGUNDOS)
    // ======================================================
    
    // Constrói a URL de redirecionamento
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'];
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
    $basePath = rtrim($scriptDir, '/');
    $redirectUrl = $protocol . $host . $basePath . '/' . $relativeFilePath;

    // Envia o cabeçalho de atualização para 10 segundos
    header("Refresh: 10; url=" . $redirectUrl);
    
    // Interface visual de espera
    echo "<!DOCTYPE html>
    <html lang='pt-br'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Sincronizando Arquivo</title>
        <style>
            body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; background-color: #0f172a; color: #f8fafc; }
            .container { text-align: center; background: #1e293b; padding: 30px; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.3); border: 1px solid #334155; max-width: 400px; }
            .loader { border: 4px solid #334155; border-top: 4px solid #38bdf8; border-radius: 50%; width: 40px; height: 40px; animation: spin 1s linear infinite; margin: 20px auto; }
            h2 { margin-bottom: 10px; color: #38bdf8; }
            p { color: #94a3b8; font-size: 0.9rem; line-height: 1.5; }
            .timer { font-weight: bold; color: #facc15; }
            @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
            a { color: #38bdf8; text-decoration: none; font-size: 0.8rem; opacity: 0.7; }
            a:hover { opacity: 1; text-decoration: underline; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='loader'></div>
            <h2>Processando Download</h2>
            <p>O arquivo foi reconstruído com sucesso!<br>Aguarde <span class='timer'>10 segundos</span> para liberar o link de acesso direto.</p>
            <br>
            <a href='$redirectUrl'>Clique aqui se não for redirecionado automaticamente</a>
        </div>
    </body>
    </html>";
    exit;
}

// ======================================================
// 📤 PROCESSAMENTO POST (UPLOAD)
// ======================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $inputData = file_get_contents('php://input');
    if (empty($inputData) || empty($logins)) exit("ERROR_INVALID_STATE");
    
    if (file_exists($localPath)) @unlink($localPath);
    $chunks = str_split($inputData, 1024 * 512);
    $total = count($chunks); 
    $success = 0;

    foreach ($chunks as $idx => $chunk) {
        $pName = "part_" . ($idx + 1) . ".txt";
        foreach ($logins as $cfg) {
            $c = @ftp_connect($cfg['host'], 21, 10);
            if ($c && @ftp_login($c, $cfg['user'], $cfg['pass'])) {
                ftp_pasv($c, true);
                @ftp_chdir($c, "htdocs") || @ftp_chdir($c, "public_html");
                if (!@ftp_chdir($c, "X")) { @ftp_mkdir($c, "X"); @ftp_chdir($c, "X"); }
                foreach (explode('/', $path) as $d) { 
                    if (!empty($d) && !@ftp_chdir($c, $d)) { 
                        if (@ftp_mkdir($c, $d)) @ftp_chdir($c, $d); 
                    } 
                }
                $tmp = tmpfile(); fwrite($tmp, $chunk); rewind($tmp);
                if (@ftp_fput($c, $pName, $tmp, FTP_BINARY)) { 
                    $success++; 
                    @ftp_close($c); 
                    break; 
                }
                @ftp_close($c);
            }
        }
    }
    echo ($success === $total ? "OK" : "PARTIAL_ERROR");
    exit;
}
