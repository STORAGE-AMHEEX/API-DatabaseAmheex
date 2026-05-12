<?php
// 1. Lógica de captura da URL
$script_name = basename($_SERVER['SCRIPT_NAME']);
$uri = $_SERVER['REQUEST_URI'];
$base_path = str_replace($script_name, '', $_SERVER['SCRIPT_NAME']);
$slug = str_replace($base_path, '', $uri);
$slug = trim($slug, '/');
$payload = $slug ? $slug : "default";

// 2. Configuração dos scripts e gatilhos
$config_json = [
    "scripts" => [
        ["file" => "up.php", "trigger_at" => 25,  "label" => "Executando Upload..."],
        ["file" => "ar.php", "trigger_at" => 50,  "label" => "Processando Arquivos..."],
        ["file" => "dw.php", "trigger_at" => 75,  "label" => "Baixando Pacotes..."],
        ["file" => "re.php", "trigger_at" => 100, "label" => "Finalizando Recebimento..."]
    ]
];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Processamento PHP Nativo</title>
    <style>
        :root { --bg: #030712; --accent: #38bdf8; }
        body { 
            background: var(--bg); 
            color: white; 
            font-family: 'Inter', sans-serif; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            height: 100vh; 
            margin: 0; 
        }
        .main-card { 
            background: rgba(17, 24, 39, 0.95); 
            padding: 40px; 
            border-radius: 24px; 
            border: 1px solid rgba(56, 189, 248, 0.2); 
            text-align: center; 
            width: 340px; 
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }
        .progress-container { 
            width: 100%; 
            height: 10px; 
            background: #1e293b; 
            border-radius: 20px; 
            margin: 25px 0; 
            overflow: hidden; 
        }
        .progress-bar { 
            width: 0%; 
            height: 100%; 
            background: var(--accent); 
            box-shadow: 0 0 20px var(--accent); 
            transition: width 0.4s cubic-bezier(0.4, 0, 0.2, 1); 
        }
        .percent-text { font-size: 3.5rem; font-weight: 900; color: var(--accent); margin-bottom: 5px; }
        .status-label { text-transform: uppercase; font-size: 11px; letter-spacing: 2.5px; color: #94a3b8; }
    </style>
</head>
<body>

    <div class="main-card">
        <div class="status-label" id="status-top">Sistema Ativo</div>
        <div class="percent-text" id="pct">0%</div>
        
        <div class="progress-container">
            <div id="bar" class="progress-bar"></div>
        </div>

        <div id="info" style="font-size: 13px; color: #64748b; font-weight: 500;">
            Aguardando sinal...
        </div>
    </div>

    <script>
        const config = <?php echo json_encode($config_json); ?>;
        const payload = "<?php echo addslashes($payload); ?>";
        
        let progress = 0;
        const bar = document.getElementById('bar');
        const pct = document.getElementById('pct');
        const info = document.getElementById('info');
        const fired = new Set();

        const interval = setInterval(() => {
            progress += 1;
            
            // Atualização visual suave
            bar.style.width = progress + "%";
            pct.innerText = progress + "%";

            // Lógica de execução via PHP puro (Fetch)
            config.scripts.forEach(async (item) => {
                if (progress >= item.trigger_at && !fired.has(item.file)) {
                    fired.add(item.file);
                    
                    // Atualiza o rótulo antes da execução
                    info.innerText = item.label;
                    info.style.color = "#38bdf8";

                    try {
                        // O JavaScript chama o arquivo PHP diretamente
                        const response = await fetch(`./${item.file}?id=${encodeURIComponent(payload)}`);
                        if (response.ok) {
                            console.log(`[SUCESSO] ${item.file} executado via PHP.`);
                        }
                    } catch (error) {
                        console.error(`[ERRO] Falha ao executar ${item.file}:`, error);
                    }
                }
            });

            if (progress >= 100) {
                clearInterval(interval);
                document.getElementById('status-top').innerText = "Finalizado";
                document.getElementById('status-top').style.color = "#4ade80";
                info.innerText = "Processamento concluído com sucesso!";
                info.style.color = "#4ade80";
            }
        }, 100); 
    </script>
</body>
</html>
