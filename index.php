<?php
// ======================================================
// 🚀 SMART FRAGMENT STORAGE - LOCAL SYNC & DOWNLOAD
// ======================================================

@ini_set('memory_limit', '16000M');
@set_time_limit(0);

while (ob_get_level()) ob_end_clean();

header("Access-Control-Allow-Origin: *");

$indexJson = __DIR__ . '/index.json';
$logins = is_file($indexJson) ? json_decode(file_get_contents($indexJson), true) ?: [] : [];

// Identifica o PATH da URL (ex: caminho/arquivo.txt)
$path = ltrim($_SERVER['PATH_INFO'] ?? '', '/');
if (!$path) {
    $scriptName = $_SERVER['SCRIPT_NAME'];
    $path = ltrim(str_replace($scriptName, '', $_SERVER['REQUEST_URI']), '/');
    $path = explode('?', $path)[0];
}

if (!$path) exit("ERRO: DEFINA_UM_PATH_NA_URL");

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$baseUrl = $protocol . $_SERVER['HTTP_HOST'] . explode('?', $_SERVER['REQUEST_URI'])[0];

// ======================================================
// 📤 UPLOAD: RETORNA APENAS A URL DO PATH
// ======================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $inputData = file_get_contents('php://input');
    if (empty($inputData)) exit("ERRO: SEM_CONTEUDO");
    
    $chunks = str_split($inputData, 1024);
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
        exit($baseUrl); 
    } else {
        exit("ERRO: FALHA_FRAGMENTO_PARTIAL_$successCount");
    }
}

// ======================================================
// 📥 DOWNLOAD + SALVAMENTO LOCAL (AUTO-DOWNLOAD NO PHP)
// ======================================================
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $fullContent = "";
    $part = 1;
    $foundAny = true;

    // 1. Reconstrói a partir dos fragmentos FTP
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
        exit("ERRO_ARQUIVO_INEXISTENTE");
    }

    // --- NOVA LÓGICA: SALVAR NO SERVIDOR LOCAL ---
    $localPath = __DIR__ . '/' . $path;
    $localDir = dirname($localPath);

    // Cria as pastas locais se não existirem
    if (!is_dir($localDir)) {
        @mkdir($localDir, 0777, true);
    }

    // Salva o arquivo no disco do servidor PHP
    @file_put_contents($localPath, $fullContent);

    // --- ENTREGA O DOWNLOAD PARA O USUÁRIO ---
    $fileName = basename($path);
    header("Content-Type: application/octet-stream");
    header("Content-Length: " . strlen($fullContent));
    header("Content-Disposition: attachment; filename=\"$fileName\"");
    echo $fullContent;
    exit;
}
