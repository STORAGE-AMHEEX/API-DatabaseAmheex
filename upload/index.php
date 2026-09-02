<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AMHEEX - Upload Até 1T</title>
    <link rel="icon" type="image/jpeg" href="https://amheex.web.app/icon.jpg">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;600;700&family=Rajdhani:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>

    <style>
        @keyframes rgb-animation {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        @keyframes rgbGlow {
            0% { border-color: #ff0055; box-shadow: 0 0 25px rgba(255, 0, 85, 0.2); }
            33% { border-color: #00ffcc; box-shadow: 0 0 25px rgba(0, 255, 204, 0.2); }
            66% { border-color: #7000ff; box-shadow: 0 0 25px rgba(112, 0, 255, 0.2); }
            100% { border-color: #ff0055; box-shadow: 0 0 25px rgba(255, 0, 85, 0.2); }
        }

        * {
            box-sizing: border-box;
        }

        html, body {
            margin: 0;
            padding: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
        }

        body {
            font-family: 'Orbitron', sans-serif;
            color: #111;
            background: #ffffff;
            display: flex;
            justify-content: center;
            align-items: center;
            border: 6px solid;
            border-image: linear-gradient(45deg, #ff0055, #00ffcc, #7000ff) 1;
            padding: 10px;
        }

        .upload-container {
            width: 100%;
            max-width: 650px;
            max-height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 0;
            margin: auto;
        }

        .card-upload {
            background: #ffffff;
            border: 2px solid #00ffcc;
            border-radius: 20px;
            padding: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
            animation: rgbGlow 8s infinite ease-in-out;
            width: 100%;
            max-height: 100vh;
            overflow-y: auto;
        }

        .group { margin-bottom: 15px; }

        .label {
            display: block;
            margin-bottom: 8px;
            color: #7000ff;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        .input {
            width: 100%;
            background: #f9f9fb;
            border: 1px solid #dcdce6;
            border-radius: 12px;
            color: #111;
            padding: 12px;
            outline: none;
            font-size: 13px;
            text-align: center;
        }
        .input:focus { border-color: #00ffcc; }

        .fileBox {
            background: #fafafc;
            border: 2px dashed rgba(112, 0, 255, 0.4);
            border-radius: 16px;
            padding: 20px 15px;
            text-align: center;
            cursor: pointer;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.3s ease;
            width: 100%;
        }
        .fileBox:hover { border-color: #ff0055; background: #fdf6f9; }
        .fileBox input { display: none; }

        .upload-icon { width: 38px; height: 38px; fill: #7000ff; }

        .fileBtn { font-size: 12px; font-weight: bold; color: #222; word-break: break-word; }
        .fileHint { font-size: 10px; color: #666; }
        .fileName { font-size: 11px; color: #ff0055; font-weight: bold; display: none; word-break: break-word; }

        .btn-upload {
            width: 100%;
            border: none;
            border-radius: 12px;
            padding: 14px;
            cursor: pointer;
            color: #fff;
            font-weight: bold;
            font-size: 13px;
            background: linear-gradient(135deg, #ff0055, #7000ff, #00ffcc);
            background-size: 200% 200%;
            animation: rgb-animation 4s linear infinite;
            letter-spacing: 1px;
            box-shadow: 0 4px 15px rgba(112, 0, 255, 0.3);
            transition: opacity 0.2s;
        }
        .btn-upload:hover { opacity: 0.9; }

        .progress {
            width: 100%;
            height: 8px;
            background: #eee;
            border-radius: 999px;
            overflow: hidden;
            margin-top: 15px;
            display: none;
            border: 1px solid #dcdce6;
        }

        .progressBar {
            width: 0%;
            height: 100%;
            background: linear-gradient(90deg, #ff0055, #7000ff, #00ffcc);
            background-size: 200% 200%;
            animation: rgb-animation 2s linear infinite;
            transition: width 0.3s ease;
        }

        .status {
            margin-top: 10px;
            color: #7000ff;
            font-size: 11px;
            text-align: center;
            font-weight: 600;
            word-break: break-word;
        }

        .history-section {
            margin-top: 15px;
            border-top: 1px solid rgba(112, 0, 255, 0.2);
            padding-top: 15px;
            width: 100%;
        }

        .history-title {
            font-size: 11px;
            color: #7000ff;
            letter-spacing: 1px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .history-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
            max-height: 150px;
            overflow-y: auto;
            width: 100%;
        }

        .history-item {
            background: #f8f8fa;
            border: 1px solid rgba(112, 0, 255, 0.15);
            border-radius: 10px;
            padding: 8px 10px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            width: 100%;
        }

        .item-left {
            display: flex;
            align-items: center;
            gap: 6px;
            min-width: 0;
            flex: 1;
        }

        .item-name {
            font-size: 11px;
            color: #111;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            width: 100%;
        }

        .item-actions {
            display: flex;
            gap: 4px;
            flex-shrink: 0;
        }
        
        .action-btn {
            background: #fff;
            border: 1px solid #dcdce6;
            border-radius: 6px;
            padding: 4px 8px;
            color: #333;
            cursor: pointer;
            font-size: 9px;
            font-weight: 600;
        }
        .action-btn:hover { background: #7000ff; border-color: #7000ff; color: #fff; }
        
        .empty-history { text-align: center; font-size: 10px; color: #888; padding: 10px 0; }
    </style>
</head>
<body>

<div class="upload-container">
    <div class="card-upload">
        <div style="text-align: center; margin-bottom: 15px;">
            <h2 style="margin: 0; font-size: 18px; color: #7000ff;">AMHEEX STORAGE UPLOAD</h2>
        </div>

        <div class="group">
            <label class="label">PASTA OU PATH (OPCIONAL)</label>
            <input type="text" id="path" class="input" placeholder="Ex: meus_arquivos ou /meus_arquivos">
        </div>
        
        <div class="group">
            <div class="fileBox" id="dropZone" onclick="document.getElementById('fileInput').click()">
                <svg class="upload-icon" viewBox="0 0 24 24">
                    <path d="M19.35 10.04C18.67 6.59 15.64 4 12 4 9.11 4 6.6 5.64 5.35 8.04 2.34 8.36 0 10.91 0 14c0 3.31 2.69 6 6 6h13c2.76 0 5-2.24 5-5 0-2.64-2.05-4.78-4.65-4.96zM14 13v4h-4v-4H7l5-5 5 5h-3z"/>
                </svg>
                <div class="fileBtn">CLIQUE OU SELECIONE ARQUIVOS / .ZIP (ATÉ 1T)</div>
                <div class="fileHint">Envio otimizado por partes e fila ativa</div>
                <input type="file" id="fileInput" multiple>
                <div class="fileName" id="fileName"></div>
            </div>
        </div>
        
        <button class="btn-upload" onclick="iniciarUpload()">INICIAR UPLOAD</button>
        
        <div class="progress" id="progress"><div class="progressBar" id="progressBar"></div></div>
        <div class="status" id="status">Pronto para envio.</div>
        
        <div class="history-section">
            <div class="history-title">
                <span>HISTÓRICO</span>
                <span id="historyCount" style="color: #666; font-size: 10px;">0 arquivo(s)</span>
            </div>
            <div class="history-list" id="historyList">
                <div class="empty-history" id="emptyHistory">Nenhum envio recente.</div>
            </div>
        </div>
    </div>
</div>

<script>
    const API_URL = "https://storage-amheex.onrender.com";
    const fileInput = document.getElementById("fileInput");
    const pathInput = document.getElementById("path");
    const statusBox = document.getElementById("status");
    const progressBar = document.getElementById("progressBar");
    const progress = document.getElementById("progress");
    const fileNameDisplay = document.getElementById("fileName");
    const historyList = document.getElementById("historyList");
    const emptyHistory = document.getElementById("emptyHistory");
    const historyCount = document.getElementById("historyCount");
    let totalArquivos = 0;

    const MAX_FILE_SIZE = 2 * 1024 * 1024 * 1024; // 2 GB
    const CHUNK_SIZE = 5 * 1024 * 1024; // 5MB

    fileInput.addEventListener("change", () => {
        if (fileInput.files.length) {
            for (let f of fileInput.files) {
                if (f.size > MAX_FILE_SIZE) {
                    alert(`O arquivo "${f.name}" excede o limite de 2GB.`);
                    fileInput.value = "";
                    fileNameDisplay.style.display = "none";
                    return;
                }
            }
            fileNameDisplay.innerText = "✓ " + fileInput.files.length + " arquivo(s) selecionado(s)";
            fileNameDisplay.style.display = "block";
        }
    });

    function status(msg) { statusBox.innerText = msg; }

    function adicionarHistorico(nome, url) {
        if (emptyHistory) emptyHistory.style.display = "none";
        totalArquivos++;
        historyCount.innerText = totalArquivos + " arquivo(s)";

        const item = document.createElement("div");
        item.className = "history-item";
        
        item.innerHTML = `
            <div class="item-left">
                <div>📁</div>
                <div class="item-name" title="${nome}">${nome}</div>
            </div>
            <div class="item-actions">
                <button class="action-btn" onclick="copiarUrl(event, '${url}')">Copiar</button>
                <button class="action-btn" onclick="window.open('${url}', '_blank')">Abrir</button>
            </div>
        `;
        historyList.insertBefore(item, historyList.firstChild);
    }

    function copiarUrl(event, url) {
        event.stopPropagation();
        
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(url).then(() => {
                feedbackCopia(event.target);
            }).catch(() => {
                copiarFallback(event.target, url);
            });
        } else {
            copiarFallback(event.target, url);
        }
    }

    function copiarFallback(btn, url) {
        const textarea = document.createElement("textarea");
        textarea.value = url;
        textarea.style.position = "fixed";
        textarea.style.opacity = "0";
        document.body.appendChild(textarea);
        textarea.focus();
        textarea.select();
        try {
            document.execCommand('copy');
            feedbackCopia(btn);
        } catch (err) {
            alert("Não foi possível copiar a URL automaticamente.");
        }
        document.body.removeChild(textarea);
    }

    function feedbackCopia(btn) {
        const originalText = btn.innerText;
        btn.innerText = "Copiado!";
        setTimeout(() => { btn.innerText = originalText; }, 1500);
    }

    async function uploadEmPartes(file, pathFinal) {
        const totalChunks = Math.ceil(file.size / CHUNK_SIZE);
        let finalUrl = `${API_URL}/${pathFinal}`;

        for (let chunkIndex = 0; chunkIndex < totalChunks; chunkIndex++) {
            const start = chunkIndex * CHUNK_SIZE;
            const end = Math.min(start + CHUNK_SIZE, file.size);
            const chunk = file.slice(start, end);

            const percent = Math.round(((chunkIndex + 1) / totalChunks) * 100);
            progressBar.style.width = percent + "%";
            status(`Enviando ${file.name} - ${percent}%`);

            const formData = new FormData();
            formData.append("chunk", chunk);
            formData.append("chunkIndex", chunkIndex);
            formData.append("totalChunks", totalChunks);
            formData.append("fileName", file.name);

            try {
                const res = await fetch(`${API_URL}/chunk/${pathFinal}`, {
                    method: "POST",
                    body: formData
                });
                if (res.ok) {
                    try {
                        const json = await res.json();
                        if (json.url) finalUrl = json.url;
                    } catch(e){}
                }
            } catch (err) {
                const resDirect = await fetch(`${API_URL}/${pathFinal}`, { method: "POST", body: chunk });
                if (resDirect.ok) {
                    const json = await resDirect.json().catch(() => ({}));
                    if (json.url) finalUrl = json.url;
                }
            }
        }
        return finalUrl;
    }

    async function iniciarUpload() {
        const files = fileInput.files;
        if (!files.length) return alert("Selecione um arquivo!");

        let pastaBase = pathInput.value.trim();
        while (pastaBase.startsWith('/')) {
            pastaBase = pastaBase.substring(1);
        }
        while (pastaBase.endsWith('/')) {
            pastaBase = pastaBase.slice(0, -1);
        }

        progress.style.display = "block";
        let fila = [];

        for (let file of files) {
            let safeName = file.name.replace(/[^a-zA-Z0-9.\-_]/g, '_');
            
            if (file.name.endsWith('.zip')) {
                try {
                    const zip = new JSZip();
                    const content = await zip.loadAsync(file);
                    for (let filename of Object.keys(content.files)) {
                        if (!content.files[filename].dir) {
                            let safeSubName = filename.replace(/[^a-zA-Z0-9.\-_/]/g, '_');
                            const blobData = await content.files[filename].async("blob");
                            fila.push({ name: safeSubName, fileObj: new File([blobData], safeSubName) });
                        }
                    }
                } catch(e) {
                    fila.push({ name: safeName, fileObj: file });
                }
            } else {
                fila.push({ name: safeName, fileObj: file });
            }
        }

        for (let i = 0; i < fila.length; i++) {
            status(`Processando fila (${i+1}/${fila.length}): ${fila[i].name}`);
            
            let cleanFileName = fila[i].name.replace(/^\/+/, '');
            let pathFinal = pastaBase ? `${pastaBase}/${cleanFileName}` : cleanFileName;

            try {
                let finalUrl = "";
                if (fila[i].fileObj.size > CHUNK_SIZE) {
                    finalUrl = await uploadEmPartes(fila[i].fileObj, pathFinal);
                } else {
                    progressBar.style.width = "50%";
                    const res = await fetch(`${API_URL}/${pathFinal}`, { method: "POST", body: fila[i].fileObj });
                    progressBar.style.width = "100%";
                    finalUrl = `${API_URL}/${pathFinal}`;
                    try {
                        const json = await res.json();
                        if (json.url) finalUrl = json.url;
                    } catch(e){}
                }
                adicionarHistorico(fila[i].name, finalUrl);
            } catch (err) {
                status(`Erro crítico ao enviar: ${fila[i].name}`);
            }
        }

        progressBar.style.width = "100%";
        status("Concluído!");
        fileInput.value = "";
        fileNameDisplay.style.display = "none";
        setTimeout(() => { progress.style.display = "none"; progressBar.style.width = "0%"; }, 2500);
    }
</script>
</body>
</html>
