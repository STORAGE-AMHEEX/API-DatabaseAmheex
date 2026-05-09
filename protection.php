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

    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    }

    elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    }

    else {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    return trim($ip);
}

// ============================
// GERA ID PELO IP
// EX: 129.29.29.2 => ID12929292
// ============================
function gerar_id_ip($ip) {
    return 'ID' . preg_replace('/[^0-9]/', '', $ip);
}

$meuIP = get_client_ip();
$meuID = gerar_id_ip($meuIP);

$arquivo = 'ips.json';

// ============================
// URL ATUAL
// ============================
$urlAtual =
(
    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    ? 'https://'
    : 'http://'
)
. ($_SERVER['HTTP_HOST'] ?? 'localhost')
. ($_SERVER['REQUEST_URI'] ?? '/');

// ============================
// DATA/HORA
// ============================
$dataHora = date('d/m/Y H:i:s');

// ============================
// CRIA JSON SE NÃO EXISTIR
// ============================
if (!file_exists($arquivo)) {

    file_put_contents(
        $arquivo,
        json_encode(
            [],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
        )
    );
}

// ============================
// LÊ JSON
// ============================
$dados = json_decode(file_get_contents($arquivo), true);

if (!is_array($dados)) {
    $dados = [];
}

// ============================
// AUTO REGISTRO
// ============================
if (!isset($dados[$meuIP])) {

    $dados[$meuIP] = [
        "status" => false,
        "id" => $meuID,
        "ip" => $meuIP,
        "data_registro" => $dataHora,
        "ultima_atualizacao" => $dataHora,
        "ultima_url" => $urlAtual
    ];

    file_put_contents(
        $arquivo,
        json_encode(
            $dados,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
        )
    );

    ob_end_clean();

    header('HTTP/1.0 403 Forbidden');

    echo "
    <!DOCTYPE html>
    <html lang='pt-br'>
    <head>

        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>

        <style>

            *{
                margin:0;
                padding:0;
                box-sizing:border-box;
            }

            body{
                background:#050505;
                font-family:Arial,sans-serif;
                min-height:100vh;
                display:flex;
                align-items:center;
                justify-content:center;
                padding:20px;
                color:#fff;
            }

            .box{
                width:100%;
                max-width:460px;
                background:#111;
                border-radius:20px;
                padding:30px 22px;
                border:1px solid rgba(255,0,60,0.35);
                box-shadow:0 0 35px rgba(255,0,60,0.20);
                text-align:center;
            }

            .icon{
                font-size:65px;
                margin-bottom:15px;
            }

            h1{
                color:#ff3355;
                font-size:28px;
                margin-bottom:12px;
            }

            .desc{
                color:#cfcfcf;
                line-height:1.6;
                font-size:15px;
                margin-bottom:22px;
            }

            .card{
                background:#1a1a1a;
                border-radius:14px;
                padding:15px;
                margin-top:12px;
                border:1px solid rgba(255,255,255,0.05);
            }

            .label{
                color:#777;
                font-size:12px;
                letter-spacing:1px;
                margin-bottom:6px;
            }

            .value{
                color:#fff;
                font-size:18px;
                word-break:break-word;
            }

            .footer{
                margin-top:22px;
                color:#666;
                font-size:12px;
                line-height:1.5;
            }

            @media(max-width:480px){

                .box{
                    padding:25px 18px;
                }

                h1{
                    font-size:24px;
                }

                .value{
                    font-size:16px;
                }

            }

        </style>

    </head>

    <body>

        <div class='box'>

            <div class='icon'>⚠️</div>

            <h1>ACESSO BLOQUEADO</h1>

            <div class='desc'>
                Seu acesso foi registrado no sistema.<br>
                Aguarde autorização do administrador.
            </div>

            <div class='card'>
                <div class='label'>IDENTIFICAÇÃO</div>
                <div class='value'>$meuID</div>
            </div>

            <div class='card'>
                <div class='label'>REGISTRO</div>
                <div class='value'>$dataHora</div>
            </div>

            <div class='footer'>
                Somente acessos autorizados podem utilizar o sistema.
            </div>

        </div>

    </body>
    </html>
    ";

    exit;
}

