<?php
@ini_set('memory_limit', '1024M');
@ini_set('max_execution_time', '0');
@set_time_limit(0);

while (ob_get_level()) {
    @ob_end_clean();
}

header('Content-Type: text/plain; charset=utf-8');

define('BASE_DIR', realpath(__DIR__) . '/X');

if (!is_dir(BASE_DIR)) {
    @mkdir(BASE_DIR, 0777, true);
}

$firebaseBaseUrl = 'https://database-amheex-default-rtdb.firebaseio.com/';
$raizStorage     = '#';
$tempoMinimo     = 5;  
$tempoMaximo     = 180; 
$agora           = time();

function firebase_request($url, $method = 'GET', $data = null) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
    curl_setopt($ch, CURLOPT_TIMEOUT, 120);

    if ($data !== null) {
        $payload = json_encode($data);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: json',
            'Content-Length: ' . strlen($payload)
        ]);
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode >= 400) {
        return false;
    }
    return $response;
}

function obterNodePathFirebase($relativeFilePath, $raizStorage) {
    $base64Path = rtrim(strtr(base64_encode($relativeFilePath), '+/', '-_'), '=');
    return $raizStorage . '/' . $base64Path;
}

// BACKUP: Envia parte por parte (1MB), aguardando o sucesso de cada uma antes de ir para a próxima
function enviarBackupFirebaseStreaming($fullLocalPath, $relativeFilePath, $firebaseBaseUrl, $raizStorage) {
    if (!file_exists($fullLocalPath) || filesize($fullLocalPath) === 0) {
        return false;
    }

    $nodePath = obterNodePathFirebase($relativeFilePath, $raizStorage);
    $handle = @fopen($fullLocalPath, 'rb');
    if (!$handle) {
        return false;
    }

    $chunkSize = 1048576; // 1MB por parte
    $partIndex = 1;
    $sucessoGlobal = true;

    while (!feof($handle)) {
        @set_time_limit(0);
        $binaryData = fread($handle, $chunkSize);
        if ($binaryData === false) {
            $sucessoGlobal = false;
            break;
        }

        if (strlen($binaryData) === 0) {
            break;
        }

        $hexChunk = bin2hex($binaryData);
        $partName = 'part_' . $partIndex;
        $url = $firebaseBaseUrl . $nodePath . '/' . $partName . '.json';

        $tentativas = 0;
        $enviadoComSucesso = false;

        while ($tentativas < 10) {
            $resposta = firebase_request($url, 'PUT', $hexChunk);
            if ($resposta !== false) {
                $enviadoComSucesso = true;
                break;
            }
            $tentativas++;
            usleep(300000); 
        }

        if (!$enviadoComSucesso) {
            $sucessoGlobal = false;
            break;
        }

        unset($binaryData, $hexChunk);
        $partIndex++;
    }

    fclose($handle);
    return $sucessoGlobal;
}

