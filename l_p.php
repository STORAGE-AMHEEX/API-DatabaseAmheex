<?php
/**
 * Controle de execução usando JSON interno
 */

@error_reporting(0);
@ini_set('display_errors', 0);
@set_time_limit(0);

// ==========================
// JSON INTERNO
// ==========================
$json = '[
    "index.php","index.html","database.php"
]';

// ==========================
// ARQUIVO ATUAL
// ==========================
$arquivoAtual = basename(__FILE__);

// ==========================
// DECODIFICA JSON
// ==========================
$lista = json_decode($json, true);

if (!is_array($lista)) {
    $lista = [];
}

// ==========================
// VERIFICA BLOQUEIO
// ==========================
if (in_array($arquivoAtual, $lista)) {

    header("Content-Type: text/plain");
    exit("ARQUIVO BLOQUEADO PELO JSON");
}

// ==========================
// EXECUTA PROCESSO
// ==========================
include_once "up.php";
?>