// ============================
// CORRIGE IPS ANTIGOS
// ============================
if (!is_array($dados[$meuIP])) {

    $statusAntigo = ($dados[$meuIP] === true);

    $dados[$meuIP] = [
        "status" => $statusAntigo,
        "id" => $meuID,
        "ip" => $meuIP,
        "data_registro" => $dataHora,
        "ultima_atualizacao" => $dataHora,
        "ultima_url" => $urlAtual
    ];
}

// ============================
// GARANTE CAMPOS
// ============================
if (!isset($dados[$meuIP]["ultima_atualizacao"])) {
    $dados[$meuIP]["ultima_atualizacao"] = $dataHora;
}

if (!isset($dados[$meuIP]["ultima_url"])) {
    $dados[$meuIP]["ultima_url"] = $urlAtual;
}

if (!isset($dados[$meuIP]["id"])) {
    $dados[$meuIP]["id"] = $meuID;
}

// ============================
// ATUALIZA ACESSO
// ============================
$dados[$meuIP]["ultima_atualizacao"] = $dataHora;
$dados[$meuIP]["ultima_url"] = $urlAtual;

// ============================
// SALVA JSON
// ============================
file_put_contents(
    $arquivo,
    json_encode(
        $dados,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
    )
);

// ============================
// STATUS
// ============================
$statusLiberado = false;

if (is_array($dados[$meuIP])) {

    $statusLiberado =
        $dados[$meuIP]["status"] ?? false;

} else {

    $statusLiberado =
        ($dados[$meuIP] === true);
}

// ============================
// BLOQUEADO
// ============================
if ($statusLiberado !== true) {

    ob_end_clean();

    header('HTTP/1.0 403 Forbidden');

    echo "
    <!DOCTYPE html>
    <html lang='pt-br'>
    <head>

        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>

        <style>

            *{
                margin:0;
                padding:0;
                box-sizing:border-box;
            }

            body{
                background:#050505;
                font-family:Arial,sans-serif;
                min-height:100vh;
                display:flex;
                align-items:center;
                justify-content:center;
                padding:20px;
                color:#fff;
            }

            .box{
                width:100%;
                max-width:460px;
                background:#111;
                border-radius:20px;
                padding:30px 22px;
                border:1px solid rgba(255,0,60,0.35);
                box-shadow:0 0 35px rgba(255,0,60,0.20);
                text-align:center;
            }

            .icon{
                font-size:65px;
                margin-bottom:15px;
            }

            h1{
                color:#ff3355;
                font-size:28px;
                margin-bottom:12px;
            }

            .desc{
                color:#cfcfcf;
                line-height:1.6;
                font-size:15px;
                margin-bottom:22px;
            }

            .card{
                background:#1a1a1a;
                border-radius:14px;
                padding:15px;
                margin-top:12px;
                border:1px solid rgba(255,255,255,0.05);
            }

            .label{
                color:#777;
                font-size:12px;
                letter-spacing:1px;
                margin-bottom:6px;
            }

            .value{
                color:#fff;
                font-size:18px;
                word-break:break-word;
            }

            .footer{
                margin-top:22px;
                color:#666;
                font-size:12px;
                line-height:1.5;
            }

        </style>

    </head>

    <body>

        <div class='box'>

            <div class='icon'>⛔</div>

            <h1>ACESSO NEGADO</h1>

            <div class='desc'>
                Esta identificação ainda não possui autorização.
            </div>

            <div class='card'>
                <div class='label'>IDENTIFICAÇÃO</div>
                <div class='value'>$meuID</div>
            </div>

            <div class='card'>
                <div class='label'>ÚLTIMA ATUALIZAÇÃO</div>
                <div class='value'>$dataHora</div>
            </div>

            <div class='footer'>
                Aguarde liberação para continuar.
            </div>

        </div>

    </body>
    </html>
    ";

    exit;
}

ob_end_flush();
?>