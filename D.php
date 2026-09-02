<?php
// =========================================================================
// PAINEL DE GERENCIAMENTO DE ARQUIVOS (SERVIDOR + FIREBASE)
// =========================================================================
define('BASE_DIR', realpath(__DIR__) . '/X');
$firebaseBaseUrl = 'https://storage-amheex-default-rtdb.firebaseio.com/';
$raizStorage     = 'X';

// Função para apagar do Firebase (suporta arquivos individuais ou nós inteiros de pastas)
function apagarDoFirebase($relativeFilePath, $firebaseBaseUrl, $raizStorage, $isDir = false) {
    if (!$isDir) {
        $base64Path = rtrim(strtr(base64_encode($relativeFilePath), '+/', '-_'), '=');
        $nodePath = $raizStorage . '/' . $base64Path . '.json';
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $firebaseBaseUrl . $nodePath);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_exec($ch);
        curl_close($ch);
    }
}

// Função recursiva para apagar diretório local e arquivos filhos no Firebase
function apagarDiretorioRecursivo($dir, $relativePath, $firebaseBaseUrl, $raizStorage) {
    if (!is_dir($dir)) return;
    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item == '.' || $item == '..') continue;
        $pathLocal = $dir . '/' . $item;
        $pathRel = trim($relativePath . '/' . $item, '/');
        
        if (is_dir($pathLocal)) {
            apagarDiretorioRecursivo($pathLocal, $pathRel, $firebaseBaseUrl, $raizStorage);
            @rmdir($pathLocal);
        } else {
            @unlink($pathLocal);
            apagarDoFirebase($pathRel, $firebaseBaseUrl, $raizStorage, false);
        }
    }
}

// Diretório atual navegável
$caminhoAtualRelativo = isset($_GET['dir']) ? trim($_GET['dir'], '/\\') : '';
$caminhoAtualRelativo = str_replace(['..\\', '../'], '', $caminhoAtualRelativo);
$pastaFisicaAtual = BASE_DIR . ($caminhoAtualRelativo ? '/' . $caminhoAtualRelativo : '');

if (!is_dir($pastaFisicaAtual) || strpos(realpath($pastaFisicaAtual), realpath(BASE_DIR)) !== 0) {
    $pastaFisicaAtual = BASE_DIR;
    $caminhoAtualRelativo = '';
}

// Ação de Exclusão
if (isset($_GET['delete'])) {
    $itemDeletar = trim($_GET['delete'], '/\\');
    $itemDeletar = str_replace(['..\\', '../'], '', $itemDeletar);
    $caminhoCompleto = BASE_DIR . '/' . $itemDeletar;

    if (file_exists($caminhoCompleto)) {
        if (is_dir($caminhoCompleto)) {
            apagarDiretorioRecursivo($caminhoCompleto, $itemDeletar, $firebaseBaseUrl, $raizStorage);
            @rmdir($caminhoCompleto);
        } else {
            @unlink($caminhoCompleto);
            apagarDoFirebase($itemDeletar, $firebaseBaseUrl, $raizStorage, false);
        }
    }
    
    // Redireciona mantendo na pasta pai atual ou raiz
    $redirectDir = dirname($itemDeletar);
    $redirectDir = ($redirectDir === '.' || $redirectDir === '/') ? '' : $redirectDir;
    header("Location: D.php" . ($redirectDir ? "?dir=" . urlencode($redirectDir) : ""));
    exit;
}

// Listar conteúdos da pasta atual
$pastas = [];
$arquivos = [];

if ($handle = opendir($pastaFisicaAtual)) {
    while (($entry = readdir($handle)) !== false) {
        if ($entry == '.' || $entry == '..') continue;
        
        $caminhoCompletoItem = $pastaFisicaAtual . '/' . $entry;
        $caminhoRelativoItem = ($caminhoAtualRelativo ? $caminhoAtualRelativo . '/' : '') . $entry;

        if (is_dir($caminhoCompletoItem)) {
            $pastas[] = [
                'nome' => $entry,
                'caminho' => $caminhoRelativoItem
            ];
        } else {
            $arquivos[] = [
                'nome' => $entry,
                'caminho' => $caminhoRelativoItem,
                'tamanho' => filesize($caminhoCompletoItem),
                'modificacao' => filemtime($caminhoCompletoItem)
            ];
        }
    }
    closedir($handle);
}

