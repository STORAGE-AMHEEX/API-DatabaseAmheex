<?php

$domain = $_SERVER['HTTP_HOST'] ?? 'unknown';

// remove porta
$domain = preg_replace('/:\d+$/', '', strtolower($domain));

// arquivo de log
$file = __DIR__ . "/dominios.txt";

// evita duplicação
$existing = file_exists($file) ? file($file, FILE_IGNORE_NEW_LINES) : [];

if (!in_array($domain, $existing)) {
    file_put_contents($file, $domain . PHP_EOL, FILE_APPEND);
}

/*
========================================
REDIRECIONA PARA SUA APP NORMAL
========================================
*/

include "index.php";