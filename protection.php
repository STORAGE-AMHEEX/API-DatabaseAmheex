<?php
session_start();

/*
========================================
CONFIG AMHEEX
========================================
*/

$allowedDomains = [
    "amheex.web.app",
    "amheex.com",
    "api-amheex.vercel.app",
    "api-amheex.onrender.com",
    "localhost"
];

// host real
$host = $_SERVER['HTTP_HOST'] ?? '';
$host = preg_replace('/:\d+$/', '', $host);

/*
========================================
VERIFICA DOMÍNIO
========================================
*/

$isAllowedDomain = in_array($host, $allowedDomains);

// bloqueia totalmente se não for permitido
if (!$isAllowedDomain) {
    http_response_code(403);
    exit("Domínio não autorizado");
}

/*
========================================
NÍVEL DE PERMISSÃO
========================================
*/

// domínio dedicado (liberação completa)
$dedicatedDomains = [
    "amheex.com",
    "api-amheex.onrender.com"
];

$isDedicated = in_array($host, $dedicatedDomains);

/*
========================================
SESSÃO + TEMPO
========================================
*/

$tempoLiberado = 300; // 5 minutos

if (!isset($_SESSION['amheex_start'])) {
    $_SESSION['amheex_start'] = time();
}

// expiração
if (time() - $_SESSION['amheex_start'] > $tempoLiberado) {
    http_response_code(403);
    exit("Sessão expirada");
}

/*
========================================
FLAGS DO SISTEMA
========================================
*/

define("AMHEEX_ACTIVE", true);

// liberação padrão
define("AMHEEX_READ", true);

// liberação extra apenas para domínio dedicado
define("AMHEEX_UPLOAD", $isDedicated);
define("AMHEEX_ADVANCED_ACTIONS", $isDedicated);

/*
========================================
EXEMPLO DE USO
========================================

// upload só funciona se:
if (!AMHEEX_UPLOAD) {
    exit("Upload bloqueado neste domínio");
}

*/
?>