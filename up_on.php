<?php
// Caminho para o arquivo local
$arquivo = 'up_on.txt';

// Verifica se o arquivo existe
if (file_exists($arquivo)) {
    // Lê o conteúdo do arquivo e remove espaços em branco/quebras de linha
    $conteudo = trim(file_get_contents($arquivo));

    // Se o texto de dentro do arquivo for exatamente "1"
    if ($conteudo === '1') {
        
        // --- Executa o seu código PHP abaixo ---
        sleep(0);
        include_once "AMHEEX_DOMAIN_STORAGE_INFINITY.php";
        // ----------------------------------------
        
    } else {
        // Opcional: O que fazer se o arquivo não tiver o número 1
        // echo "O arquivo não contém o texto '1'.";
    }
} else {
    // Opcional: O que fazer se o arquivo up_on.txt não for encontrado
    // echo "Arquivo up_on.txt não encontrado.";
}
