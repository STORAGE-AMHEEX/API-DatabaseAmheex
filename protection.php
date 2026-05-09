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

// bloqueio de domínio
if (!in_array($host, $allowedDomains)) {
    http_response_code(403);
    exit;
}

/*
========================================
LIBERAÇÃO POR TEMPO (CORE)
========================================
*/

// tempo de acesso após "ativação"
$tempoLiberado = 300; // 5 minutos

if (!isset($_SESSION['amheex_start'])) {
    $_SESSION['amheex_start'] = time();
}

// verifica tempo
if (time() - $_SESSION['amheex_start'] > $tempoLiberado) {
    http_response_code(403);
    exit("Sessão expirada");
}

// marca sistema liberado
define("AMHEEX_ACTIVE", true);