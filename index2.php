<?php
/**
 * Core: Cyber-Sync-Core v7.5
 * Função: Sincronização, Fragmentação e Backup Local
 */

@ini_set('memory_limit', '16000M');
@set_time_limit(0);

// Limpa buffers para saída em tempo real se necessário
while (ob_get_level()) ob_end_clean();

header("Access-Control-Allow-Origin: *");
header("X-Powered-By: Cyber-Sync-Core-Advanced");

// Configurações de API e Cluster
$jsonUrl = 'https://api-amheex.onrender.com/index.json';
$context = stream_context_create([
    "http" => ["header" => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) CyberSync/7.5\r\n"]
]);

$jsonRaw = @file_get_contents($jsonUrl, false, $context);
$logins = $jsonRaw ? json_decode($jsonRaw, true) : [];

// Identificação do Path após o domínio
$path = ltrim($_SERVER['PATH_INFO'] ?? '', '/');
if (!$path) {
    $uri = explode('?', $_SERVER['REQUEST_URI'])[0];
    $path = ltrim(str_replace($_SERVER['SCRIPT_NAME'], '', $uri), '/');
}

if (!$path || $path === 'index.php') exit("CORE_ERROR: INVALID_PATH");

// Definição da Raiz de Armazenamento Local
$storageFolder = 'X';
$relativeFilePath = $storageFolder . '/' . $path;
$localPath = __DIR__ . '/' . $relativeFilePath;

// ======================================================
// 📥 PROCESSAMENTO GET (DOWNLOAD / SYNC)
// ======================================================
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    
    // Se NÃO existe localmente, vamos buscar no cluster (Backup System)
    if (!file_exists($localPath)) {
        $fullContent = "";
        $part = 1; 
        $foundAny = true;

        if (empty($logins)) exit("CORE_ERROR: NO_REMOTE_SERVERS_CONFIGURED");

        while ($foundAny) {
            $partName = "part_$part.txt"; 
            $foundPart = false;
            
            foreach ($logins as $cfg) {
                $conn = @ftp_connect($cfg['host'], 21, 15);
                if ($conn && @ftp_login($conn, $cfg['user'], $cfg['pass'])) {
                    ftp_pasv($conn, true);
                    
                    // Paths possíveis no FTP
                    $targets = [
                        "htdocs/$storageFolder/$path/$partName", 
                        "public_html/$storageFolder/$path/$partName", 
                        "$storageFolder/$path/$partName"
                    ];

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
            
            if ($foundPart) {
                $part++;
            } else {
                $foundAny = false; // Não há mais partes
            }
        }

        // Se conseguiu baixar algo, salva localmente para futuros acessos
        if ($fullContent !== "") {
            $localDir = dirname($localPath);
            if (!is_dir($localDir)) @mkdir($localDir, 0777, true);
            @file_put_contents($localPath, $fullContent);
        } else { 
            http_response_code(404); 
            exit("ERROR: FILE_NOT_IN_LOCAL_OR_CLUSTER"); 
        }
    }

    // Gerar URL de acesso direto
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
    $redirectUrl = $protocol . $_SERVER['HTTP_HOST'] . '/' . $relativeFilePath;

    // Interface de Espera/Sincronização
    header("Refresh: 5; url=" . $redirectUrl);
    echo "
    <div style='background:#0f172a; color:#f8fafc; height:100vh; display:flex; flex-direction:column; justify-content:center; align-items:center; font-family:sans-serif;'>
        <div style='border:4px solid #334155; border-top-color:#38bdf8; width:40px; height:40px; border-radius:50%; animation:s 1s linear infinite;'></div>
        <h2 style='color:#38bdf8'>Sincronizando Localmente</h2>
        <p style='color:#94a3b8'>O arquivo está pronto na pasta <b>$storageFolder/</b>.</p>
        <a href='$redirectUrl' style='color:#facc15; text-decoration:none;'>Redirecionando em 5 segundos...</a>
        <style>@keyframes s { to {transform:rotate(360deg)}}</style>
    </div>";
    exit;
}

// ======================================================
// 📤 PROCESSAMENTO POST (UPLOAD & FRAGMENTAÇÃO)
// ======================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $inputData = file_get_contents('php://input');
    if (empty($inputData)) exit("UPLOAD_ERROR: NO_DATA");
    if (empty($logins)) exit("UPLOAD_ERROR: NO_REMOTE_CLUSTER");

    // Remove arquivo local se existir para atualizar com o novo upload
    if (file_exists($localPath)) @unlink($localPath);

    // Fragmentação (Chunks de 512KB)
    $chunks = str_split($inputData, 1024 * 512);
    $totalParts = count($chunks); 
    $uploadedParts = 0;

    foreach ($chunks as $idx => $chunk) {
        $pName = "part_" . ($idx + 1) . ".txt";
        $successChunk = false;

        foreach ($logins as $cfg) {
            $c = @ftp_connect($cfg['host'], 21, 10);
            if ($c && @ftp_login($c, $cfg['user'], $cfg['pass'])) {
                ftp_pasv($c, true);
                
                // Navega/Cria estrutura htdocs ou public_html
                @ftp_chdir($c, "htdocs") || @ftp_chdir($c, "public_html");
                
                // Garante que a pasta 'X' exista
                if (!@ftp_chdir($c, $storageFolder)) { 
                    @ftp_mkdir($c, $storageFolder); 
                    @ftp_chdir($c, $storageFolder); 
                }

                // Cria a árvore de diretórios do $path
                $dirs = explode('/', $path);
                foreach ($dirs as $d) { 
                    if (!empty($d) && !@ftp_chdir($c, $d)) { 
                        if (@ftp_mkdir($c, $d)) @ftp_chdir($c, $d); 
                    } 
                }

                // Upload do fragmento
                $tmp = tmpfile(); 
                fwrite($tmp, $chunk); 
                rewind($tmp);
                
                if (@ftp_fput($c, $pName, $tmp, FTP_BINARY)) { 
                    $successChunk = true; 
                    @ftp_close($c); 
                    break; 
                }
                @ftp_close($c);
            }
        }
        if ($successChunk) $uploadedParts++;
    }

    echo ($uploadedParts === $totalParts ? "SYNC_SUCCESS" : "SYNC_PARTIAL_ERROR");
    exit;
}        }
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
