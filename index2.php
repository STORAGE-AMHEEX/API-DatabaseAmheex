<?php
// ======================================================
// 🚀 ULTRA SYNC SYSTEM V7.2 - REDIRECT & PATH FIX
// ======================================================

@ini_set('memory_limit', '16000M');
@set_time_limit(0);

while (ob_get_level()) ob_end_clean();

header("Access-Control-Allow-Origin: *");
header("X-Powered-By: Cyber-Sync-Core");

// --- CONFIGURAÇÃO DA FONTE DE DADOS ---
$jsonUrl = 'https://api-amheex.onrender.com/index.json';

$context = stream_context_create([
    "http" => ["header" => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) CyberSync/7.2\r\n"]
]);

$jsonRaw = @file_get_contents($jsonUrl, false, $context);
$logins = $jsonRaw ? json_decode($jsonRaw, true) : [];

// Validação do Path
$path = ltrim($_SERVER['PATH_INFO'] ?? '', '/');
if (!$path) {
    $uri = explode('?', $_SERVER['REQUEST_URI'])[0];
    $path = ltrim(str_replace($_SERVER['SCRIPT_NAME'], '', $uri), '/');
}

if (!$path || $path === 'index.php') exit("CORE_ERROR: INVALID_PATH");

$localPath = __DIR__ . '/X/' . $path;
$fileName = basename($path);

// ======================================================
// 📥 DOWNLOAD (GET)
// ======================================================
if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    if (isset($_GET['sync_internal'])) {
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
        header("Content-Type: application/octet-stream");
        header("Content-Length: " . filesize($localPath));
        readfile($localPath);
        exit;
    }
    ?>
    <!DOCTYPE html>
    <html lang="pt-br">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>DOWNLOAD CENTER // <?php echo strtoupper($fileName); ?></title>
        <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&display=swap" rel="stylesheet">
        <style>
            :root { --main-blue: #0044ff; --neon-blue: #00d2ff; --dark-bg: #020205; }
            * { box-sizing: border-box; }
            body { background: var(--dark-bg); color: var(--neon-blue); font-family: 'Orbitron', sans-serif; height: 100vh; margin: 0; display: flex; align-items: center; justify-content: center; overflow: hidden; perspective: 1200px; }
            body::before { content: ""; position: absolute; width: 300%; height: 300%; top: -100%; left: -100%; background-image: linear-gradient(var(--main-blue) 1px, transparent 1px), linear-gradient(90deg, var(--main-blue) 1px, transparent 1px); background-size: 40px 40px; transform: rotateX(65deg) translateY(0); animation: gridMove 8s linear infinite; opacity: 0.1; z-index: -1; }
            @keyframes gridMove { from { transform: rotateX(65deg) translateY(0); } to { transform: rotateX(65deg) translateY(40px); } }
            .container { position: relative; width: 90%; max-width: 500px; transform-style: preserve-3d; animation: float 5s ease-in-out infinite; }
            @keyframes float { 0%, 100% { transform: translateY(0px) rotateY(-5deg); } 50% { transform: translateY(-15px) rotateY(5deg); } }
            .glass-card { background: rgba(0, 15, 40, 0.85); backdrop-filter: blur(20px); border: 1px solid var(--main-blue); padding: 40px 20px; border-radius: 4px; box-shadow: 0 0 40px rgba(0, 68, 255, 0.3); text-align: center; position: relative; }
            .pct-text { font-size: clamp(3rem, 15vw, 5.5rem); font-weight: 900; text-shadow: 0 0 25px var(--main-blue); margin: 10px 0; }
            .progress-frame { width: 100%; height: 10px; background: rgba(0,0,0,0.6); border: 1px solid var(--main-blue); margin: 25px 0; overflow: hidden; }
            .progress-bar { width: 0%; height: 100%; background: linear-gradient(90deg, var(--main-blue), var(--neon-blue)); transition: width 0.3s ease; }
            .label { font-size: 11px; letter-spacing: 4px; text-transform: uppercase; color: #fff; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="glass-card">
                <div class="label" id="status">Syncing from Remote JSON</div>
                <div class="pct-text" id="pct">00%</div>
                <div class="progress-frame"><div class="progress-bar" id="bar"></div></div>
                <div class="file-info"><?php echo $fileName; ?></div>
            </div>
        </div>
        <script>
            async function startCoreSync() {
                const url = window.location.href + (window.location.search ? '&' : '?') + 'sync_internal=1';
                try {
                    const response = await fetch(url);
                    if (!response.ok) throw new Error();
                    const reader = response.body.getReader();
                    const total = +response.headers.get('Content-Length');
                    let loaded = 0; let chunks = [];
                    while(true) {
                        const {done, value} = await reader.read();
                        if (done) break;
                        chunks.push(value);
                        loaded += value.length;
                        if (total) {
                            let p = Math.round((loaded / total) * 100);
                            document.getElementById('bar').style.width = p + '%';
                            document.getElementById('pct').innerText = p + '%';
                        }
                    }
                    const blob = new Blob(chunks);
                    const bUrl = URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = bUrl; a.download = "<?php echo $fileName; ?>";
                    document.body.appendChild(a);
                    a.click();
                    a.remove();

                    // ✅ REDIRECIONA PARA A URL DO ARQUIVO LOCAL APÓS O DOWNLOAD
                    setTimeout(() => { 
                        const baseUrl = window.location.origin;
                        window.location.href = baseUrl + '/X/<?php echo $path; ?>'; 
                    }, 2000);

                } catch (e) {
                    document.getElementById('pct').innerText = "ERR";
                    document.getElementById('status').innerText = "Erro na Nuvem";
                }
            }
            window.onload = startCoreSync;
        </script>
    </body>
    </html>
    <?php
    exit;
}

// ======================================================
// 📤 UPLOAD (POST)
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
    exit($success === $total ? "OK" : "PARTIAL_ERROR");
}
