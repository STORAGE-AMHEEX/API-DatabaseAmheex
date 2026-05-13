<?php

// 1. Silêncio e Performance
@error_reporting(0);
@ini_set('display_errors', 0);
@ini_set('memory_limit', '64M');
@set_time_limit(70);

// 2. Configuração de Host e Base
$protocol = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'))
    ? 'https://'
    : 'http://';

$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scriptName = $_SERVER['SCRIPT_NAME'];

$baseDir = rtrim(
    str_replace(basename($scriptName), '', $scriptName),
    '/'
);

// 3. Captura Precisa do Path
$path = '';
$requestUri = explode('?', $_SERVER['REQUEST_URI'])[0];

if (!empty($_SERVER['PATH_INFO'])) {

    $path = $_SERVER['PATH_INFO'];

} else {

    if (strpos($requestUri, $scriptName) === 0) {
        $path = substr($requestUri, strlen($scriptName));
    } else {
        $path = str_replace(dirname($scriptName), '', $requestUri);
    }
}

$path = trim($path, '/');

// Segurança
$path = str_replace(
    ['../', '..\\', "\0"],
    '',
    urldecode($path)
);

$localPath = __DIR__ . '/./' . $path;

// 4. Espera até 60s verificando a cada 1s
if (!empty($path) && @file_exists($localPath) && !is_dir($localPath)) {

    $ultimoTamanho = -1;
    $arquivoAtualizou = false;

    // Verifica por até 60 segundos
    for ($i = 0; $i < 60; $i++) {

        @clearstatcache(true, $localPath);

        $tamanhoAtual = @filesize($localPath);

        // Se tamanho mudou
        if ($tamanhoAtual !== false && $tamanhoAtual != $ultimoTamanho) {

            $ultimoTamanho = $tamanhoAtual;
            $arquivoAtualizou = true;

        } else {

            // Se já teve atualização antes e agora estabilizou
            if ($arquivoAtualizou && $tamanhoAtual > 0) {
                break;
            }
        }

        sleep(1);
    }

    // Atualiza novamente antes do redirect
    @clearstatcache(true, $localPath);

    $size = @filesize($localPath);

    // Redireciona se existir conteúdo
    if ($size !== false && $size > 0) {

        $targetUrl =
            $protocol .
            $host .
            $baseDir .
            '/./' .
            str_replace('\\', '/', $path);

        // Corrige barras duplas
        $targetUrl = preg_replace(
            '/([^:])(\/{2,})/',
            '$1/',
            $targetUrl
        );

        header('Location: ' . $targetUrl, true, 302);
        exit;
    }
}
?>