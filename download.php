<?php
/**
 * Redirector Core - Protegido contra Loop
 */

// 1. Identifica o protocolo
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";

// 2. Obtém o domínio e a URI completa
$host = $_SERVER['HTTP_HOST'];
$uri  = $_SERVER['REQUEST_URI'];

// --- TRAVA DE SEGURANÇA (ANTI-LOOP) ---
// Se a URL já começar com /X/, paramos o script para evitar o redirecionamento infinito.
if (strpos($uri, '/X/') === 0 || strpos($uri, 'X/') === 1) {
    // Já estamos no destino, não faz nada ou carrega o conteúdo.
    return; 
}

// 3. Resgata o PATH
$path = ltrim($_SERVER['PATH_INFO'] ?? '', '/');

if (!$path) {
    $cleanUri = explode('?', $uri)[0];
    $path = ltrim(str_replace($_SERVER['SCRIPT_NAME'], '', $cleanUri), '/');
}

// 4. Validação de segurança
if (!$path) {
    die("ERRO: Nenhum caminho definido.");
}

// 5. Monta a URL final
$targetUrl = $protocol . $host . "/X/" . $path;

// 6. Redirecionamento 301 (Permanente) ou 302 (Temporário)
header("Location: " . $targetUrl, true, 301);
exit;
