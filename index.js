const axios = require("axios");
const cron = require("node-cron");

// Log inicial para confirmar que o script foi iniciado
console.log("⏰ Agendador iniciado! Enviando ping a cada 10 minutos...");

// Agenda a tarefa: a cada 10 minutos
cron.schedule("*/10 * * * *", async () => {
  try {
    const response = await axios.get("https://storage-amheex.onrender.com/2database.php", {
      timeout: 10000, // evita ficar travado se o servidor não responder
    });
    console.log(`[${new Date().toISOString()}] ✅ Ping enviado com sucesso (${response.status})`);
  } catch (err) {
    console.error(`[${new Date().toISOString()}] ❌ Erro ao enviar ping:`, err.message);
  }
});