// RESTAURAÇÃO: Lê diretamente part_1.json, part_2.json... sem baixar o nó inteiro (evita travamentos)
function restaurarArquivoFirebase($fullLocalPath, $relativeFilePath, $firebaseBaseUrl, $raizStorage) {
    $nodePath = obterNodePathFirebase($relativeFilePath, $raizStorage);
    
    $fileDir = dirname($fullLocalPath);
    if (!is_dir($fileDir)) {
        @mkdir($fileDir, 0777, true);
    }

    $handle = @fopen($fullLocalPath, 'wb');
    if (!$handle) {
        return false;
    }

    $partIndex = 1;
    $restauradoComSucesso = true;

    while (true) {
        @set_time_limit(0);
        $partName = 'part_' . $partIndex;
        $url = $firebaseBaseUrl . $nodePath . '/' . $partName . '.json';

        $tentativas = 0;
        $conteudoParte = false;

        while ($tentativas < 10) {
            $resposta = firebase_request($url, 'GET');
            
            // Se retornar null, vazio ou erro HTTP, tratamos como o fim das partes
            if ($resposta === false || $resposta === 'null' || empty($resposta)) {
                $conteudoParte = null;
                break;
            }

            // O Firebase retorna o dado com aspas (ex: "48656c6c6f") quando é uma string pura salva via PUT
            $decodificado = json_decode($resposta, true);
            if ($decodificado !== null) {
                $conteudoParte = $decodificado;
                break;
            } else {
                // Caso venha o texto cru sem JSON válido
                $conteudoParte = trim($resposta, '"');
                break;
            }

            $tentativas++;
            usleep(200000);
        }

        // Se encontrou null ou fim das partes, encerra o loop de leitura
        if ($conteudoParte === null) {
            break; 
        }

        if (is_string($conteudoParte)) {
            $binaryData = hex2bin($conteudoParte);
            if ($binaryData !== false) {
                fwrite($handle, $binaryData);
            } else {
                $restauradoComSucesso = false;
                break;
            }
        } else {
            $restauradoComSucesso = false;
            break;
        }

        $partIndex++;
    }

    fclose($handle);

    if ($restauradoComSucesso && file_exists($fullLocalPath) && filesize($fullLocalPath) > 0) {
        return true;
    }

    if (file_exists($fullLocalPath)) {
        @unlink($fullLocalPath);
    }

    return false;
}

echo "Iniciando varredura por arquivos com 0 bytes na pasta /X/ para restauração...\n\n";

$iteradorRestauracao = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator(BASE_DIR, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::LEAVES_ONLY
);

$arquivosRestaurados = 0;
$errosRestauracao = 0;

foreach ($iteradorRestauracao as $arquivo) {
    if ($arquivo->isFile()) {
        $fullPath = $arquivo->getRealPath();
        
        if (filesize($fullPath) === 0) {
            $relativeFilePath = str_replace('\\', '/', substr($fullPath, strlen(BASE_DIR) + 1));
            echo "Restaurando parte por parte: /X/{$relativeFilePath}...\n";

            if (restaurarArquivoFirebase($fullPath, $relativeFilePath, $firebaseBaseUrl, $raizStorage)) {
                echo " [SUCESSO] Arquivo restaurado!\n";
                $arquivosRestaurados++;
            } else {
                echo " [FALHA] Não foi possível restaurar o arquivo.\n";
                $errosRestauracao++;
            }
        }
    }
}

echo "\nRestauração concluída. Arquivos restaurados: {$arquivosRestaurados} | Erros: {$errosRestauracao}\n\n";
echo "-------------------------------------------------------------------------\n\n";

$iteradorBackup = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator(BASE_DIR, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::LEAVES_ONLY
);

$arquivosProcessados = 0;
$errosEncontrados = 0;

echo "Iniciando varredura por arquivos modificados para backup...\n\n";

foreach ($iteradorBackup as $arquivo) {
    if ($arquivo->isFile()) {
        $fullPath = $arquivo->getRealPath();
        
        if (filesize($fullPath) === 0) {
            continue;
        }

        $tempoModificacao = $arquivo->getMTime();
        $diferencaTempo = $agora - $tempoModificacao;

        if ($diferencaTempo > $tempoMinimo && $diferencaTempo <= $tempoMaximo) {
            $relativeFilePath = str_replace('\\', '/', substr($fullPath, strlen(BASE_DIR) + 1));
            
            echo "Processando backup: /X/{$relativeFilePath}...\n";

            $sucesso = enviarBackupFirebaseStreaming($fullPath, $relativeFilePath, $firebaseBaseUrl, $raizStorage);

            if ($sucesso) {
                echo " [SUCESSO] Backup enviado.\n";
                $arquivosProcessados++;
            } else {
                echo " [FALHA] Erro no backup.\n";
                $errosEncontrados++;
            }
        }
    }
}

echo "\nVarredura de backup concluída.\n";
echo "Arquivos salvos: {$arquivosProcessados}\n";
echo "Erros: {$errosEncontrados}\n";
