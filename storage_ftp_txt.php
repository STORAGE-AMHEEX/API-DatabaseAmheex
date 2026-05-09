<?php
// ===============================================
// 🚀 STORAGE COMPATÍVEL COM BYETHOST / INFINITYFREE
// ===============================================

@ini_set('memory_limit', '16000M');
@set_time_limit(0);

header("Access-Control-Allow-Origin: *");
header("Content-Type: text/plain; charset=utf-8");

$indexJson = __DIR__ . '/index.json';
$logins = is_file($indexJson) ? json_decode(file_get_contents($indexJson), true) ?: [] : [];

$path = $_GET['path'] ?? '';
$txt  = $_GET['txt'] ?? null;

if (!$path) exit("Aguardando 'path'...");

$relPath = ltrim(str_replace('\\', '/', $path), '/');

// ======================================================
// 📤 UPLOAD: COMPATÍVEL COM HTDOCS
// ======================================================
if ($txt !== null) {
    $subido = false;
    $tmpFile = tempnam(sys_get_temp_dir(), 'ftp_');
    file_put_contents($tmpFile, $txt);

    foreach ($logins as $cfg) {
        $c = @ftp_connect($cfg['host'], 21, 20);
        if ($c && @ftp_login($c, $cfg['user'], $cfg['pass'])) {
            ftp_pasv($c, true);
            
            // 1. Tenta entrar na htdocs (comum em servidores gratuitos)
            if (!@ftp_chdir($c, "htdocs")) {
                @ftp_chdir($c, "public_html"); // fallback
            }

            // 2. Garante a pasta X
            if (!@ftp_chdir($c, "X")) {
                @ftp_mkdir($c, "X");
                @ftp_chdir($c, "X");
            }

            // 3. Cria subpastas do path
            $dirs = explode('/', dirname($relPath));
            foreach ($dirs as $d) {
                if ($d === '.' || empty($d)) continue;
                if (!@ftp_chdir($c, $d)) {
                    if (@ftp_mkdir($c, $d)) @ftp_chdir($c, $d);
                }
            }

            $finalName = basename($relPath);
            @ftp_delete($c, $finalName);

            if (@ftp_put($c, $finalName, $tmpFile, FTP_BINARY)) {
                $subido = true;
            }
            
            @ftp_close($c);
            if ($subido) break;
        }
    }
    @unlink($tmpFile);
    exit($subido ? "OK" : "ERRO_GRAVACAO");
}

// ======================================================
// 📥 LEITURA: BUSCA EM HTDOCS/X/...
// ======================================================
$found = false;

foreach ($logins as $cfg) {
    $c = @ftp_connect($cfg['host'], 21, 15);
    if ($c && @ftp_login($c, $cfg['user'], $cfg['pass'])) {
        ftp_pasv($c, true);
        
        $tmpRead = fopen('php://temp', 'r+b');
        
        // Possíveis caminhos no ByetHost/InfinityFree
        $targets = [
            "htdocs/X/$relPath",
            "X/$relPath",
            "public_html/X/$relPath"
        ];

        foreach ($targets as $target) {
            if (@ftp_fget($c, $tmpRead, $target, FTP_BINARY)) {
                rewind($tmpRead);
                echo stream_get_contents($tmpRead);
                $found = true;
                break;
            }
        }
        
        fclose($tmpRead);
        @ftp_close($c);
        if ($found) break;
    }
}

if (!$found) {
    http_response_code(404);
    echo "NAO_ENCONTRADO";
}
