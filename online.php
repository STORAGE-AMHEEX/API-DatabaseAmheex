<?php
// ======================================================
// 🚀 SMART SYNC SYSTEM V5 - LOCAL CACHE & BLOB UI
// ======================================================

@ini_set('memory_limit', '16000M');
@set_time_limit(0);

// Limpa buffers para não corromper o binário
while (ob_get_level()) ob_end_clean();

header("Access-Control-Allow-Origin: *");
header("X-Powered-By: Victor-Dev-Sync-Engine");

$indexJson = __DIR__ . '/index.json';
$logins = is_file($indexJson) ? json_decode(file_get_contents($indexJson), true) ?: [] : [];

// Identificação do caminho do arquivo
$path = ltrim($_SERVER['PATH_INFO'] ?? '', '/');
if (!$path) {
    $uri = explode('?', $_SERVER['REQUEST_URI'])[0];
    $path = ltrim(str_replace($_SERVER['SCRIPT_NAME'], '', $uri), '/');
}

if (!$path || $path === 'index.php') exit("ERRO: CAMINHO_INVALIDO");

$localPath = __DIR__ . '/' . $path;
$fileName = basename($path);

// ======================================================
// 📤 UPLOAD (MANTIDO)
// ======================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $inputData = file_get_contents('php://input');
    if (empty($inputData)) exit("ERRO: EMPTY");
    
    // Invalida cache local no novo upload
    if (file_exists($localPath)) @unlink($localPath);

    $chunks = str_split($inputData, 1024 * 512);
    $total = count($chunks);
    $success = 0;
    foreach ($chunks as $idx => $chunk) {
        $partName = "part_" . ($idx + 1) . ".txt";
        foreach ($logins as $cfg) {
            $c = @ftp_connect($cfg['host'], 21, 10);
            if ($c && @ftp_login($c, $cfg['user'], $cfg['pass'])) {
                ftp_pasv($c, true);
                @ftp_chdir($c, "htdocs") || @ftp_chdir($c, "public_html");
                if (!@ftp_chdir($c, "X")) { @ftp_mkdir($c, "X"); @ftp_chdir($c, "X"); }
                foreach (explode('/', $path) as $d) { if (!empty($d) && !@ftp_chdir($c, $d)) { if (@ftp_mkdir($c, $d)) @ftp_chdir($c, $d); } }
                $tmp = tmpfile(); fwrite($tmp, $chunk); rewind($tmp);
                if (@ftp_fput($c, $partName, $tmp, FTP_BINARY)) { $success++; @ftp_close($c); break; }
                @ftp_close($c);
            }
        }
    }
    exit($success === $total ? "OK" : "ERROR");
}

// ======================================================
// 📥 DOWNLOAD (GET) - LÓGICA LOCAL + BLOB
// ======================================================
if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    // 1. REQUISIÇÃO DE DADOS (STREAM BINÁRIO)
    if (isset($_GET['sync_internal']) || strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/octet-stream') !== false) {
        
        // Se NÃO existe localmente, baixa do FTP e monta
        if (!file_exists($localPath)) {
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
                        @ftp_close($conn); if ($foundPart) break;
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
                exit("ERRO: ARQUIVO_NAO_ENCONTRADO");
            }
        }

        // Entrega o arquivo do disco local
        header("Content-Type: application/octet-stream");
        header("Content-Disposition: attachment; filename=\"$fileName\"");
        header("Content-Length: " . filesize($localPath));
        readfile($localPath);
        exit;
    }

    // 2. INTERFACE VISUAL (BLOB UI)
    ?>
    <!DOCTYPE html>
    <html lang="pt-br">
    <head>
        <meta charset="UTF-8">
        <title>SYNC // <?php echo strtoupper($fileName); ?></title>
        <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700&display=swap" rel="stylesheet">
        <style>
            body { background: #000; color: #00f2ff; font-family: 'Orbitron', sans-serif; height: 100vh; margin: 0; display: flex; align-items: center; justify-content: center; overflow: hidden; }
            .ui-box { width: 350px; padding: 40px; border: 1px solid #00f2ff; box-shadow: 0 0 30px #00f2ff33; text-align: center; border-radius: 2px; position: relative; }
            .bar-bg { width: 100%; height: 2px; background: #111; margin: 25px 0; position: relative; overflow: hidden; }
            .bar-fill { width: 0%; height: 100%; background: #00f2ff; box-shadow: 0 0 15px #00f2ff; transition: width 0.1s; }
            .pct { font-size: 3rem; font-weight: 700; text-shadow: 0 0 20px #00f2ff; margin: 10px 0; }
            .info { font-size: 9px; letter-spacing: 2px; text-transform: uppercase; opacity: 0.6; }
            .filename { color: #fff; font-size: 11px; margin-top: 10px; word-break: break-all; }
        </style>
    </head>
    <body>
        <div class="ui-box">
            <div class="info" id="status">Sincronizando Sistema</div>
            <div class="pct" id="pct">0%</div>
            <div class="bar-bg"><div class="bar-fill" id="fill"></div></div>
            <div class="filename"><?php echo $fileName; ?></div>
        </div>

        <script>
            async function startSync() {
                const url = window.location.href + (window.location.search ? '&' : '?') + 'sync_internal=1';
                
                const response = await fetch(url, {
                    headers: { 'Accept': 'application/octet-stream' }
                });
                
                if (!response.ok) throw new Error();

                const reader = response.body.getReader();
                const total = +response.headers.get('Content-Length');
                let loaded = 0;
                let chunks = [];

                while(true) {
                    const {done, value} = await reader.read();
                    if (done) break;
                    chunks.push(value);
                    loaded += value.length;
                    
                    if (total) {
                        let p = Math.round((loaded / total) * 100);
                        document.getElementById('fill').style.width = p + '%';
                        document.getElementById('pct').innerText = p + '%';
                    }
                }

                document.getElementById('status').innerText = "Processando Blob...";
                const blob = new Blob(chunks);
                const blobUrl = URL.createObjectURL(blob);
                
                const a = document.createElement('a');
                a.href = blobUrl;
                a.download = "<?php echo $fileName; ?>";
                document.body.appendChild(a);
                a.click();
                
                document.getElementById('pct').innerText = "DONE";
                document.getElementById('status').innerText = "Sincronia Completa";
                
                setTimeout(() => {
                    URL.revokeObjectURL(blobUrl);
                }, 100);
            }

            startSync().catch(() => { 
                document.getElementById('pct').innerText = "ERR"; 
                document.getElementById('status').innerText = "Falha na Conexão";
                document.getElementById('status').style.color = "red";
            });
        </script>
    </body>
    </html>
    <?php
}
