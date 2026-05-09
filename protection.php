<?php
ob_start();

// ============================
// BYPASS PARA ESTE ARQUIVO
// ============================
$arquivoAtual = basename($_SERVER['SCRIPT_NAME']);
if ($arquivoAtual === 'painel_protection.php') {
    ob_end_flush();
    return; // não aplica proteção
}

function get_client_ip() {
    $ip = '0.0.0.0';
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) $ip = $_SERVER['HTTP_CLIENT_IP'];
    elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    else $ip = $_SERVER['REMOTE_ADDR'];
    return trim($ip);
}

$meuIP = get_client_ip();
$arquivo = 'ips.json';

// Cria o arquivo se não existir
if (!file_exists($arquivo)) {
    file_put_contents($arquivo, json_encode([], JSON_PRETTY_PRINT));
}

$dados = json_decode(file_get_contents($arquivo), true);

// LÓGICA DE AUTO-REGISTRO
if (!isset($dados[$meuIP])) {
    $dados[$meuIP] = false;
    file_put_contents($arquivo, json_encode($dados, JSON_PRETTY_PRINT));

    ob_end_clean();
    header('HTTP/1.0 403 Forbidden');
    echo "<h1 style='color:#ff0033;'>Acesso Bloqueado</h1>";
    echo "<p>Seu IP ($meuIP) foi registrado e aguarda autorização.</p>";
    exit;
}

// LÓGICA DE VALIDAÇÃO
if ($dados[$meuIP] !== true) {
    ob_end_clean();
    header('HTTP/1.0 403 Forbidden');
    echo "<h1 style='color:#ff0033;'>Acesso Não Autorizado</h1>";
    echo "<p>O processamento para o IP $meuIP ainda não foi liberado.</p>";
    exit;
}

ob_end_flush();
?>