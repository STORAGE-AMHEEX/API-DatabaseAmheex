<?php
// ===============================================
// Ã°ÂÂÂ SMART FRAGMENT STORAGE - VICTOR DEV
// ===============================================

@ini_set('memory_limit', '16000M');
@set_time_limit(0);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/octet-stream");

$indexJson = __DIR__ . '/index.json';
$logins = is_file($indexJson) ? json_decode(file_get_contents($indexJson), true) ?: [] : [];

// 1. CAPTURA DO PATH DINÃÂMICO (O que estiver na frente do script na URL)
// Exemplo: se chamar /script.php/meu/arquivo.bin -> $path serÃÂ¡ "meu/arquivo.bin"
$path = ltrim($_SERVER['PATH_INFO'] ?? '', '/');

// Se nÃÂ£o houver PATH_INFO, tenta pegar pela REQUEST_URI (removendo o nome do script)
if (!$path) {
    $scriptName = $_SERVER['SCRIPT_NAME'];
    $path = ltrim(str_replace($scriptName, '', $_SERVER['REQUEST_URI']), '/');
    $path = explode('?', $path)[0]; // Remove query strings
}

if (!$path) exit("ERRO: DEFINA_UM_PATH_NA_URL");

$max_chunk_size = 1024 * 1024; // 1024 caracteres por arquivo .txt

// ======================================================
// Ã°ÂÂÂ¤ UPLOAD: RECEBE BINÃÂRIO OU TEXTO (POST)
// ======================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $inputData = file_get_contents('php://input');
    if (empty($inputData)) exit("ERRO: SEM_CONTEUDO");

    // Fatiamento baseado em offset (cada 1024 bytes vira uma parte)
    $chunks = str_split($inputData, $max_chunk_size);
    $total = count($chunks);
    $successCount = 0;

    foreach ($chunks as $idx => $chunk) {
        $partName = "part_" . ($idx + 1) . ".txt";
        $uploaded = false;

        foreach ($logins as $cfg) {
            $c = @ftp_connect($cfg['host'], 21, 10);
            if ($c && @ftp_login($c, $cfg['user'], $cfg['pass'])) {
                ftp_pasv($c, true);
                
                // NavegaÃÂ§ÃÂ£o e criaÃÂ§ÃÂ£o de pastas no FTP
                @ftp_chdir($c, "htdocs") || @ftp_chdir($c, "public_html");
                if (!@ftp_chdir($c, "X")) { @ftp_mkdir($c, "X"); @ftp_chdir($c, "X"); }

                $dirs = explode('/', $path);
                foreach ($dirs as $d) {
                    if (empty($d)) continue;
                    if (!@ftp_chdir($c, $d)) {
                        if (@ftp_mkdir($c, $d)) @ftp_chdir($c, $d);
                    }
                }

                // Salva a parte como .txt
                $tmp = tmpfile();
                fwrite($tmp, $chunk);
                rewind($tmp);
                if (@ftp_fput($c, $partName, $tmp, FTP_BINARY)) $uploaded = true;
                fclose($tmp);
                @ftp_close($c);
                if ($uploaded) break;
            }
        }
        if ($uploaded) $successCount++;
    }
    exit($successCount === $total ? "OK_FULL" : "PARTIAL_$successCount");
}

// ======================================================
// Ã°ÂÂÂ¥ DOWNLOAD: RECONSTRÃÂI O BINÃÂRIO ORIGINAL (GET)
// ======================================================
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
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
                
                // Tenta localizar a parte nos diretÃÂ³rios padrÃÂ£o
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
                fclose($tmp);
                @ftp_close($c);
                if ($foundPart) break;
            }
        }

        if ($foundPart) {
            $part++;
        } else {
            $foundAny = false; // NÃÂ£o hÃÂ¡ mais partes
        }
    }

    if ($fullContent !== "") {
        echo $fullContent;
    } else {
        http_response_code(404);
        echo "ERRO_ARQUIVO_INEXISTENTE";
    }
}
