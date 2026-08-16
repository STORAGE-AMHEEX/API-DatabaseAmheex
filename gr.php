<?php
/**
 * Advanced File Manager - Edition 2026
 * Security: Key Protected
 */

session_start();
$KEY = "AMHEEX"; // Sua chave de acesso

// Sistema de Login
if (isset($_GET['logout'])) { session_destroy(); header("Location: ?"); exit; }
if (isset($_POST['k'])) { if ($_POST['k'] === $KEY) { $_SESSION['auth'] = true; } else { $err = "Chave Inválida!"; } }
if (!isset($_SESSION['auth'])) {
    die("<!DOCTYPE html><html><head><meta name='viewport' content='width=device-width,initial-scale=1'><style>
    body{background:#0f172a;color:#fff;font-family:sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;margin:0}
    .l{background:#1e293b;padding:30px;border-radius:16px;box-shadow:0 10px 25px rgba(0,0,0,0.5);width:90%;max-width:350px;text-align:center}
    input{width:100%;padding:12px;margin:15px 0;border-radius:8px;border:1px solid #334155;background:#0f172a;color:#fff;text-align:center;box-sizing:border-box}
    button{background:#3b82f6;color:#fff;border:none;padding:12px 20px;border-radius:8px;cursor:pointer;width:100%;font-weight:bold}
    </style></head><body><form class='l' method='POST'><h2>🔐 Acesso</h2><input type='password' name='k' placeholder='Digite a Key' autofocus><button type='submit'>Entrar</button>".(isset($err)?"<p style='color:#ef4444'>$err</p>":"")."</form></body></html>");
}

$BASE = realpath(__DIR__);
$P = isset($_GET['path']) ? realpath($_GET['path']) : $BASE;
if (!$P || strpos($P, $BASE) !== 0) $P = $BASE;

// Funções de Gerenciamento
function rrmdir($d){foreach(scandir($d)as$i){if($i==='.'||$i==='..')continue;$c=$d.'/'.$i;is_dir($c)?rrmdir($c):unlink($c);}rmdir($d);}

// Ações (Criar, Renomear, Deletar, Salvar)
if(isset($_POST['nf'])){ file_put_contents($P.'/'.basename($_POST['nf']), ''); header("Location:?path=".urlencode($P)); exit; }
if(isset($_POST['nd'])){ mkdir($P.'/'.basename($_POST['nd']), 0777, true); header("Location:?path=".urlencode($P)); exit; }
if(isset($_FILES['f'])){
    $n = basename($_FILES['f']['name']);
    $dest = $P.'/'.$n;
    if(move_uploaded_file($_FILES['f']['tmp_name'], $dest) && pathinfo($n, PATHINFO_EXTENSION) == 'zip'){
        $z = new ZipArchive; if($z->open($dest)===TRUE){ $z->extractTo($P); $z->close(); unlink($dest); }
    }
    header("Location:?path=".urlencode($P)); exit;
}
if(isset($_POST['rn_o']) && isset($_POST['rn_n'])){ rename($P.'/'.$_POST['rn_o'], $P.'/'.basename($_POST['rn_n'])); header("Location:?path=".urlencode($P)); exit; }
if(isset($_GET['del'])){ $t = realpath($P.'/'.$_GET['del']); if(strpos($t, $BASE)===0) is_dir($t)?rrmdir($t):unlink($t); header("Location:?path=".urlencode($P)); exit; }
if(isset($_GET['dl'])){ $f=realpath($_GET['dl']); if(strpos($f,$BASE)===0 && is_file($f)){ header('Content-Type: application/octet-stream'); header('Content-Disposition: attachment; filename="'.basename($f).'"'); readfile($f); exit; }}
if(isset($_POST['ef'])){ file_put_contents($P.'/'.$_POST['ef'], $_POST['cnt']); header("Location:?path=".urlencode($P)); exit; }

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manager 2026</title>
    <style>
        :root { --bg: #0f172a; --card: #1e293b; --accent: #38bdf8; --text: #f8fafc; --danger: #ef4444; }
        * { box-sizing: border-box; transition: 0.2s; }
        body { font-family: 'Inter', system-ui, sans-serif; background: var(--bg); color: var(--text); margin: 0; padding: 10px; }
        .container { max-width: 1100px; margin: auto; }
        
        /* Header & Breadcrumb */
        header { background: var(--card); padding: 15px; border-radius: 12px; margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }
        .path { font-size: 14px; color: var(--accent); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 70%; }
        
        /* Controls */
        .controls { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 10px; margin-bottom: 15px; }
        .btn { padding: 12px; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; text-align: center; text-decoration: none; color: white; display: flex; align-items: center; justify-content: center; gap: 8px; font-size: 14px; }
        .btn-blue { background: #3b82f6; } .btn-green { background: #10b981; } .btn-orange { background: #f59e0b; } .btn-red { background: var(--danger); }
        
        /* List Table */
        .list-container { background: var(--card); border-radius: 12px; overflow: hidden; }
        .item { display: flex; align-items: center; justify-content: space-between; padding: 12px 15px; border-bottom: 1px solid #334155; }
        .item:hover { background: #334155; }
        .item-info { display: flex; align-items: center; gap: 12px; flex: 1; min-width: 0; }
        .item-name { text-decoration: none; color: var(--text); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .actions { display: flex; gap: 5px; }
        .icon-btn { padding: 8px; border-radius: 6px; border: none; cursor: pointer; color: white; background: #475569; display: flex; }

        /* Modal Editor */
        #m { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.9); z-index: 1000; flex-direction: column; padding: 15px; }
        textarea { width: 100%; flex: 1; background: #010409; color: #7ee787; border: 1px solid var(--accent); border-radius: 8px; padding: 15px; font-family: 'Fira Code', monospace; font-size: 14px; outline: none; margin-bottom: 15px; }

        /* Responsive adjustments */
        @media (max-width: 600px) {
            .item { flex-direction: column; align-items: flex-start; gap: 10px; }
            .actions { width: 100%; justify-content: flex-end; }
        }
    </style>
</head>
<body>

<div class="container">
    <header>
        <div class="path">📂 <?php echo htmlspecialchars($P); ?></div>
        <a href="?logout=1" class="btn btn-red" style="padding: 5px 10px; font-size: 12px;">Sair</a>
    </header>

    <div class="controls">
        <?php if($P !== $BASE): ?>
            <a href="?path=<?php echo urlencode(dirname($P)); ?>" class="btn btn-blue">⬅ Voltar</a>
        <?php endif; ?>
        <button class="btn btn-green" onclick="var n=prompt('Nome do arquivo:');if(n){document.getElementById('nfn').value=n;document.getElementById('nff').submit();}">📄 Novo</button>
        <button class="btn btn-orange" onclick="var n=prompt('Nome da pasta:');if(n){document.getElementById('ndn').value=n;document.getElementById('ndf').submit();}">📁 Pasta</button>
        <button class="btn btn-blue" onclick="document.getElementById('up').click()">📤 Upload</button>
    </div>

    <!-- Forms Escondidos -->
    <form id="nff" method="POST"><input type="hidden" name="nf" id="nfn"></form>
    <form id="ndf" method="POST"><input type="hidden" name="nd" id="ndn"></form>
    <form id="rf" method="POST"><input type="hidden" name="rn_o" id="rno"><input type="hidden" name="rn_n" id="rnn"></form>
    <form id="upf" method="POST" enctype="multipart/form-data"><input type="file" name="f" id="up" style="display:none" onchange="this.form.submit()"></form>

    <div class="list-container">
        <?php
        $items = scandir($P);
        foreach($items as $i) {
            if($i === '.' || $i === '..') continue;
            $p = $P.'/'.$i;
            $isD = is_dir($p);
            $ext = pathinfo($i, PATHINFO_EXTENSION);
            $b64 = $isD ? '' : base64_encode(file_get_contents($p));
            ?>
            <div class="item">
                <div class="item-info">
                    <span><?php echo $isD ? '📁' : '📄'; ?></span>
                    <?php if($isD): ?>
                        <a href="?path=<?php echo urlencode($p); ?>" class="item-name" style="color:var(--accent); font-weight:bold;"><?php echo $i; ?></a>
                    <?php else: ?>
                        <span class="item-name"><?php echo $i; ?></span>
                    <?php endif; ?>
                </div>
                <div class="actions">
                    <?php if(!$isD): ?>
                        <button class="icon-btn" style="background:#6366f1" onclick="edit('<?php echo $i; ?>','<?php echo $b64; ?>')">📝</button>
                    <?php endif; ?>
                    <button class="icon-btn" onclick="rn('<?php echo $i; ?>')">✏️</button>
                    <a href="?path=<?php echo urlencode($P); ?>&del=<?php echo urlencode($i); ?>" class="icon-btn" style="background:var(--danger)" onclick="return confirm('Excluir?')">🗑️</a>
                    <a href="?dl=<?php echo urlencode($p); ?>" class="icon-btn" style="background:#3b82f6">⬇️</a>
                </div>
            </div>
            <?php
        }
        ?>
    </div>
</div>

<div id="m">
    <h2 style="margin:0 0 10px 0">📝 Editando: <span id="edtitle" style="color:var(--accent)"></span></h2>
    <form method="POST" style="height:90%; display:flex; flex-direction:column;">
        <input type="hidden" name="ef" id="ef">
        <textarea name="cnt" id="cnt" spellcheck="false"></textarea>
        <div style="display:flex; gap:10px;">
            <button type="submit" class="btn btn-green" style="flex:2">💾 Salvar Alterações</button>
            <button type="button" class="btn btn-red" style="flex:1" onclick="document.getElementById('m').style.display='none'">Fechar</button>
        </div>
    </form>
</div>

<script>
function edit(f, b64){
    document.getElementById('ef').value = f;
    document.getElementById('edtitle').innerText = f;
    document.getElementById('cnt').value = atob(b64);
    document.getElementById('m').style.display = 'flex';
}
function rn(o){
    var n = prompt('Novo nome para ' + o + ':', o);
    if(n && n !== o){
        document.getElementById('rno').value = o;
        document.getElementById('rnn').value = n;
        document.getElementById('rf').submit();
    }
}
</script>
</body>
</html>
