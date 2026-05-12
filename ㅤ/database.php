<?php
/**
 * Core: Cyber-Sync-Core v9.0 - Smart Backup & Restore
 * Regra: > 0b e < 5min -> Upload. == 0b -> Download/Restore.
 */

@ini_set('memory_limit', '16000M');
@set_time_limit(0);

$storageFolder = 'X';

// Pasta pública principal do domínio
$publicRoot = rtrim($_SERVER['DOCUMENT_ROOT'], '/');

// Base real
$baseDir = $publicRoot . '/' . $storageFolder;

// 1. Configurações do Cluster
$jsonUrl = 'https://api-amheex.onrender.com/index.json';
$context = stream_context_create(["http" => ["header" => "User-Agent: Mozilla/5.0\r\n"]]);
$jsonRaw = @file_get_contents($jsonUrl, false, $context);
$logins = $jsonRaw ? json_decode($jsonRaw, true) : [];

if (!is_dir($baseDir)) @mkdir($baseDir, 0777, true);

/**
 * Função para escanear todos os arquivos recursivamente
 */
$directory = new RecursiveDirectoryIterator($baseDir);
$iterator = new RecursiveIteratorIterator($directory);

foreach ($iterator as $fileInfo) {
    if ($fileInfo->isDir()) continue;

    $localFilePath = $fileInfo->getPathname();
    $relative    = str_replace($baseDir . '/', '', $localFilePath);
    $fileSize    = $fileInfo->getSize();
    $lastChanged = (time() - $fileInfo->getMTime()); // Segundos desde a última alteração

    // ======================================================
    // LOGICA: RESTAURAR (0 BYTES)
    // ======================================================
    if ($fileSize <= 10) {
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
                    $targets = ["htdocs/$storageFolder/$relative/$partName", "public_html/$storageFolder/$relative/$partName", "$storageFolder/$relative/$partName"];
                    foreach ($targets as $t) {
                        $tmp = fopen('php://temp', 'r+b');
                        if (@ftp_fget($conn, $tmp, $t, FTP_BINARY)) {
                            rewind($tmp); $fullContent .= stream_get_contents($tmp);
                            $foundPart = true; fclose($tmp); break;
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
            @file_put_contents($localFilePath, $fullContent);
            echo "RESTORED: $relative \n";
        }
        continue; // Pula para o próximo arquivo
    }

    // ======================================================
    // LOGICA: BACKUP/UPLOAD (ALTERADO NOS ÚLTIMOS 1 MINUTOS)
    // ======================================================
    if ($lastChanged <= 60 && $fileSize > 0) {
        $inputData = file_get_contents($localFilePath);
        $chunks = str_split($inputData, 1024 * 512); // 512KB por parte

        foreach ($chunks as $idx => $chunk) {
            $pName = "part_" . ($idx + 1) . ".txt";
            foreach ($logins as $cfg) {
                $c = @ftp_connect($cfg['host'], 21, 10);
                if ($c && @ftp_login($c, $cfg['user'], $cfg['pass'])) {
                    ftp_pasv($c, true);
                    @ftp_chdir($c, "htdocs") || @ftp_chdir($c, "public_html");
                    if (!@ftp_chdir($c, $storageFolder)) { @ftp_mkdir($c, $storageFolder); @ftp_chdir($c, $storageFolder); }
                    
                    foreach (explode('/', $relative) as $d) {
                        if (!empty($d) && !@ftp_chdir($c, $d)) { if (@ftp_mkdir($c, $d)) @ftp_chdir($c, $d); }
                    }

                    $tmp = tmpfile(); fwrite($tmp, $chunk); rewind($tmp);
                    if (@ftp_fput($c, $pName, $tmp, FTP_BINARY)) { @ftp_close($c); break; }
                    @ftp_close($c);
                }
            }
        }
        echo "BACKUP_SYNCED: $relative \n";
    }
}

echo "SYNC_CYCLE_COMPLETE";
