<?php
session_start();

/*
========================================
CARREGA TXT
========================================
*/

$txtFile = __DIR__ . "/dominios.txt";
$domainsTxt = [];

if (file_exists($txtFile)) {
    $domainsTxt = file($txtFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
}

/*
========================================
CARREGA JSON
========================================
*/

$jsonFile = __DIR__ . "/dominios.json";
$domainsJson = [];

if (file_exists($jsonFile)) {
    $data = json_decode(file_get_contents($jsonFile), true);

    if (is_array($data)) {
        foreach ($data as $item) {
            if (isset($item['domain'])) {
                $domainsJson[] = $item['domain'];
            }
        }
    }
}

/*
========================================
COMBINA FONTES
========================================
*/

$allowedDomains = array_unique(array_merge($domainsTxt, $domainsJson, [
    "localhost"
]));

/*
========================================
HOST REAL
========================================
*/

$host = strtolower($_SERVER['HTTP_HOST'] ?? '');
$host = preg_replace('/:\d+$/', '', $host);

/*
========================================
VALIDAÇÃO
========================================
*/

$isAllowedDomain = false;

foreach ($allowedDomains as $domain) {

    $domain = strtolower(trim($domain));

    if ($host === $domain) {
        $isAllowedDomain = true;
        break;
    }

    if (str_ends_with($host, "." . $domain)) {
        $isAllowedDomain = true;
        break;
    }
}

/*
========================================
BLOQUEIO
========================================
*/

if (!$isAllowedDomain) {
    http_response_code(403);
    exit("Domínio não autorizado: " . $host);
}

/*
========================================
DOMÍNIOS DEDICADOS (JSON FUTURO)
========================================
*/

$dedicatedDomains = [
    "amheex.com",
    "api-amheex.onrender.com"
];

$isDedicated = in_array($host, $dedicatedDomains);

/*
========================================
SESSÃO
========================================
*/

$tempoLiberado = 300;

if (!isset($_SESSION['amheex_start'])) {
    $_SESSION['amheex_start'] = time();
}

if (time() - $_SESSION['amheex_start'] > $tempoLiberado) {
    http_response_code(403);
    exit("Sessão expirada");
}

/*
========================================
FLAGS
========================================
*/

define("AMHEEX_ACTIVE", true);
define("AMHEEX_READ", true);
define("AMHEEX_UPLOAD", $isDedicated);
define("AMHEEX_ADVANCED_ACTIONS", $isDedicated);

?>