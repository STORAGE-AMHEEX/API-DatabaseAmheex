 <?php
// Captura o conteÃÂºdo da URL atual
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$slug = trim($uri, '/');
$payload = $slug ?: "default";

// Se receber uma requisiÃÂ§ÃÂ£o de execuÃÂ§ÃÂ£o (Ajax), processa o include e encerra
if (isset($_GET['execute']) && isset($_GET['file'])) {
    $file = $_GET['file'];
    $permitidos = ['ar.php', 'dw.php', 're.php'];
    
    if (in_array($file, $permitidos) && file_exists($file)) {
        $id = $_GET['id'] ?? '';
        // Executa usando a lÃÂ³gica da imagem enviada
        include_once $file; 
        exit("Executado: $file");
    }
    exit("Erro");
}

// ConfiguraÃÂ§ÃÂ£o do JSON de gatilhos
$config_json = [
    "scripts" => [
        ["file" => "ar.php", "trigger_at" => 0,  "label" => "Processando Arquivos..."],
        ["file" => "dw.php", "trigger_at" => 50,  "label" => "Baixando Pacotes..."],
        ["file" => "re.php", "trigger_at" => 100, "label" => "Finalizando Recebimento..."]
    ]
];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Processamento Sincronizado</title>
    <style>
        :root {
            --bg-color: #0f172a;
            --primary-color: #38bdf8;
            --track-color: #1e293b;
            --card-bg: rgba(30, 41, 59, 0.7);
        }

        body {
            background: radial-gradient(circle at center, #1e293b 0%, #0f172a 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            margin: 0;
            font-family: 'Inter', system-ui, sans-serif;
        }

        .container {
            text-align: center;
            padding: 3rem;
            background: var(--card-bg);
            border-radius: 28px;
            backdrop-filter: blur(12px);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.1);
            width: 320px;
        }

        h2 { margin-bottom: 20px; font-weight: 300; letter-spacing: 1px; font-size: 1.2rem; color: #cbd5e1; }

        .progress-ring { position: relative; width: 180px; height: 180px; margin: 0 auto; }
        .progress-ring svg { transform: rotate(-90deg); }
        
        .progress-ring__circle {
            stroke-dasharray: 502.65; /* 2 * PI * 80 */
            stroke-dashoffset: 502.65;
            transition: stroke-dashoffset 0.3s linear;
            stroke-linecap: round;
        }

        .percent-text {
            position: absolute;
            top: 50%; left: 50%;
            transform: translate(-50%, -50%);
            font-size: 2.8rem;
            font-weight: 800;
            color: var(--primary-color);
            text-shadow: 0 0 15px rgba(56, 189, 248, 0.4);
        }

        #info {
            margin-top: 25px;
            font-size: 0.85rem;
            color: #94a3b8;
            min-height: 1.5em;
        }

        .loading-dots:after {
            content: ' .';
            animation: dots 1.5s steps(5, end) infinite;
        }
        @keyframes dots { 0%, 20% { content: '.'; } 40% { content: '..'; } 60% { content: '...'; } 80%, 100% { content: ''; } }
    </style>
</head>
<body>

    <div class="container">
        <h2 id="status-title">Processando<span class="loading-dots"></span></h2>
        
        <div class="progress-ring">
            <svg width="180" height="180">
                <circle stroke="var(--track-color)" stroke-width="10" fill="transparent" r="80" cx="90" cy="90"/>
                <circle id="progress-bar" class="progress-ring__circle" stroke="var(--primary-color)" stroke-width="10" fill="transparent" r="80" cx="90" cy="90"/>
            </svg>
            <div class="percent-text" id="percent-display">0%</div>
        </div>

        <div id="info">Iniciando sistema...</div>
    </div>

    <script>
        const circle = document.getElementById('progress-bar');
        const percentDisplay = document.getElementById('percent-display');
        const info = document.getElementById('info');
        const circumference = 2 * Math.PI * 80;

        const config = <?php echo json_encode($config_json); ?>;
        const payload = "<?php echo addslashes($payload); ?>";
        
        let progress = 0;
        const fired = new Set();

        function updateUI(value) {
            percentDisplay.innerText = value + "%";
            const offset = circumference - (value / 100) * circumference;
            circle.style.strokeDashoffset = offset;
        }

        const interval = setInterval(() => {
            progress++;
            updateUI(progress);

            // Verifica se deve executar um PHP via include_once (Ajax interno)
            config.scripts.forEach(item => {
                if (progress >= item.trigger_at && !fired.has(item.file)) {
                    fired.add(item.file);
                    info.innerText = item.label;

                    // Chama a prÃÂ³pria pÃÂ¡gina passando os parÃÂ¢metros de execuÃÂ§ÃÂ£o
                    fetch(`?execute=true&file=${item.file}&id=${encodeURIComponent(payload)}`)
                        .then(response => response.text())
                        .then(data => console.log(data));
                }
            });

            if (progress >= 100) {
                clearInterval(interval);
                document.getElementById('status-title').innerText = "ConcluÃÂ­do!";
                document.getElementById('status-title').style.color = "#4ade80";
                info.innerText = "Todos os scripts processados.";
            }
        }, 120);
    </script>
</body>
</html>
