<?php
// 1. Captura o conteúdo da URL atual
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$slug = trim($uri, '/');

// 2. Configuração via JSON (Define a ordem e em qual % cada PHP dispara)
$config_json = '{
    "scripts": [
        {"file": "up.php", "trigger_at": 25, "label": "Iniciando Recebimento..."},
        {"file": "ar.php", "trigger_at": 50, "label": "Baixando pacotes..."},
        {"file": "dw.php", "trigger_at": 75, "label": "Fazendo Upload..."},
        {"file": "re.php", "trigger_at": 100, "label": "Arquivando dados..."}
    ]
}';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Processamento Sincronizado</title>
    <style>
        :root { --bg: #030712; --accent: #38bdf8; }
        body { background: var(--bg); color: white; font-family: 'Inter', sans-serif; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }
        .main-card { background: rgba(17, 24, 39, 0.8); padding: 40px; border-radius: 20px; border: 1px solid rgba(56, 189, 248, 0.2); text-align: center; width: 320px; box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
        .progress-container { width: 100%; height: 8px; background: #1f2937; border-radius: 10px; margin: 25px 0; overflow: hidden; }
        .progress-bar { width: 0%; height: 100%; background: var(--accent); box-shadow: 0 0 15px var(--accent); transition: width 0.2s linear; }
        .percent-text { font-size: 3rem; font-weight: 900; color: var(--accent); margin: 0; }
        .status-label { text-transform: uppercase; font-size: 10px; letter-spacing: 2px; color: #94a3b8; }
        #hidden-loader { display: none; }
    </style>
</head>
<body>

    <div class="main-card">
        <div class="status-label" id="status-top">Processando</div>
        <div class="percent-text" id="pct">0%</div>
        
        <div class="progress-container">
            <div id="bar" class="progress-bar"></div>
        </div>

        <div id="info" style="font-size: 13px; color: #64748b; min-height: 20px;">
            Aguardando...
        </div>
    </div>

    <div id="hidden-loader"></div>

    <script>
        // Transforma o JSON do PHP em objeto JS
        const config = <?php echo $config_json; ?>;
        const payload = "<?php echo $slug; ?>";
        
        let progress = 0;
        const bar = document.getElementById('bar');
        const pct = document.getElementById('pct');
        const info = document.getElementById('info');
        const loader = document.getElementById('hidden-loader');

        // Conjunto para evitar disparar o mesmo arquivo mais de uma vez
        const fired = new Set();

        const interval = setInterval(() => {
            progress += 1;
            
            // Atualiza Visual
            bar.style.width = progress + "%";
            pct.innerText = progress + "%";

            // Verifica no JSON se há um script para a porcentagem atual
            config.scripts.forEach(item => {
                if (progress >= item.trigger_at && !fired.has(item.file)) {
                    fired.add(item.file);
                    
                    // Atualiza o texto na tela
                    info.innerText = item.label;
                    
                    // Dispara o PHP via iframe oculto
                    const ifrm = document.createElement("iframe");
                    ifrm.src = item.file + "/" + payload;
                    loader.appendChild(ifrm);
                    
                    console.log(`Disparado: ${item.file} aos ${progress}%`);
                }
            });

            if (progress >= 100) {
                clearInterval(interval);
                document.getElementById('status-top').innerText = "Finalizado";
                document.getElementById('status-top').style.color = "#4ade80";
                info.innerText = "Todos os processos concluídos!";
                info.style.color = "#4ade80";
            }
        }, 150); // Velocidade: 150ms por 1% (aprox. 15 segundos total)
    </script>
</body>
</html>
