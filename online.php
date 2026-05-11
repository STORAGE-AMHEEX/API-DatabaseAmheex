<?php
// ======================================================
// 🚀 SMART SYNC SYSTEM V4 - AUTOMATIC UI - VICTOR DEV
// ======================================================

@ini_set('memory_limit', '16000M');
@set_time_limit(0);

while (ob_get_level()) ob_end_clean();

header("Access-Control-Allow-Origin: *");
header("X-Powered-By: Victor-Dev-High-Tech");

$indexJson = __DIR__ . '/index.json';
$logins = is_file($indexJson) ? json_decode(file_get_contents($indexJson), true) ?: [] : [];

$path = ltrim($_SERVER['PATH_INFO'] ?? '', '/');
if (!$path) {
    $path = ltrim(str_replace($_SERVER['SCRIPT_NAME'], '', explode('?', $_SERVER['REQUEST_URI'])[0]), '/');
}

if (!$path) exit("ERRO: PATH_NOT_DEFINED");

// ======================================================
// 📤 UPLOAD (MANTIDO)
// ======================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $inputData = file_get_contents('php://input');
    if (empty($inputData)) exit("ERRO: EMPTY");
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
// 📥 DOWNLOAD AUTOMÁTICO (GET)
// ======================================================
if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    // DETECÇÃO DE REQUISIÇÃO AJAX/FETCH (Para entregar o binário sem chave GET)
    $isFetch = (isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/octet-stream') !== false || isset($_GET['sync_internal']));

    if ($isFetch) {
        $tempStorage = fopen('php://temp', 'r+b');
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
                    foreach ($targets as $t) { if (@ftp_fget($conn, $tempStorage, $t, FTP_BINARY)) { $foundPart = true; break; } }
                    @ftp_close($conn); if ($foundPart) break;
                }
            }
            if ($foundPart) $part++; else $foundAny = false;
        }
        $size = ftell($tempStorage);
        if ($size === 0) exit;
        rewind($tempStorage);
        header("Content-Type: application/octet-stream");
        header("Content-Length: $size");
        fpassthru($tempStorage);
        fclose($tempStorage);
        exit;
    }

    // INTERFACE DE CARREGAMENTO (Aparece primeiro no navegador)
    ?>
    <!DOCTYPE html>
    <html lang="pt-br">
    <head>
        <meta charset="UTF-8">
        <title>LOADING // <?php echo strtoupper(basename($path)); ?></title>
        <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700&display=swap" rel="stylesheet">
        <style>
            body { background: #000; color: #00f2ff; font-family: 'Orbitron', sans-serif; height: 100vh; margin: 0; display: flex; align-items: center; justify-content: center; }
            .ui-box { width: 350px; padding: 40px; border: 1px solid #00f2ff; box-shadow: 0 0 30px #00f2ff33; text-align: center; border-radius: 2px; }
            .bar-bg { width: 100%; height: 2px; background: #111; margin: 25px 0; position: relative; }
            .bar-fill { width: 0%; height: 100%; background: #00f2ff; box-shadow: 0 0 15px #00f2ff; transition: width 0.1s; }
            .pct { font-size: 3rem; font-weight: 700; text-shadow: 0 0 20px #00f2ff; }
            .info { font-size: 9px; letter-spacing: 2px; text-transform: uppercase; opacity: 0.6; }
        </style>
    </head>
    <body>
        <div class="ui-box">
            <div class="info">Sincronizando Sistema</div>
            <div class="pct" id="pct">0%</div>
            <div class="bar-bg"><div class="bar-fill" id="fill"></div></div>
            <div class="info" style="color:#fff;"><?php echo basename($path); ?></div>
        </div>

        <script>
            async function startSync() {
                // Faz a requisição para o próprio arquivo enviando um Header identificador
                const response = await fetch(window.location.href, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/octet-stream' }
                });
                
                const reader = response.body.getReader();
                const total = +response.headers.get('Content-Length');
                let loaded = 0;
                let chunks = [];

                while(true) {
                    const {done, value} = await reader.read();
                    if (done) break;
                    chunks.push(value);
                    loaded += value.length;
                    let p = Math.round((loaded / total) * 100);
                    document.getElementById('fill').style.width = p + '%';
                    document.getElementById('pct').innerText = p + '%';
                }

                const blob = new Blob(chunks);
                const url = URL.createObjectURL(blob);
                const ext = "<?php echo strtolower(pathinfo($path, PATHINFO_EXTENSION)); ?>";
                
                // Se for mídia, abre. Se não, baixa.
                if(['mp4','mp3','png','jpg','pdf','html','txt','php'].includes(ext)) {
                    window.location.replace(url);
                } else {
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = "<?php echo basename($path); ?>";
                    a.click();
                }
            }
            startSync().catch(() => { document.getElementById('pct').innerText = "FAIL"; });
        </script>
    </body>
    </html>
    <?php
}
