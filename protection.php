<?php
ob_start();

// ============================
// BYPASS PARA ESTE ARQUIVO
// ============================
$arquivoAtual = basename($_SERVER['SCRIPT_NAME']);
if ($arquivoAtual === 'painel_protection.php') {
    ob_end_flush();
    return;
}

// ============================
// IP DO CLIENTE
// ============================
function get_client_ip() {
    $ip = '0.0.0.0';
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) $ip = $_SERVER['HTTP_CLIENT_IP'];
    elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    else $ip = $_SERVER['REMOTE_ADDR'];
    return trim($ip);
}

$meuIP = get_client_ip();
$arquivo = 'ips.json';

// ============================
// CRIA JSON SE NÃO EXISTIR
// ============================
if (!file_exists($arquivo)) {
    file_put_contents($arquivo, json_encode([], JSON_PRETTY_PRINT));
}

$dados = json_decode(file_get_contents($arquivo), true);

// ============================
// AUTO REGISTRO
// ============================
if (!isset($dados[$meuIP])) {
    $dados[$meuIP] = false;
    file_put_contents($arquivo, json_encode($dados, JSON_PRETTY_PRINT));

    ob_end_clean();
    header('HTTP/1.0 403 Forbidden');

    echo "
    <div style='
        background:#0b0b0b;
        color:#fff;
        height:100vh;
        display:flex;
        align-items:center;
        justify-content:center;
        font-family:Arial;
    '>
        <div style='
            text-align:center;
            padding:30px;
            border:1px solid #ff0033;
            border-radius:12px;
            background:#111;
            box-shadow:0 0 20px rgba(255,0,0,0.3);
            max-width:420px;
        '>
            <div style='font-size:60px;'>⚠️</div>
            <h1 style='color:#ff0033;margin:10px 0;'>ACESSO BLOQUEADO</h1>
            <p style='color:#ccc;'>
                Seu IP <b style='color:#fff;'>$meuIP</b> foi registrado.<br>
                Aguarde autorização do administrador.
            </p>
            <small style='color:#666;'>
                Somente usuários permitidos podem acessar este sistema.
            </small>
        </div>
    </div>
    ";
    exit;
}

// ============================
// VALIDAÇÃO
// ============================
if ($dados[$meuIP] !== true) {
    ob_end_clean();
    header('HTTP/1.0 403 Forbidden');

    echo "
    <div style='
        background:#0b0b0b;
        color:#fff;
        height:100vh;
        display:flex;
        align-items:center;
        justify-content:center;
        font-family:Arial;
    '>
        <div style='
            text-align:center;
            padding:30px;
            border:1px solid #ff0033;
            border-radius:12px;
            background:#111;
            box-shadow:0 0 20px rgba(255,0,0,0.3);
            max-width:420px;
        '>
            <div style='font-size:60px;'>⚠️</div>
            <h1 style='color:#ff0033;margin:10px 0;'>ACESSO NEGADO</h1>
            <p style='color:#ccc;'>
                Seu IP <b style='color:#fff;'>$meuIP</b> não foi liberado pelo administrador.
            </p>
            <small style='color:#666;'>
                Permissão necessária para continuar.
            </small>
        </div>
    </div>
    ";
    exit;
}

ob_end_flush();
?>