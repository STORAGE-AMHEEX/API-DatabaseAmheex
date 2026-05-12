const axios = require("axios");
const cron = require("node-cron");

// Log inicial
console.log("⏰ Agendador iniciado! Enviando ping a cada 1 minuto...");

// Executa a cada 1 minuto
cron.schedule("*/1 * * * *", async () => {
    try {
        const response = await axios.get(
            "https://storage-amheex.onrender.com/database.php",
            {
                timeout: 2000
            }
        );

        console.log(
            `[${new Date().toISOString()}] ✅ Ping enviado com sucesso (${response.status})`
        );

    } catch (err) {
        console.error(
            `[${new Date().toISOString()}] ❌ Erro ao enviar ping: ${err.message}`
        );
    }
});