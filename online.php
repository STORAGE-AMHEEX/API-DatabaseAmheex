<?php
// ======================================================
// 🚀 ULTRA SYNC SYSTEM V6 - FUTURISTIC 3D & AUTO-REFRESH
// ======================================================

@ini_set('memory_limit', '16000M');
@set_time_limit(0);

while (ob_get_level()) ob_end_clean();

header("Access-Control-Allow-Origin: *");
header("X-Powered-By: Cyber-Sync-Core");

$indexJson = __DIR__ . '/index.json';
$logins = is_file($indexJson) ? json_decode(file_get_contents($indexJson), true) ?: [] : [];

$path = ltrim($_SERVER['PATH_INFO'] ?? '', '/');
if (!$path) {
    $uri = explode('?', $_SERVER['REQUEST_URI'])[0];
    $path = ltrim(str_replace($_SERVER['SCRIPT_NAME'], '', $uri), '/');
}

if (!$path || $path === 'index.php') exit("CORE_ERROR: INVALID_PATH");

$localPath = __DIR__ . '/' . $path;
$fileName = basename($path);

// ======================================================
// 📥 DOWNLOAD (GET) - LÓGICA LOCAL + INTERFACE 3D
// ======================================================
if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    // 1. ENTREGA DO BINÁRIO (Para o Fetch do JS)
    if (isset($_GET['sync_internal'])) {
        if (!file_exists($localPath)) {
            $fullContent = "";
            $part = 1; $foundAny = true;
            while ($foundAny) {
                $partName = "part_$part.txt"; $foundPart = false;
                foreach ($logins as $cfg) {
                    $conn = @ftp_connect($cfg['host'], 21, 10);
                    if ($conn && @ftp_login($conn, $cfg['user'], $cfg['pass'])) {
                        ftp_pasv($conn, true);
                        $targets = ["htdocs/X/$path/$partName", "public_html/X/$path/$partName", "X/$path/$partName"];
                        foreach ($targets as $t) {
                            $tmp = fopen('php://temp', 'r+b');
                            if (@ftp_fget($conn, $tmp, $t, FTP_BINARY)) {
                                rewind($tmp); $fullContent .= stream_get_contents($tmp);
                                $foundPart = true; fclose($tmp); break;
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
            } else { http_response_code(404); exit; }
        }
        header("Content-Type: application/octet-stream");
        header("Content-Length: " . filesize($localPath));
        readfile($localPath);
        exit;
    }

    // 2. INTERFACE VISUAL ULTRA-FUTURISTA
    ?>
    <!DOCTYPE html>
    <html lang="pt-br">
    <head>
        <meta charset="UTF-8">
        <title>CORE SYNC // <?php echo strtoupper($fileName); ?></title>
        <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;900&display=swap" rel="stylesheet">
        <style>
            :root { --main-blue: #0044ff; --neon-blue: #00d2ff; --dark-bg: #020205; }
            body { background: var(--dark-bg); color: var(--neon-blue); font-family: 'Orbitron', sans-serif; height: 100vh; margin: 0; display: flex; align-items: center; justify-content: center; overflow: hidden; perspective: 1000px; }
            
            /* Background 3D Grid */
            body::before { content: ""; position: absolute; width: 200%; height: 200%; top: -50%; left: -50%; background-image: linear-gradient(var(--main-blue) 1px, transparent 1px), linear-gradient(90deg, var(--main-blue) 1px, transparent 1px); background-size: 50px 50px; transform: rotateX(60deg) translateY(0); animation: gridMove 10s linear infinite; opacity: 0.15; z-index: -1; }
            @keyframes gridMove { from { transform: rotateX(60deg) translateY(0); } to { transform: rotateX(60deg) translateY(50px); } }

            .container { position: relative; width: 400px; transform-style: preserve-3d; animation: float 4s ease-in-out infinite; }
            @keyframes float { 0%, 100% { transform: translateY(0px) rotateY(0deg); } 50% { transform: translateY(-20px) rotateY(5deg); } }

            .glass-card { background: rgba(0, 20, 50, 0.6); backdrop-filter: blur(15px); border: 2px solid var(--main-blue); padding: 50px; border-radius: 5px; box-shadow: 0 0 50px rgba(0, 68, 255, 0.4); text-align: center; border-left: 10px solid var(--neon-blue); }
            
            .pct-text { font-size: 5rem; font-weight: 900; letter-spacing: -5px; text-shadow: 0 0 30px var(--main-blue); margin-bottom: 10px; }
            
            .progress-frame { width: 100%; height: 8px; background: rgba(0,0,0,0.5); border: 1px solid var(--main-blue); border-radius: 10px; overflow: hidden; margin: 20px 0; }
            .progress-bar { width: 0%; height: 100%; background: linear-gradient(90deg, var(--main-blue), var(--neon-blue)); box-shadow: 0 0 15px var(--neon-blue); transition: width 0.2s cubic-bezier(0.1, 0.7, 1.0, 0.1); }
            
            .label { font-size: 10px; letter-spacing: 5px; text-transform: uppercase; color: #fff; opacity: 0.8; }
            .file-info { font-size: 11px; margin-top: 15px; color: var(--neon-blue); opacity: 0.5; font-weight: 400; }

            /* Scanner Effect */
            .scanner { position: absolute; top: 0; left: 0; width: 100%; height: 2px; background: var(--neon-blue); box-shadow: 0 0 20px var(--neon-blue); animation: scan 3s linear infinite; }
            @keyframes scan { 0% { top: 0%; opacity: 0; } 50% { opacity: 1; } 100% { top: 100%; opacity: 0; } }
        </style>
    </head>
    <body>

        <div class="container">
            <div class="scanner"></div>
            <div class="glass-card">
                <div class="label" id="status">Iniciando Protocolo</div>
                <div class="pct-text" id="pct">0%</div>
                <div class="progress-frame">
                    <div class="progress-bar" id="bar"></div>
                </div>
                <div class="file-info"><?php echo $fileName; ?></div>
            </div>
        </div>

        <!-- Som de Processamento Futurista -->
        <audio id="beep" src="https://www.soundjay.com/buttons/sounds/button-20.mp3"></audio>
        <audio id="success" src="https://www.soundjay.com/buttons/sounds/button-09.mp3"></audio>

        <script>
            async function startCoreSync() {
                const beep = document.getElementById('beep');
                const successSnd = document.getElementById('success');
                const url = window.location.href + (window.location.search ? '&' : '?') + 'sync_internal=1';
                
                try {
                    const response = await fetch(url);
                    if (!response.ok) throw new Error();

                    const reader = response.body.getReader();
                    const total = +response.headers.get('Content-Length');
                    let loaded = 0;
                    let chunks = [];

                    document.getElementById('status').innerText = "Sincronizando Dados";
                    beep.play();

                    while(true) {
                        const {done, value} = await reader.read();
                        if (done) break;
                        chunks.push(value);
                        loaded += value.length;
                        
                        if (total) {
                            let p = Math.round((loaded / total) * 100);
                            document.getElementById('bar').style.width = p + '%';
                            document.getElementById('pct').innerText = p + '%';
                            if(p % 10 === 0) beep.cloneNode(true).play();
                        }
                    }

                    document.getElementById('status').innerText = "Gerando Blob...";
                    const blob = new Blob(chunks);
                    const bUrl = URL.createObjectURL(blob);
                    
                    const a = document.createElement('a');
                    a.href = bUrl;
                    a.download = "<?php echo $fileName; ?>";
                    document.body.appendChild(a);
                    a.click();
                    
                    document.getElementById('status').innerText = "CONCLUÍDO";
                    successSnd.play();

                    // Reinicia a página após 2 segundos do download concluído
                    setTimeout(() => {
                        window.location.reload();
                    }, 2500);

                } catch (e) {
                    document.getElementById('pct').innerText = "ERR";
                    document.getElementById('status').innerText = "Falha no Núcleo";
                }
            }

            // Inicia após interação ou carregamento
            window.onload = startCoreSync;
        </script>
    </body>
    </html>
    <?php
    exit;
}

// ======================================================
// 📤 UPLOAD (MANTIDO)
// ======================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $inputData = file_get_contents('php://input');
    if (empty($inputData)) exit("ERROR_EMPTY");
    if (file_exists($localPath)) @unlink($localPath);
    $chunks = str_split($inputData, 1024 * 512);
    $total = count($chunks); $success = 0;
    foreach ($chunks as $idx => $chunk) {
        $pName = "part_" . ($idx + 1) . ".txt";
        foreach ($logins as $cfg) {
            $c = @ftp_connect($cfg['host'], 21, 10);
            if ($c && @ftp_login($c, $cfg['user'], $cfg['pass'])) {
                ftp_pasv($c, true);
                @ftp_chdir($c, "htdocs") || @ftp_chdir($c, "public_html");
                if (!@ftp_chdir($c, "X")) { @ftp_mkdir($c, "X"); @ftp_chdir($c, "X"); }
                foreach (explode('/', $path) as $d) { if (!empty($d) && !@ftp_chdir($c, $d)) { if (@ftp_mkdir($c, $d)) @ftp_chdir($c, $d); } }
                $tmp = tmpfile(); fwrite($tmp, $chunk); rewind($tmp);
                if (@ftp_fput($c, $pName, $tmp, FTP_BINARY)) { $success++; @ftp_close($c); break; }
                @ftp_close($c);
            }
        }
    }
    exit($success === $total ? "OK" : "ERROR");
}
