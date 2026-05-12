<?php
/**
 * Core: Cyber-Sync-Core v8.0
 * Regra: Se existe local -> Sobe (Upload/Frag). Se não existe -> Baixa (Download/Merge).
 */

@ini_set('memory_limit', '16000M');
@set_time_limit(0);

header("Access-Control-Allow-Origin: *");
header("X-Powered-By: Cyber-Sync-Core-Smart");

// Configurações do Cluster
$jsonUrl = 'https://api-amheex.onrender.com/index.json';
$context = stream_context_create([
    "http" => ["header" => "User-Agent: Mozilla/5.0 CyberSync/8.0\r\n"]
]);

$jsonRaw = @file_get_contents($jsonUrl, false, $context);
$logins = $jsonRaw ? json_decode($jsonRaw, true) : [];

// Tratamento de Path
$path = ltrim($_SERVER['PATH_INFO'] ?? '', '/');
if (!$path) {
    $uri = explode('?', $_SERVER['REQUEST_URI'])[0];
    $path = ltrim(str_replace($_SERVER['SCRIPT_NAME'], '', $uri), '/');
}

if (!$path || $path === 'index.php') exit("CORE_ERROR: INVALID_PATH");

$storageFolder = 'X';
$localPath = __DIR__ . '/' . $storageFolder . '/' . $path;

// ======================================================
// 🔄 LÓGICA DE SINCRONIZAÇÃO (MÉTODO GET)
// ======================================================
if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    if (file_exists($localPath)) {
        // --- CASO 1: ARQUIVO EXISTE LOCALMENTE -> REALIZAR UPLOAD/FRAGMENTAÇÃO ---
        $inputData = file_get_contents($localPath);
        $chunks = str_split($inputData, 1024 * 512); // Partes de 512KB
        $total = count($chunks);
        $done = 0;

        foreach ($chunks as $idx => $chunk) {
            $pName = "part_" . ($idx + 1) . ".txt";
            foreach ($logins as $cfg) {
                $c = @ftp_connect($cfg['host'], 21, 10);
                if ($c && @ftp_login($c, $cfg['user'], $cfg['pass'])) {
                    ftp_pasv($c, true);
                    @ftp_chdir($c, "htdocs") || @ftp_chdir($c, "public_html");
                    if (!@ftp_chdir($c, $storageFolder)) { @ftp_mkdir($c, $storageFolder); @ftp_chdir($c, $storageFolder); }
                    
                    foreach (explode('/', $path) as $d) {
                        if (!empty($d) && !@ftp_chdir($c, $d)) { if (@ftp_mkdir($c, $d)) @ftp_chdir($c, $d); }
                    }

                    $tmp = tmpfile(); fwrite($tmp, $chunk); rewind($tmp);
                    if (@ftp_fput($c, $pName, $tmp, FTP_BINARY)) { $done++; @ftp_close($c); break; }
                    @ftp_close($c);
                }
            }
        }
        // Após sincronizar com o servidor, entrega o arquivo para o navegador
        header("Content-Type: application/octet-stream");
        header("Content-Disposition: inline; filename=\"".basename($localPath)."\"");
        readfile($localPath);
        exit;

    } else {
        // --- CASO 2: ARQUIVO NÃO EXISTE -> BAIXAR DO CLUSTER ---
        $fullContent = "";
        $part = 1;
        $foundAny = true;

        while ($foundAny) {
            $partName = "part_$part.txt";
            $foundPart = false;
            foreach ($logins as $cfg) {
                $conn = @ftp_connect($cfg['host'], 21, 10);
                if ($conn && @ftp_login($conn, $cfg['user'], $cfg['pass'])) {
                    ftp_pasv($conn, true);
                    $targets = ["htdocs/$storageFolder/$path/$partName", "public_html/$storageFolder/$path/$partName", "$storageFolder/$path/$partName"];
                    foreach ($targets as $t) {
                        $tmp = fopen('php://temp', 'r+b');
                        if (@ftp_fget($conn, $tmp, $t, FTP_BINARY)) {
                            rewind($tmp); $fullContent .= stream_get_contents($tmp);
                            $foundPart = true; fclose($tmp); break;
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
            if (!is_dir(dirname($localPath))) @mkdir(dirname($localPath), 0777, true);
            @file_put_contents($localPath, $fullContent);
            header("Content-Type: application/octet-stream");
            echo $fullContent;
        } else {
            http_response_code(404);
            exit("ERROR: FILE_NOT_FOUND_ANYWHERE");
        }
        exit;
    }
}

// ======================================================
// 📤 UPLOAD DIRETO (MÉTODO POST)
// ======================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $inputData = file_get_contents('php://input');
    if (empty($inputData)) exit("ERROR: EMPTY_POST");

    // Salva localmente primeiro
    if (!is_dir(dirname($localPath))) @mkdir(dirname($localPath), 0777, true);
    @file_put_contents($localPath, $inputData);
    
    echo "LOCAL_SAVE_SUCCESS_TRIGGERING_SYNC";
    exit;
}