usort($pastas, fn($a, $b) => strcasecmp($a['nome'], $b['nome']));
usort($arquivos, fn($a, $b) => strcasecmp($a['nome'], $b['nome']));
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciador de Arquivos do Servidor</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bg-main: #0f172a;
            --bg-card: #1e293b;
            --border-color: #334155;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --accent: #3b82f6;
            --danger: #ef4444;
            --danger-hover: #dc2626;
        }
        body { font-family: 'Segoe UI', system-ui, sans-serif; background: var(--bg-main); color: var(--text-main); margin: 0; padding: 40px 20px; }
        .container { max-width: 1000px; margin: auto; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.4); overflow: hidden; }
        .header { padding: 24px; border-bottom: 1px solid var(--border-color); display: flex; align-items: center; gap: 12px; font-size: 20px; font-weight: 600; }
        .breadcrumb-bar { background: #090d16; padding: 12px 24px; display: flex; align-items: center; gap: 8px; font-size: 14px; border-bottom: 1px solid var(--border-color); overflow-x: auto; }
        .breadcrumb-bar a { color: var(--text-muted); text-decoration: none; }
        .breadcrumb-bar a:hover { color: var(--accent); text-decoration: underline; }
        .breadcrumb-bar span { color: var(--text-main); font-weight: 500; }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th, td { padding: 14px 24px; border-bottom: 1px solid var(--border-color); font-size: 14px; }
        th { background: rgba(255,255,255,0.02); color: var(--text-muted); font-weight: 600; text-transform: uppercase; font-size: 11px; }
        tr:hover td { background: rgba(255,255,255,0.015); }
        .item-icon { width: 30px; text-align: center; }
        .folder-icon { color: #f59e0b; }
        .file-icon { color: #38bdf8; }
        .item-name { color: var(--text-main); text-decoration: none; font-weight: 500; display: inline-flex; align-items: center; gap: 10px; }
        .item-name:hover { color: var(--accent); }
        .btn-action { background: transparent; border: 1px solid var(--border-color); color: var(--text-muted); padding: 6px 10px; border-radius: 6px; cursor: pointer; transition: all 0.2s; font-size: 13px; }
        .btn-delete:hover { background: rgba(239, 68, 68, 0.1); color: var(--danger); border-color: rgba(239, 68, 68, 0.3); }
        
        /* Modal Flutuante Centralizado */
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); backdrop-filter: blur(4px); display: flex; justify-content: center; align-items: center; opacity: 0; visibility: hidden; transition: all 0.25s ease; z-index: 1000; }
        .modal-overlay.active { opacity: 1; visibility: visible; }
        .modal-box { background: var(--bg-card); border: 1px solid var(--border-color); width: 100%; max-width: 400px; border-radius: 12px; padding: 24px; box-shadow: 0 20px 25px rgba(0,0,0,0.5); transform: scale(0.95); transition: transform 0.25s ease; }
        .modal-overlay.active .modal-box { transform: scale(1); }
        .modal-title { margin: 0 0 10px 0; font-size: 18px; font-weight: 600; display: flex; align-items: center; gap: 8px; }
        .modal-desc { color: var(--text-muted); font-size: 14px; margin-bottom: 20px; line-height: 1.5; }
        .modal-buttons { display: flex; justify-content: flex-end; gap: 10px; }
        .btn-modal { padding: 8px 16px; border-radius: 6px; font-size: 14px; font-weight: 500; cursor: pointer; border: none; }
        .btn-cancel { background: transparent; border: 1px solid var(--border-color); color: var(--text-main); }
        .btn-cancel:hover { background: rgba(255,255,255,0.05); }
        .btn-confirm-delete { background: var(--danger); color: white; text-decoration: none; display: inline-flex; align-items: center; }
        .btn-confirm-delete:hover { background: var(--danger-hover); }
        .empty-state { text-align: center; padding: 40px; color: var(--text-muted); }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <i class="fa-solid fa-folder-tree" style="color: var(--accent);"></i> Gerenciador de Servidor & Amheex
        </div>

        <div class="breadcrumb-bar">
            <i class="fa-solid fa-house" style="color: var(--text-muted);"></i>
            <a href="D.php">Raiz (X)</a>
            <?php if (!empty($caminhoAtualRelativo)): 
                $partes = explode('/', $caminhoAtualRelativo);
                $acumulado = '';
                foreach ($partes as $parte):
                    $acumulado .= ($acumulado ? '/' : '') . $parte;
            ?>
                <span>/</span>
                <a href="D.php?dir=<?php echo urlencode($acumulado); ?>"><?php echo htmlspecialchars($parte); ?></a>
            <?php endforeach; endif; ?>
        </div>

        <table>
            <thead>
                <tr>
                    <th style="width: 40px;"></th>
                    <th>Nome</th>
                    <th>Tamanho</th>
                    <th>Modificado em</th>
                    <th style="text-align: right;">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($caminhoAtualRelativo)): 
                    $pai = dirname($caminhoAtualRelativo);
                    $paiUrl = ($pai === '.' || $pai === '/') ? '' : $pai;
                ?>
                    <tr>
                        <td class="item-icon folder-icon"><i class="fa-solid fa-arrow-turn-up"></i></td>
                        <td colspan="4">
                            <a class="item-name" href="D.php<?php echo $paiUrl ? '?dir=' . urlencode($paiUrl) : ''; ?>">.. (Voltar pasta anterior)</a>
                        </td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($pastas as $pasta): ?>
                    <tr>
                        <td class="item-icon folder-icon"><i class="fa-solid fa-folder"></i></td>
                        <td>
                            <a class="item-name" href="D.php?dir=<?php echo urlencode($pasta['caminho']); ?>">
                                <?php echo htmlspecialchars($pasta['nome']); ?>
                            </a>
                        </td>
                        <td>-</td>
                        <td>-</td>
                        <td style="text-align: right;">
                            <button class="btn-action btn-delete" onclick="abrirModalExclusao('<?php echo htmlspecialchars($pasta['caminho']); ?>', true)">
                                <i class="fa-solid fa-trash"></i> Excluir
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php foreach ($arquivos as $arq): ?>
                    <tr>
                        <td class="item-icon file-icon"><i class="fa-solid fa-file"></i></td>
                        <td>
                            <a class="item-name" href="X/<?php echo htmlspecialchars($arq['caminho']); ?>" target="_blank">
                                <?php echo htmlspecialchars($arq['nome']); ?>
                            </a>
                        </td>
                        <td><?php echo number_format($arq['tamanho'] / 1024, 2, ',', '.'); ?> KB</td>
                        <td><?php echo date('d/m/Y H:i', $arq['modificacao']); ?></td>
                        <td style="text-align: right;">
                            <button class="btn-action btn-delete" onclick="abrirModalExclusao('<?php echo htmlspecialchars($arq['caminho']); ?>', false)">
                                <i class="fa-solid fa-trash"></i> Excluir
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php if (empty($pastas) && empty($arquivos)): ?>
                    <tr><td colspan="5" class="empty-state">Esta pasta está vazia.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Modal de Exclusão Centralizado -->
    <div class="modal-overlay" id="deleteModal">
        <div class="modal-box">
            <h3 class="modal-title"><i class="fa-solid fa-triangle-exclamation" style="color: var(--danger);"></i> Confirmar Exclusão</h3>
            <p class="modal-desc" id="modalDescText">Tem certeza de que deseja excluir este item do servidor e do Firebase?</p>
            <div class="modal-buttons">
                <button class="btn-modal btn-cancel" onclick="fecharModal()">Cancelar</button>
                <a id="confirmDeleteBtn" href="#" class="btn-modal btn-confirm-delete">Sim, excluir</a>
            </div>
        </div>
    </div>

    <script>
        const modal = document.getElementById('deleteModal');
        const confirmBtn = document.getElementById('confirmDeleteBtn');
        const descText = document.getElementById('modalDescText');

        function abrirModalExclusao(caminhoItem, isPasta) {
            let tipo = isPasta ? 'esta pasta (e todo o seu conteúdo)' : 'este arquivo';
            descText.innerHTML = `Tem certeza de que deseja apagar permanentemente <b>${caminhoItem}</b> (${tipo}) tanto do servidor local quanto do Firebase?`;
            confirmBtn.href = `D.php?delete=${encodeURIComponent(caminhoItem)}`;
            modal.classList.add('active');
        }

        function fecharModal() {
            modal.classList.remove('active');
        }

        modal.addEventListener('click', (e) => {
            if (e.target === modal) fecharModal();
        });
    </script>
</body>
</html>
