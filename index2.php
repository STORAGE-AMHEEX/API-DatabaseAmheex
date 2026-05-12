<?php
@ini_set('memory_limit', '16000M');
@set_time_limit(0);
while (ob_get_level()) ob_end_clean();

header("Content-Type: text/plain");
header("X-System: Cyber-Sync-V9-Diagnostic");

// 1. Configuração de Logins (API Externa)
$jsonUrl = 'https://api-amheex.onrender.com/index.json';
$context = stream_context_create(["http" => ["header" => "User-Agent: CyberSync/9.0\r\n"]]);
$jsonRaw = @file_get_contents($jsonUrl, false, $context);
$logins = json_decode($jsonRaw, true);

if (empty($logins)) exit("CRITICAL_ERROR: NO_SERVERS_IN_API");

// 2. Definição de Paths
$path = ltrim($_SERVER['PATH_INFO'] ?? '', '/');
if (!$path) {
    $uri = explode('?', $_SERVER['REQUEST_URI'])[0];
    $path = ltrim(str_replace($_SERVER['SCRIPT_NAME'], '', $uri), '/');
}
if (!$path || $path === 'index.php') exit("PATH_NOT_DEFINED");

$localPath = __DIR__ . '/X/' . $path;
$remoteDir = 'X/' . dirname($path);

// --- FUNÇÃO DE CONEXÃO ROBUSTA ---
function connectSync($logins) {
    foreach ($logins as $cfg) {
        $c = @ftp_connect($cfg['host'], 21, 15);
        if ($c && @ftp_login($c, $cfg['user'], $cfg['pass'])) {
            ftp_pasv($c, true);
            // Tenta localizar a raiz do servidor web
            @ftp_chdir($c, "htdocs") || @ftp_chdir($c, "public_html") || @ftp_chdir($c, "/");
            return $c;
        }
    }
    return false;
}

// --- EXECUÇÃO ---

if (file_exists($localPath)) {
    /**
     * MODO UPLOAD: Fragmenta e envia
     */
    $conn = connectSync($        }
    }
    return false;
}

// --- EXECUÇÃO ---

if (file_exists($localPath)) {
    /**
     * MODO UPLOAD: Fragmenta e envia
     */
    $conn = connectSync($/**
 * Tenta conectar em um servidor FTP válido da lista
 */
function getActiveFtp($logins) {
    foreach ($logins as $cfg) {
        $c = @ftp_connect($cfg['host'], 21, 10);
        if ($c && @ftp_login($c, $cfg['user'], $cfg['pass'])) {
            ftp_pasv($c, true);
            // Tenta entrar na pasta web padrão
            @ftp_chdir($c, "htdocs") || @ftp_chdir($c, "public_html");
            return $c;
        }
    }
    return false;
}

// --- LÓGICA DE SINCRONIZAÇÃO ---

if (file_exists($localPath)) {
    /**
     * MODO UPLOAD (O arquivo existe no seu PC/Servidor)
     */
    $conn = getActiveFtp($logins);
    if (!$conn) exit("FTP_AUTH_FAILED");

    // Cria a estrutura de pastas no FTP
    $remoteFolders = explode('/', 'X/' . $path);
    array_pop($remoteFolders); // Remove o nome do arquivo da lista de pastas
    foreach ($remoteFolders as $folder) {
        if (empty($folder) || $folder == '.') continue;
        if (!@ftp_chdir($conn, $folder)) {
            @ftp_mkdir($conn, $folder);
            @ftp_chdir($conn, $folder);
        }
    }

    // Fragmenta o arquivo em memória para upload
    $data = file_get_contents($localPath);
    $chunks = str_split($data, $chunkSize);
    $total = count($chunks);
    $success = 0;

    foreach ($chunks as $idx => $chunk) {
        $pName = "part_" . ($idx + 1) . ".bin";
        $tmp = tmpfile();
        fwrite($tmp, $chunk);
        rewind($tmp);

        if (@ftp_fput($conn, $pName, $tmp, FTP_BINARY)) {
            $success++;
        }
        fclose($tmp);
    }

    @ftp_close($conn);
    echo ($success === $total) ? "OK: UPLOAD_AND_FRAGMENTED" : "ERROR: PARTIAL_UPLOAD";

} else {
    /**
     * MODO DOWNLOAD (O arquivo NÃO existe local, vamos buscar os pedaços no FTP)
     */
    $conn = getActiveFtp($logins);
    if (!$conn) exit("FTP_AUTH_FAILED");

    // Tenta navegar até o diretório no FTP
    if (!@ftp_chdir($conn, 'X/' . dirname($path))) {
        exit("ERROR: REMOTE_PATH_NOT_FOUND");
    }

    // Lista arquivos do tipo part_*.bin
    $files = ftp_nlist($conn, ".");
    $parts = array_filter($files, function($f) { return strpos($f, 'part_') !== false; });
    
    if (empty($parts)) exit("ERROR: NO_FRAGMENTS_FOUND_ON_SERVER");

    // Ordenação natural (1, 2, 3... em vez de 1, 10, 2)
    natsort($parts);

    // Cria a pasta local se não existir
    if (!is_dir(dirname($localPath))) {
        mkdir(dirname($localPath), 0777, true);
    }

    $handle = fopen($localPath, 'wb');
    foreach ($parts as $pName) {
        $tmp = tmpfile();
        if (@ftp_fget($conn, $tmp, $pName, FTP_BINARY)) {
            rewind($tmp);
            fwrite($handle, stream_get_contents($tmp));
        }
        fclose($tmp);
    }
    fclose($handle);

    @ftp_close($conn);
    echo "OK: DOWNLOADED_AND_DEFRAGMENTED";
}
