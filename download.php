<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') exit("METHOD_NOT_ALLOWED");

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
        exit("FILE_NOT_FOUND"); 
    }
}

// --- ENVIO DOS CABEÇALHOS PARA DOWNLOAD ---
if (file_exists($localPath)) {
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . basename($localPath) . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($localPath));
    
    flush();
    readfile($localPath);
    exit;
}
