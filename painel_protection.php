<?php
session_start();
$arquivo = 'ips.json';
$master_key = "2026"; 

if (isset($_POST['login'])) {
    if ($_POST['key'] === $master_key) {
        $_SESSION['auth'] = true;
    } else {
        $erro = "CHAVE INCORRETA. ACESSO NEGADO.";
    }
}

if (isset($_GET['logout'])) { session_destroy(); header("Location: gerenciar_ips.php"); exit; }

if (!file_exists($arquivo)) { file_put_contents($arquivo, json_encode([], JSON_PRETTY_PRINT)); }
$ips = json_decode(file_get_contents($arquivo), true);

if (isset($_SESSION['auth']) && isset($_GET['action']) && isset($_GET['ip'])) {
    $ip_alvo = $_GET['ip'];
    $acao = $_GET['action'];

    if ($acao === 'toggle' && isset($ips[$ip_alvo])) {
        $ips[$ip_alvo] = !($ips[$ip_alvo]);
    } elseif ($acao === 'delete') {
        unset($ips[$ip_alvo]);
    }

    file_put_contents($arquivo, json_encode($ips, JSON_PRETTY_PRINT));
    header("Location: gerenciar_ips.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CYBER IP MANAGER v2.0</title>
    <style>
        :root { --neon: #00f2ff; --bad: #ff0033; --bg: #050505; }
        body { 
            background: var(--bg); 
            color: #fff; 
            font-family: 'Orbitron', sans-serif; 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            min-height: 100vh; 
            margin: 0; 
            padding: 10px; /* Margem para telas pequenas */
        }
        
        /* Login Flutuante Responsivo */
        .login-box { 
            background: rgba(20,20,20,0.9); 
            padding: 30px; 
            border: 2px solid var(--neon); 
            box-shadow: 0 0 20px var(--neon); 
            border-radius: 15px; 
            text-align: center; 
            width: 100%;
            max-width: 320px; 
            animation: float 3s ease-in-out infinite; 
        }
        @keyframes float { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-10px); } }
        
        input[type="text"] { background: #000; border: 1px solid var(--neon); color: var(--neon); padding: 12px; width: 85%; margin: 15px 0; outline: none; text-align: center; border-radius: 5px; font-family: 'Orbitron'; }
        button { background: var(--neon); color: #000; border: none; padding: 12px 25px; font-weight: bold; cursor: pointer; border-radius: 5px; transition: 0.3s; width: 90%; font-family: 'Orbitron'; }

        /* Dashboard Principal Responsivo */
        .dashboard { width: 100%; max-width: 900px; background: #111; border: 1px solid #333; border-radius: 10px; overflow: hidden; margin: 20px 0; }
        header { background: #1a1a1a; padding: 15px 20px; border-bottom: 2px solid var(--neon); display: flex; justify-content: space-between; align-items: center; }
        header span { font-size: 0.8rem; letter-spacing: 1px; }
        
        /* Tabela Responsiva */
        .table-container { overflow-x: auto; } /* Permite scroll se a tabela for larga */
        table { width: 100%; border-collapse: collapse; min-width: 500px; }
        th { background: #000; padding: 15px; text-align: left; color: var(--neon); text-transform: uppercase; font-size: 11px; }
        td { padding: 15px; border-bottom: 1px solid #222; font-family: monospace; font-size: 14px; }
        
        .status-on { color: #00ff64; text-shadow: 0 0 5px #00ff64; }
        .status-off { color: var(--bad); text-shadow: 0 0 5px var(--bad); }
        
        /* Botões */
        .action-btns { display: flex; gap: 8px; }
        .action-btn { text-decoration: none; padding: 6px 12px; border-radius: 3px; font-size: 10px; border: 1px solid #444; color: #ccc; transition: 0.2s; white-space: nowrap; }
        .btn-toggle:hover { border-color: var(--neon); color: var(--neon); box-shadow: 0 0 5px var(--neon); }
        .btn-delete:hover { border-color: var(--bad); color: var(--bad); box-shadow: 0 0 5px var(--bad); }

        /* Media Query para Celulares pequenos */
        @media (max-width: 600px) {
            header span { font-size: 0.6rem; }
            td, th { padding: 10px 8px; font-size: 12px; }
            .action-btn { padding: 5px 8px; font-size: 9px; }
        }
    </style>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700&display=swap" rel="stylesheet">
</head>
<body>

<?php if (!isset($_SESSION['auth'])): ?>
    <div class="login-box">
        <h2 style="color: var(--neon); margin: 0; font-size: 1.2rem;">SYSTEM KEY</h2>
        <p style="font-size: 10px; color: #666; margin-top: 5px;">ACCESS GRANTED ONLY WITH KEY</p>
        <form method="POST">
            <input type="text" name="key" placeholder="OI123" autocomplete="off">
            <br>
            <button type="submit" name="login">LOGIN_SYSTEM</button>
        </form>
        <?php if(isset($erro)) echo "<p style='color:var(--bad); font-size:11px; margin-top:10px;'>$erro</p>"; ?>
    </div>

<?php else: ?>
    <div class="dashboard">
        <header>
            <span>IP_MANAGER // SECURITY_CORE</span>
            <a href="?logout=1" style="color:var(--bad); text-decoration:none; font-size:10px; border: 1px solid var(--bad); padding: 4px 8px; border-radius: 3px;">[ EXIT ]</a>
        </header>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>LOCAL_IP</th>
                        <th>STATUS</th>
                        <th>ACTIONS</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($ips)): ?>
                        <tr><td colspan="3" style="text-align:center; color:#555;">NO DATA DETECTED</td></tr>
                    <?php else: ?>
                        <?php foreach ($ips as $ip => $status): ?>
                        <tr>
                            <td style="font-size: 14px; color: var(--neon);"><?php echo $ip; ?></td>
                            <td class="<?php echo $status ? 'status-on' : 'status-off'; ?>">
                                ● <?php echo $status ? 'ALLOWED' : 'BLOCKED'; ?>
                            </td>
                            <td>
                                <div class="action-btns">
                                    <a href="?action=toggle&ip=<?php echo $ip; ?>" class="action-btn btn-toggle">SWITCH</a>
                                    <a href="?action=delete&ip=<?php echo $ip; ?>" class="action-btn btn-delete" onclick="return confirm('ERASE RECORD?')">DELETE</a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

</body>
</html>
