<?php
/**
 * 🚀 AMHEEX SMART STORAGE - V3 (INTEGRATED PROTECTION & CACHE)
 */

// 1. Configurações de Performance
@ini_set('memory_limit', '16000M');
@set_time_limit(0);
ignore_user_abort(true); // Garante que o download para o servidor termine mesmo se o usuário fechar a aba

// Limpa buffers para entrega limpa de arquivos
while (ob_get_level()) ob_end_clean();

header("Access-Control-Allow-Origin: *");

// 2. Definição de Caminhos
$indexJson = __DIR__ . '/index.json';
$logins = is_file($indexJson) ? json_decode(file_get_contents($indexJson), true) ?: [] : [];

$path = ltrim($_SERVER['PATH_INFO'] ?? '', '/');
if (!$path) {
    $uri = explode('?', $_SERVER['REQUEST_URI'])[0];
    $path = ltrim(str_replace($_SERVER['SCRIPT_NAME'], '', $uri), '/');
}

// Bloqueia acesso se tentar acessar arquivos de sistema
if (!$path || preg_match('/\.(json|php|htaccess|env)$/i', $path)) {
    http_response_code(403);
    exit("ERRO: ACESSO_NEGADO_OU_CAMINHO_INVALIDO");
}

$localPath = __DIR__ . '/' . $path;
$fileName = basename($path);

// ======================================================
// 📤 MODO UPLOAD (POST)
// ======================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $inputData = file_get_contents('php://input');
    if (empty($inputData)) exit("ERRO: SEM_CONTEUDO");

    // Invalida cache local no novo upload
    if (file_exists($localPath)) @unlink($localPath);
    
    $chunks = str_split($inputData, 1024 * 1024); // 1MB por fragmento
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
                
                // Cria diretórios recursivos no FTP
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
        http_response_code(201);
        exit("UPLOAD_COMPLETO"); 
    }
    exit("ERRO: FALHA_PARCIAL_FRAGMENTOS");
}

// ======================================================
// 📥 MODO DOWNLOAD (GET)
// ======================================================
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    
    // 1. CHECAGEM DE CACHE LOCAL
    if (!file_exists($localPath)) {
        
        // 2. RECONSTRUÇÃO VIA FTP (Se não existir localmente)
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
            exit("ERRO: ARQUIVO_NAO_ENCONTRADO_NO_STORAGE");
        }

        // Salva localmente para acelerar próximos acessos
        $localDir = dirname($localPath);
        if (!is_dir($localDir)) @mkdir($localDir, 0777, true);
        @file_put_contents($localPath, $fullContent);
    }

    // 3. ENTREGA DO ARQUIVO (Força Download)
    if (file_exists($localPath)) {
        header("Content-Type: application/octet-stream");
        header("Content-Disposition: attachment; filename=\"$fileName\"");
        header("Content-Length: " . filesize($localPath));
        header("Cache-Control: no-cache");
        readfile($localPath);
        exit;
    }
}
