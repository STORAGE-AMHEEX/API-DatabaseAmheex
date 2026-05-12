<?php
include_once "up.php";
sleep(1); // Pausa por 2 segundos

include_once "ar.php";
sleep(2); 

// Note que você repetiu o dw.php no seu exemplo. 
// O include_once impedirá que ele seja carregado novamente.
include_once "dw.php"; 
sleep(3);

include_once "re.php";
