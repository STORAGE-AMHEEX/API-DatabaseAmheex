<?php
// =========================================================================
//   GERENCIADOR DE ZIP E EXTRAÇÃO - PASTA #
// =========================================================================
@ini_set('memory_limit', '1024M');
@ini_set('max_execution_time', '300');

define('TARGET_DIR', __DIR__ . '/#');

if (!is_dir(TARGET_DIR)) {
    @mkdir(TARGET_DIR, 0777, true);
}

$mensagem = '';
$tipoMensagem = '';

// AÇÃO 1: DOWNLOAD DO ZIP DA PASTA #
if (isset($_GET['action']) && $_GET['action'] === 'download') {
    if (!is_dir(TARGET_DIR) || count(scandir(TARGET_DIR)) <= 2) {
        die("A pasta # está vazia ou não existe.");
    }

    $zipFilename = sys_get_temp_dir() . '/pasta_hash_' . time() . '.zip';
    $zip = new ZipArchive();

    if ($zip->open($zipFilename, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(TARGET_DIR, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $name => $file) {
            if (!$file->isDir()) {
                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, strlen(TARGET_DIR) + 1);
                $zip->addFile($filePath, $relativePath);
            }
        }
        $zip->close();

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="pasta_hash_backup.zip"');
        header('Content-Length: ' . filesize($zipFilename));
        readfile($zipFilename);
        @unlink($zipFilename);
        exit;
    } else {
        die("Erro ao criar o arquivo ZIP.");
    }
}

// AÇÃO 2: UPLOAD E EXTRAÇÃO DO ZIP PARA A PASTA #
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['zip_file'])) {
    $file = $_FILES['zip_file'];

    if ($file['error'] === UPLOAD_ERR_OK) {
        $zipPath = $file['tmp_name'];
        $zip = new ZipArchive();

        if ($zip->open($zipPath) === TRUE) {
            $zip->extractTo(TARGET_DIR);
            $zip->close();
            $mensagem = "Arquivo ZIP enviado e extraído com sucesso na pasta #!";
            $tipoMensagem = "success";
        } else {
            $mensagem = "Erro ao abrir o arquivo ZIP enviado.";
            $tipoMensagem = "error";
        }
    } else {
        $mensagem = "Erro no upload do arquivo.";
        $tipoMensagem = "error";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciador ZIP - Pasta #</title>
    <style>
        :root {
            --primary: #4f46e5;
            --primary-hover: #4338ca;
            --success: #10b981;
            --bg: #f8fafc;
            --card: #ffffff;
            --text: #1e293b;
            --border: #e2e8f0;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: var(--bg);
            color: var(--text);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            width: 100%;
            max-width: 480px;
            background: var(--card);
            padding: 32px;
            border-radius: 16px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.05);
            border: 1px solid var(--border);
        }

        h2 {
            font-size: 1.3rem;
            margin-bottom: 8px;
            color: #0f172a;
            text-align: center;
        }

        p.subtitle {
            font-size: 0.9rem;
            color: #64748b;
            text-align: center;
            margin-bottom: 28px;
        }

        .card-action {
            background: #f1f5f9;
            border: 2px dashed #cbd5e1;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
            margin-bottom: 20px;
            transition: all 0.2s ease;
        }

        .card-action:hover {
            border-color: var(--primary);
            background: #f8fafc;
        }

        .btn {
            display: block;
            width: 100%;
            padding: 12px 20px;
            font-size: 0.95rem;
            font-weight: 600;
            text-align: center;
            text-decoration: none;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            transition: background 0.2s;
        }

        .btn-download {
            background: var(--primary);
            color: white;
            margin-bottom: 15px;
        }

        .btn-download:hover { background: var(--primary-hover); }

        .btn-upload {
            background: var(--success);
            color: white;
            margin-top: 12px;
        }

        .btn-upload:hover { background: #059669; }

        input[type="file"] {
            display: block;
            width: 100%;
            font-size: 0.85rem;
            color: #64748b;
            margin-bottom: 10px;
        }

        input[type="file"]::file-selector-button {
            background: #e2e8f0;
            border: none;
            padding: 8px 12px;
            border-radius: 6px;
            color: #334155;
            font-weight: 600;
            cursor: pointer;
            margin-right: 10px;
        }

        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 0.9rem;
            margin-bottom: 20px;
            text-align: center;
        }

        .alert.success { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .alert.error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }

        .divider {
            margin: 24px 0;
            height: 1px;
            background: var(--border);
        }
    </style>
</head>
<body>

    <div class="container">
        <h2>Gerenciador da Pasta #</h2>
        <p class="subtitle">Baixe ou envie pacotes ZIP diretamente para o servidor</p>

        <?php if (!empty($mensagem)): ?>
            <div class="alert <?php echo $tipoMensagem; ?>">
                <?php echo htmlspecialchars($mensagem); ?>
            </div>
        <?php endif; ?>

        <!-- BOTÃO DE DOWNLOAD -->
        <a href="?action=download" class="btn btn-download">
            📦 Baixar Pasta # em ZIP
        </a>

        <div class="divider"></div>

        <!-- FORMULÁRIO DE UPLOAD E EXTRAÇÃO -->
        <form action="" method="POST" enctype="multipart/form-data">
            <div class="card-action">
                <label for="zip_file" style="display:block; font-weight:600; font-size:0.9rem; margin-bottom:10px; color:#334155;">
                    Selecione o arquivo ZIP para extrair na pasta #
                </label>
                <input type="file" name="zip_file" id="zip_file" accept=".zip" required>
            </div>
            <button type="submit" class="btn btn-upload">
                📂 Enviar e Extrair no Servidor
            </button>
        </form>
    </div>

</body>
</html>
