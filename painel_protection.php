<?php
session_start();

$arquivo = 'ips.json';
$master_key = "2026";

// ============================
// LOGIN
// ============================
if (isset($_POST['login'])) {

    if ($_POST['key'] === $master_key) {

        $_SESSION['auth'] = true;

    } else {

        $erro = "CHAVE INCORRETA";
    }
}

// ============================
// LOGOUT
// ============================
if (isset($_GET['logout'])) {

    session_destroy();

    header("Location: gerenciar_ips.php");

    exit;
}

// ============================
// CRIA JSON
// ============================
if (!file_exists($arquivo)) {

    file_put_contents(
        $arquivo,
        json_encode([], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
    );
}

// ============================
// LÊ JSON
// ============================
$ips = json_decode(file_get_contents($arquivo), true);

if (!is_array($ips)) {
    $ips = [];
}

// ============================
// CORRIGE IPS ANTIGOS
// ============================
foreach ($ips as $ip => $dados) {

    if (!is_array($dados)) {

        $status = ($dados === true);

        $ips[$ip] = [
            "status" => $status,
            "id" => "ID" . preg_replace('/[^0-9]/', '', $ip),
            "ip" => $ip,
            "data_registro" => date('d/m/Y H:i:s'),
            "ultima_atualizacao" => date('d/m/Y H:i:s'),
            "ultima_url" => "desconhecida"
        ];
    }

    // garante campos
    if (!isset($ips[$ip]["id"])) {
        $ips[$ip]["id"] =
            "ID" . preg_replace('/[^0-9]/', '', $ip);
    }

    if (!isset($ips[$ip]["ip"])) {
        $ips[$ip]["ip"] = $ip;
    }

    if (!isset($ips[$ip]["status"])) {
        $ips[$ip]["status"] = false;
    }

    if (!isset($ips[$ip]["data_registro"])) {
        $ips[$ip]["data_registro"] =
            date('d/m/Y H:i:s');
    }

    if (!isset($ips[$ip]["ultima_atualizacao"])) {
        $ips[$ip]["ultima_atualizacao"] =
            date('d/m/Y H:i:s');
    }

    if (!isset($ips[$ip]["ultima_url"])) {
        $ips[$ip]["ultima_url"] = "desconhecida";
    }
}

// salva correções
file_put_contents(
    $arquivo,
    json_encode(
        $ips,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
    )
);

// ============================
// AÇÕES
// ============================
if (
    isset($_SESSION['auth']) &&
    isset($_GET['action']) &&
    isset($_GET['ip'])
) {

    $ip_alvo = $_GET['ip'];
    $acao = $_GET['action'];

    if (
        $acao === 'toggle' &&
        isset($ips[$ip_alvo])
    ) {

        $ips[$ip_alvo]["status"] =
            !$ips[$ip_alvo]["status"];

        $ips[$ip_alvo]["ultima_atualizacao"] =
            date('d/m/Y H:i:s');
    }

    elseif ($acao === 'delete') {

        unset($ips[$ip_alvo]);
    }

    file_put_contents(
        $arquivo,
        json_encode(
            $ips,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
        )
    );

    header("Location: gerenciar_ips.php");

    exit;
}
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>

<meta charset="UTF-8">

<meta name="viewport"
content="width=device-width, initial-scale=1.0">

<title>AMHEEX SECURITY</title>

<link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700&display=swap" rel="stylesheet">

<style>

:root{

    --neon:#00e5ff;
    --red:#ff3355;
    --bg:#050505;
    --card:#111;
    --line:#222;
}

*{
    margin:0;
    padding:0;
    box-sizing:border-box;
}

body{

    background:var(--bg);
    color:#fff;
    font-family:'Orbitron',sans-serif;
    min-height:100vh;
    padding:20px;
}

/* ============================
LOGIN
============================ */

.login-box{

    width:100%;
    max-width:360px;

    background:rgba(15,15,15,0.95);

    margin:auto;
    margin-top:8vh;

    border-radius:22px;

    padding:35px 25px;

    border:1px solid rgba(0,229,255,0.4);

    box-shadow:
    0 0 40px rgba(0,229,255,0.15);

    text-align:center;
}

.logo{

    font-size:26px;
    color:var(--neon);

    margin-bottom:10px;
}

.desc{

    color:#777;
    font-size:11px;

    margin-bottom:25px;
}

.input{

    width:100%;

    background:#000;

    border:1px solid rgba(0,229,255,0.4);

    color:#fff;

    padding:14px;

    border-radius:12px;

    outline:none;

    margin-bottom:15px;
}

.btn{

    width:100%;

    padding:14px;

    border:none;

    border-radius:12px;

    background:var(--neon);

    color:#000;

    font-weight:bold;

    cursor:pointer;

    transition:.2s;
}

.btn:hover{

    transform:scale(1.02);
}

/* ============================
DASHBOARD
============================ */

.dashboard{

    width:100%;
    max-width:1450px;

    margin:auto;

    background:var(--card);

    border-radius:24px;

    overflow:hidden;

    border:1px solid #1c1c1c;

    box-shadow:
    0 0 45px rgba(0,0,0,0.5);
}

.topbar{

    background:#0d0d0d;

    border-bottom:1px solid #1f1f1f;

    padding:22px;

    display:flex;

    justify-content:space-between;

    align-items:center;

    gap:10px;

    flex-wrap:wrap;
}

.title{

    font-size:22px;

    color:var(--neon);
}

.logout{

    text-decoration:none;

    color:var(--red);

    border:1px solid rgba(255,51,85,0.4);

    padding:10px 16px;

    border-radius:10px;

    font-size:12px;
}

/* ============================
CARDS
============================ */

.stats{

    display:grid;

    grid-template-columns:
    repeat(auto-fit,minmax(220px,1fr));

    gap:18px;

    padding:22px;
}

.stat{

    background:#0f0f0f;

    border-radius:18px;

    padding:22px;

    border:1px solid #1c1c1c;
}

.stat .small{

    color:#777;

    font-size:12px;

    margin-bottom:10px;
}

.stat .big{

    font-size:28px;

    color:var(--neon);
}

/* ============================
LISTA IPS
============================ */

.list{

    padding:22px;

    display:grid;

    gap:18px;
}

.card{

    background:#0d0d0d;

    border:1px solid #1f1f1f;

    border-radius:20px;

    padding:20px;
}

.card-top{

    display:flex;

    justify-content:space-between;

    align-items:center;

    gap:10px;

    flex-wrap:wrap;

    margin-bottom:18px;
}

.ip{

    color:var(--neon);

    font-size:22px;

    word-break:break-word;
}

.status{

    padding:8px 14px;

    border-radius:999px;

    font-size:11px;

    font-weight:bold;
}

.on{

    background:rgba(0,255,100,0.15);

    color:#00ff88;
}

.off{

    background:rgba(255,51,85,0.15);

    color:#ff3355;
}

.grid{

    display:grid;

    grid-template-columns:
    repeat(auto-fit,minmax(220px,1fr));

    gap:14px;
}

.info{

    background:#131313;

    border-radius:14px;

    padding:14px;

    border:1px solid #1c1c1c;
}

.label{

    color:#666;

    font-size:11px;

    margin-bottom:8px;
}

.value{

    color:#fff;

    font-size:14px;

    word-break:break-word;

    line-height:1.5;
}

.actions{

    display:flex;

    gap:10px;

    flex-wrap:wrap;

    margin-top:18px;
}

.action{

    text-decoration:none;

    padding:12px 18px;

    border-radius:12px;

    font-size:12px;

    transition:.2s;
}

.toggle{

    border:1px solid rgba(0,229,255,0.3);

    color:var(--neon);
}

.delete{

    border:1px solid rgba(255,51,85,0.3);

    color:var(--red);
}

.action:hover{

    transform:scale(1.03);
}

.empty{

    text-align:center;

    padding:50px;

    color:#666;
}

@media(max-width:700px){

    .ip{
        font-size:18px;
    }

    .title{
        font-size:18px;
    }

}

</style>
</head>

<body>

<?php if (!isset($_SESSION['auth'])): ?>

<div class="login-box">

    <div class="logo">
        AMHEEX SECURITY
    </div>

    <div class="desc">
        SYSTEM ACCESS REQUIRED
    </div>

    <form method="POST">

        <input
        type="text"
        name="key"
        class="input"
        placeholder="MASTER KEY"
        autocomplete="off">

        <button
        type="submit"
        name="login"
        class="btn">
            LOGIN SYSTEM
        </button>

    </form>

    <?php if(isset($erro)): ?>

    <div style="
    margin-top:15px;
    color:#ff3355;
    font-size:12px;">
        <?php echo $erro; ?>
    </div>

    <?php endif; ?>

</div>

<?php else: ?>

<div class="dashboard">

    <div class="topbar">

        <div class="title">
            AMHEEX IP MANAGER
        </div>

        <a href="?logout=1" class="logout">
            SAIR
        </a>

    </div>

    <?php

    $total = count($ips);

    $ativos = 0;
    $bloqueados = 0;

    foreach($ips as $d){

        if(($d["status"] ?? false) === true){
            $ativos++;
        } else {
            $bloqueados++;
        }
    }

    ?>

    <div class="stats">

        <div class="stat">
            <div class="small">
                TOTAL IPS
            </div>

            <div class="big">
                <?php echo $total; ?>
            </div>
        </div>

        <div class="stat">
            <div class="small">
                LIBERADOS
            </div>

            <div class="big">
                <?php echo $ativos; ?>
            </div>
        </div>

        <div class="stat">
            <div class="small">
                BLOQUEADOS
            </div>

            <div class="big">
                <?php echo $bloqueados; ?>
            </div>
        </div>

    </div>

    <div class="list">

    <?php if(empty($ips)): ?>

        <div class="empty">
            NENHUM REGISTRO
        </div>

    <?php else: ?>

        <?php foreach($ips as $ip => $dados): ?>

        <div class="card">

            <div class="card-top">

                <div class="ip">
                    <?php echo htmlspecialchars($ip); ?>
                </div>

                <div class="
                status
                <?php echo ($dados["status"] ?? false)
                ? 'on'
                : 'off'; ?>">
                    ●
                    <?php
                    echo ($dados["status"] ?? false)
                    ? 'LIBERADO'
                    : 'BLOQUEADO';
                    ?>
                </div>

            </div>

            <div class="grid">

                <div class="info">
                    <div class="label">
                        IDENTIFICAÇÃO
                    </div>

                    <div class="value">
                        <?php echo htmlspecialchars($dados["id"]); ?>
                    </div>
                </div>

                <div class="info">
                    <div class="label">
                        IP
                    </div>

                    <div class="value">
                        <?php echo htmlspecialchars($dados["ip"]); ?>
                    </div>
                </div>

                <div class="info">
                    <div class="label">
                        DATA REGISTRO
                    </div>

                    <div class="value">
                        <?php
                        echo htmlspecialchars(
                            $dados["data_registro"]
                        );
                        ?>
                    </div>
                </div>

                <div class="info">
                    <div class="label">
                        ÚLTIMA ATUALIZAÇÃO
                    </div>

                    <div class="value">
                        <?php
                        echo htmlspecialchars(
                            $dados["ultima_atualizacao"]
                        );
                        ?>
                    </div>
                </div>

                <div class="info"
                style="grid-column:1/-1;">

                    <div class="label">
                        ÚLTIMA URL
                    </div>

                    <div class="value">
                        <?php
                        echo htmlspecialchars(
                            $dados["ultima_url"]
                        );
                        ?>
                    </div>

                </div>

            </div>

            <div class="actions">

                <a
                href="?action=toggle&ip=<?php echo urlencode($ip); ?>"
                class="action toggle">

                    <?php
                    echo ($dados["status"] ?? false)
                    ? 'BLOQUEAR'
                    : 'LIBERAR';
                    ?>

                </a>

                <a
                href="?action=delete&ip=<?php echo urlencode($ip); ?>"
                class="action delete"
                onclick="return confirm('DELETAR REGISTRO?')">

                    DELETAR

                </a>

            </div>

        </div>

        <?php endforeach; ?>

    <?php endif; ?>

    </div>

</div>

<?php endif; ?>

</body>
</html>