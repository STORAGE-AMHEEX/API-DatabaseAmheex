const axios = require("axios");

// URL por argumento
const targetUrl =
    process.argv[2] ||
    "https://storage-amheex.onrender.com/database.php";

// Timeout por argumento
const timeout =
    parseInt(process.argv[3]) || 60000;

// Delay de reinício após terminar
const restartDelay = 60000; // 60s

console.log("⏰ Sistema iniciado!");
console.log("🌐 URL:", targetUrl);
console.log("⏳ Timeout:", timeout, "ms");
console.log("🔄 Reinício automático:", restartDelay, "ms");

async function executeRequest() {

    const start = Date.now();

    try {

        console.log(
            `[${new Date().toISOString()}] 🚀 Iniciando requisição...`
        );

        const response = await axios.get(targetUrl, {
            timeout,
            maxRedirects: 10,
            headers: {
                "User-Agent": "AMHEEX-PING/1.0"
            }
        });

        const ms = Date.now() - start;

        console.log(
            `[${new Date().toISOString()}] ✅ Finalizado (${response.status}) em ${ms}ms`
        );

    } catch (err) {

        const ms = Date.now() - start;

        if (err.code === "ECONNABORTED") {

            console.error(
                `[${new Date().toISOString()}] ⏳ Timeout após ${ms}ms`
            );

        } else {

            console.error(
                `[${new Date().toISOString()}] ❌ Erro: ${err.message}`
            );
        }
    }

    console.log(
        `[${new Date().toISOString()}] 🔄 Aguardando 60s para reiniciar processo...`
    );

    setTimeout(() => {
        executeRequest();
    }, restartDelay);
}

// Inicializa
executeRequest();