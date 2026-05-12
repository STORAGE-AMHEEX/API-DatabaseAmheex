<?php
/**
 * Redirector Core
 * Encaminha a requisição para o diretório de armazenamento /X/
 */

// 1. Identifica o protocolo (http ou https)
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";

// 2. Obtém o domínio/host atual
$host = $_SERVER['HTTP_HOST'];

// 3. Resgata o PATH (o que vem depois do domínio ou do arquivo .php)
$path = ltrim($_SERVER['PATH_INFO'] ?? '', '/');

if (!$path) {
    // Caso o PATH_INFO não esteja disponível, tenta extrair da URI
    $uri = explode('?', $_SERVER['REQUEST_URI'])[0];
    $path = ltrim(str_replace($_SERVER['SCRIPT_NAME'], '', $uri), '/');
}

// 4. Se não houver path definido, você pode definir um erro ou um destino padrão
if (!$path) {
    die("ERRO: Nenhum caminho (PATH) definido para redirecionamento.");
}

// 5. Monta a URL final: DOMINIO + /X/ + PATH
$targetUrl = $protocol . $host . "/X/" . $path;

// 6. Executa o redirecionamento imediato
header("Location: " . $targetUrl);
exit;
