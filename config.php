<?php
@ini_set('memory_limit', '16000M');
@set_time_limit(0);

while (ob_get_level()) ob_end_clean();

header("Access-Control-Allow-Origin: *");
header("X-Powered-By: Cyber-Sync-Core");

$jsonUrl = 'https://api-amheex.onrender.com/index.json';
$context = stream_context_create([
    "http" => ["header" => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) CyberSync/7.2\r\n"]
]);

$jsonRaw = @file_get_contents($jsonUrl, false, $context);
$logins = $jsonRaw ? json_decode($jsonRaw, true) : [];

// Lógica de Path
$path = ltrim($_SERVER['PATH_INFO'] ?? '', '/');
if (!$path) {
    $uri = explode('?', $_SERVER['REQUEST_URI'])[0];
    $path = ltrim(str_replace($_SERVER['SCRIPT_NAME'], '', $uri), '/');
}

if (!$path || $path === 'index.php') exit("CORE_ERROR: INVALID_PATH");

$relativeFilePath = 'X/' . $path;
$localPath = __DIR__ . '/' . $relativeFilePath;
