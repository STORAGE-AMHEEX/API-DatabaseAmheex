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

$firebaseBaseUrl = 'https://storage-amheex-default-rtdb.firebaseio.com/';
$raizStorage     = 'X';
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
            usleep(500000); 
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

// FUNÇÃO DE RESTAURAÇÃO OTIMIZADA PARA MÁXIMA VELOCIDADE
function restaurarArquivoFirebase($fullLocalPath, $relativeFilePath, $firebaseBaseUrl, $raizStorage) {
    $nodePath = obterNodePathFirebase($relativeFilePath, $raizStorage);
    // Baixa o nó inteiro de uma só vez (evita requisições HTTP múltiplas por partes)
    $url = $firebaseBaseUrl . $nodePath . '.json';

    $resposta = firebase_request($url, 'GET');
    if ($resposta === false) {
        return false;
    }

    $dados = json_decode($resposta, true);
    if (empty($dados) || !is_array($dados)) {
        return false;
    }

    // Ordenação rápida das chaves em memória
    uksort($dados, function($a, $b) {
        return intval(substr($a, 5)) <=> intval(substr($b, 5));
    });

    $fileDir = dirname($fullLocalPath);
    if (!is_dir($fileDir)) {
        @mkdir($fileDir, 0777, true);
    }

    $handle = @fopen($fullLocalPath, 'wb');
    if (!$handle) {
        return false;
    }

    $restauradoComSucesso = true;
    foreach ($dados as $hexChunk) {
        if (is_string($hexChunk)) {
            $binaryData = hex2bin($hexChunk);
            if ($binaryData !== false) {
                fwrite($handle, $binaryData);
            } else {
                $restauradoComSucesso = false;
            }
        }
    }

    fclose($handle);

    return ($restauradoComSucesso && file_exists($fullLocalPath) && filesize($fullLocalPath) > 0);
}

echo "Iniciando varredura por arquivos com 0 bytes na pasta /X/ para restauração ultra rápida...\n\n";

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
            echo "Restaurando rapidamente: /X/{$relativeFilePath}...\n";

            if (restaurarArquivoFirebase($fullPath, $relativeFilePath, $firebaseBaseUrl, $raizStorage)) {
                echo " [SUCESSO] Restaurado!\n";
                $arquivosRestaurados++;
            } else {
                echo " [FALHA] Erro na restauração.\n";
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
