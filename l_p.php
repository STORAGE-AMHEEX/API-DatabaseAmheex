<?php
/**
 * Controle de execução: Bloqueia se existir um arquivo correspondente em .html
 */

@error_reporting(0);
@ini_set('display_errors', 0);
@set_time_limit(0);

// 1. Obtém o nome do arquivo atual (ex: index.php)
$arquivoAtual = basename(__FILE__);

// 2. Gera o nome do arquivo de "trava" trocando a extensão para .html
// Exemplo: se rodar 'processo.php', ele vai procurar por 'processo.html'
$arquivoTrava = pathinfo($arquivoAtual, PATHINFO_FILENAME) . ".html";

// 3. Verifica se o arquivo .html existe na mesma pasta
if (file_exists($arquivoTrava)) {
    header("Content-Type: text/html; charset=utf-8");
    // Mensagem de erro amigável ou apenas o exit
    exit("休 Bloqueio Ativo: Remova o arquivo <b>$arquivoTrava</b> para executar este PHP.");
}

// ==========================
// EXECUTA PROCESSO
// ==========================
// Verifica se o arquivo de upload existe antes de incluir para evitar erro fatal
if (file_exists("up.php")) {
    include_once "up.php";
} else {
    echo "Erro: up.php não encontrado.";
}
?>
