<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') exit("METHOD_NOT_ALLOWED");

$inputData = file_get_contents('php://input');
if (empty($inputData) || empty($logins)) exit("ERROR_INVALID_STATE");

if (file_exists($localPath)) @unlink($localPath);

// Quebra o arquivo em partes de 512KB
$chunks = str_split($inputData, 1024 * 512);
$total = count($chunks); 
$success = 0;

foreach ($chunks as $idx => $chunk) {
    $pName = "part_" . ($idx + 1) . ".txt";
    foreach ($logins as $cfg) {
        $c = @ftp_connect($cfg['host'], 21, 10);
        if ($c && @ftp_login($c, $cfg['user'], $cfg['pass'])) {
            ftp_pasv($c, true);
            @ftp_chdir($c, "htdocs") || @ftp_chdir($c, "public_html");
            
            if (!@ftp_chdir($c, "X")) { @ftp_mkdir($c, "X"); @ftp_chdir($c, "X"); }
            
            foreach (explode('/', $path) as $d) { 
                if (!empty($d) && !@ftp_chdir($c, $d)) { 
                    if (@ftp_mkdir($c, $d)) @ftp_chdir($c, $d); 
                } 
            }
            
            $tmp = tmpfile(); 
            fwrite($tmp, $chunk); 
            rewind($tmp);
            
            if (@ftp_fput($c, $pName, $tmp, FTP_BINARY)) { 
                $success++; 
                @ftp_close($c); 
                break; 
            }
            @ftp_close($c);
        }
    }
}

echo ($success === $total ? "OK" : "PARTIAL_ERROR");
exit;
