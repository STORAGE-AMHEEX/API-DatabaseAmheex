<?php
$json = file_get_contents('dominios.json');
$dados = json_decode($json, true);
$autorizados = $dados['autorizados'];

// Pega o domínio de onde vem a requisição
$origem = parse_url($_SERVER['HTTP_REFERER'] ?? '', PHP_URL_HOST);

if (!$origem || !in_array($origem, $autorizados)) {
    header('HTTP/1.1 403 Forbidden');
    echo "Domínio não autorizado para esta operação.";
    exit;
}
?>
