<?php
// ======================================================
// 🚀 SMART FRAGMENT STORAGE - V2 (LOCAL CACHE & SYNC)
// ======================================================

@ini_set('memory_limit', '16000M');
@set_time_limit(0);

// Limpa buffers para permitir download de arquivos grandes
while (ob_get_level()) ob_end_clean();

header("Access-Control-Allow-Origin: *");

$indexJson = __DIR__ . '/index.json';
$logins = is_file($indexJson) ? json_decode(file_get_contents($indexJson), true) ?: [] : [];

// Identificação precisa do PATH
$path = ltrim($_SERVER['PATH_INFO'] ?? '', '/');
if (!$path) {
    $uri = explode('?', $_SERVER['REQUEST_URI'])[0];
    $path = ltrim(str_replace($_SERVER['SCRIPT_NAME'], '', $uri), '/');
}

if (!$path || $path === 'index.php') exit("ERRO: CAMINHO_INVALIDO");

$localPath = __DIR__ . '/' . $path;
$fileName = basename($path);

// ======================================================
// 📤 UPLOAD (POST)
// ======================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $inputData = file_get_contents('php://input');
    if (empty($inputData)) exit("ERRO: SEM_CONTEUDO");

    // 🔥 Limpa cache local antes de novo upload
    if (file_exists($localPath)) @unlink($localPath);
    
    $chunks = str_split($inputData, 1024 * 1024); // Chunks de 1MB para eficiência
    $total = count($chunks);
    $successCount = 0;

    foreach ($chunks as $idx => $chunk) {
        $partName = "part_" . ($idx + 1) . ".txt";
        $uploaded = false;
        foreach ($logins as $cfg) {
            $c = @ftp_connect($cfg['host'], 21, 10);
            if ($c && @ftp_login($c, $cfg['user'], $cfg['pass'])) {
                ftp_pasv($c, true);
                @ftp_chdir($c, "htdocs") || @ftp_chdir($c, "public_html");
                if (!@ftp_chdir($c, "X")) { @ftp_mkdir($c, "X"); @ftp_chdir($c, "X"); }
                
                $dirs = explode('/', $path);
                foreach ($dirs as $d) { 
                    if (!empty($d) && !@ftp_chdir($c, $d)) { 
                        if (@ftp_mkdir($c, $d)) @ftp_chdir($c, $d); 
                    } 
                }
                
                $tmp = tmpfile(); fwrite($tmp, $chunk); rewind($tmp);
                if (@ftp_fput($c, $partName, $tmp, FTP_BINARY)) $uploaded = true;
                fclose($tmp); @ftp_close($c); if ($uploaded) break;
            }
        }
        if ($uploaded) $successCount++;
    }

    if ($successCount === $total) {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
        exit($protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']); 
    }
    exit("ERRO: FALHA_PARCIAL");
}

// ======================================================
// 📥 DOWNLOAD (GET) - LÓGICA DE SINCRONIA
// ======================================================
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    
    // 1. Se já existe localmente, apenas entrega
    if (file_exists($localPath)) {
        header("Content-Type: application/octet-stream");
        header("Content-Disposition: attachment; filename=\"$fileName\"");
        header("Content-Length: " . filesize($localPath));
        readfile($localPath);
        exit;
    }

    // 2. Se não existe, reconstrói do FTP
    $fullContent = "";
    $part = 1;
    $foundAny = true;

    while ($foundAny) {
        $partName = "part_$part.txt";
        $foundPart = false;
        foreach ($logins as $cfg) {
            $c = @ftp_connect($cfg['host'], 21, 10);
            if ($c && @ftp_login($c, $cfg['user'], $cfg['pass'])) {
                ftp_pasv($c, true);
                $targets = ["htdocs/X/$path/$partName", "public_html/X/$path/$partName", "X/$path/$partName"];
                $tmp = fopen('php://temp', 'r+b');
                foreach ($targets as $t) {
                    if (@ftp_fget($c, $tmp, $t, FTP_BINARY)) {
                        rewind($tmp);
                        $fullContent .= stream_get_contents($tmp);
                        $foundPart = true;
                        break;
                    }
                }
                fclose($tmp); @ftp_close($c); if ($foundPart) break;
            }
        }
        if ($foundPart) $part++; else $foundAny = false;
    }

    if ($fullContent === "") {
        http_response_code(404);
        exit("ERRO: ARQUIVO_NAO_ENCONTRADO");
    }

    // 3. Salva localmente para o próximo acesso ser instantâneo
    $localDir = dirname($localPath);
    if (!is_dir($localDir)) @mkdir($localDir, 0777, true);
    @file_put_contents($localPath, $fullContent);

    // 4. Entrega o download
    header("Content-Type: application/octet-stream");
    header("Content-Length: " . strlen($fullContent));
    header("Content-Disposition: attachment; filename=\"$fileName\"");
    echo $fullContent;
    exit;
}
