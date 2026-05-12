const axios = require("axios");
const cron = require("node-cron");

// URL pode ser passada por argumento:
// node app.js https://site.com/api
const targetUrl =
    process.argv[2] ||
    "https://storage-amheex.onrender.com/database.php";

// Timeout configurável por argumento:
// node app.js URL 120000
const timeout =
    parseInt(process.argv[3]) || 60000;

// Log inicial
console.log("⏰ Agendador iniciado!");
console.log("🌐 URL:", targetUrl);
console.log("⏳ Timeout:", timeout, "ms");

// Executa a cada 1 minuto
cron.schedule("*/1 * * * *", async () => {
    try {
        const start = Date.now();

        const response = await axios.get(targetUrl, {
            timeout,
            maxRedirects: 10,
            headers: {
                "User-Agent": "AMHEEX-PING/1.0"
            }
        });

        const ms = Date.now() - start;

        console.log(
            `[${new Date().toISOString()}] ✅ Ping enviado com sucesso (${response.status}) em ${ms}ms`
        );

    } catch (err) {

        if (err.code === "ECONNABORTED") {
            console.error(
                `[${new Date().toISOString()}] ⏳ Timeout excedido após ${timeout}ms`
            );

        } else {
            console.error(
                `[${new Date().toISOString()}] ❌ Erro ao enviar ping: ${err.message}`
            );
        }
    }
});