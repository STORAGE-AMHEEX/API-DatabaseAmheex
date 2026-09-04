const axios = require("axios");
const http = require("http");
const https = require("https");

// URL por argumento
const targetUrl =
    process.argv[2] ||
    "https://storage-amheex.rf.gd/B.php";

// Timeout de INATIVIDADE
// Só dispara se parar totalmente de receber dados
const timeout =
    parseInt(process.argv[3]) || 1 * 30 * 60 * 1000;

// Delay após finalizar (ajustado para 5 minutos reais)
const restartDelay = 10 * 1000;

console.log("⏰ Sistema iniciado!");
console.log("🌐 URL:", targetUrl);
console.log("⏳ Timeout adaptável:", timeout, "ms");
console.log("🔄 Reinício automático:", restartDelay, "ms");

async function executeRequest() {
    const start = Date.now();

    try {
        console.log(
            `[${new Date().toISOString()}] 🚀 Iniciando requisição...`
        );

        const response = await axios({
            method: "GET",
            url: targetUrl,

            // NÃO usa timeout fixo do axios
            timeout: 0,

            responseType: "stream",

            maxRedirects: 10,

            headers: {
                "User-Agent": "AMHEEX-PING/1.0"
            },

            httpAgent: new http.Agent({
                keepAlive: true
            }),

            httpsAgent: new https.Agent({
                keepAlive: true
            })
        });

        let bytes = 0;
        let lastActivity = Date.now();

        // Monitor de atividade
        const monitor = setInterval(() => {
            const idle = Date.now() - lastActivity;

            // Só timeout se PAROU de receber dados
            if (idle >= timeout) {
                console.log(
                    `[${new Date().toISOString()}] ⏳ Timeout por inatividade (${idle}ms sem dados)`
                );

                response.data.destroy(
                    new Error("Timeout por inatividade")
                );

                clearInterval(monitor);
            }
        }, 1000);

        // Recebendo dados
        response.data.on("data", chunk => {
            bytes += chunk.length;

            // Atualiza atividade
            lastActivity = Date.now();

            console.log(
                `[${new Date().toISOString()}] 📦 Recebendo dados... ${bytes} bytes`
            );
        });

        response.data.on("end", () => {
            clearInterval(monitor);

            const ms = Date.now() - start;

            console.log(
                `[${new Date().toISOString()}] ✅ Download finalizado em ${ms}ms`
            );

            restartProcess();
        });

        response.data.on("error", err => {
            clearInterval(monitor);

            console.error(
                `[${new Date().toISOString()}] ❌ Stream erro: ${err.message}`
            );

            restartProcess();
        });

    } catch (err) {
        console.error(
            `[${new Date().toISOString()}] ❌ Requisição erro: ${err.message}`
        );

        restartProcess();
    }
}

function restartProcess() {
    console.log(
        `[${new Date().toISOString()}] 🔄 Reiniciando imediatamente em ${restartDelay}ms...`
    );

    setTimeout(() => {
        executeRequest();
    }, restartDelay);
}

// Inicializa
executeRequest();
