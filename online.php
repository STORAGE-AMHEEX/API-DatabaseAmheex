<?php
// ======================================================
// 🚀 SMART FRAGMENT STORAGE - OPTIMIZED LOCAL SYNC
// ======================================================

@ini_set('memory_limit', '16000M');
@set_time_limit(0);

while (ob_get_level()) ob_end_clean();

header("Access-Control-Allow-Origin: *");

$indexJson = __DIR__ . '/index.json';
$logins = is_file($indexJson) ? json_decode(file_get_contents($indexJson), true) ?: [] : [];

// Identifica o PATH da URL de forma limpa
$path = ltrim($_SERVER['PATH_INFO'] ?? '', '/');
if (!$path) {
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $scriptName = $_SERVER['SCRIPT_NAME'];
    $path = ltrim(str_replace($scriptName, '', $path), '/');
}

if (!$path || $path == 'index.php') exit("ERRO: DEFINA_UM_PATH_VALIDO");

$localPath = __DIR__ . '/' . $path;
$fileName = basename($path);

// ======================================================
// 📥 DOWNLOAD (LÓGICA PRIORITÁRIA)
// ======================================================
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    
    $fullContent = "";

    // 1. CHECA SE JÁ EXISTE LOCALMENTE (Para evitar FTP lento)
    if (file_exists($localPath)) {
        $fullContent = file_get_contents($localPath);
    } else {
        // 2. SE NÃO EXISTE, BUSCA NO FTP
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

        // SALVA LOCALMENTE PARA A PRÓXIMA VEZ
        if ($fullContent !== "") {
            $localDir = dirname($localPath);
            if (!is_dir($localDir)) @mkdir($localDir, 0777, true);
            @file_put_contents($localPath, $fullContent);
        }
    }

    if ($fullContent === "") {
        http_response_code(404);
        exit("ERRO_ARQUIVO_INEXISTENTE");
    }

    // --- ENTREGA O DOWNLOAD ---
    header("Content-Type: application/octet-stream");
    header("Content-Length: " . strlen($fullContent));
    header("Content-Disposition: attachment; filename=\"$fileName\"");
    echo $fullContent;
    exit;
}

// ======================================================
// 📤 UPLOAD (PERMANECE IGUAL)
// ======================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ... (seu código de upload aqui)
    // Dica: Se o arquivo local existe, apague-o no POST para atualizar o cache
    if(file_exists($localPath)) @unlink($localPath);
    
    // [Seu loop de FTP...]
    exit("URL_DO_ARQUIVO");
}
