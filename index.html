<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Documentação Database API</title>
    <link rel="icon" href="https://api-databaseamheex1.onrender.com/icon.png" type="image/jpeg">
    <link rel="apple-touch-icon" href="https://api-databaseamheex1.onrender.com/icon.png">
    <style>
        :root {
            --primary: #e63946;
            --primary-dark: #c1121f;
            --card: rgba(255, 255, 255, 0.92);
            --text: #1a1a2e;
            --muted: #555;
            --border: rgba(0,0,0,0.08);
            --success: #2a9d8f;
            --warning: #e9c46a;
            --code-bg: #1e1e1e;
            --code-text: #d4d4d4;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            color: var(--text);
            line-height: 1.6;
            min-height: 100vh;
            background: linear-gradient(180deg, #ffffff 0%, #f5f5f5 50%, #eeeeee 100%);
            padding: 0;
        }

        .container {
            width: 100%;
            max-width: 960px;
            margin: 0 auto;
            padding: 0 16px 60px;
        }

        /* ========== HEADER ========== */
        header {
            background: linear-gradient(135deg, #d8f3dc 0%, #b7e4c7 50%, #95d5b2 100%);
            color: #1b4332;
            padding: clamp(28px, 6vw, 48px) 20px;
            margin: 0 0 32px;
            text-align: center;
            border-bottom: 4px solid transparent;
            border-image: linear-gradient(90deg, #ff0000, #00ff00, #0000ff, #ff00ff) 1;
            position: relative;
            overflow: hidden;
        }

        header::after {
            content: "";
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #ff0000, #00ff00, #0000ff, #ff00ff, #ff0000);
            background-size: 300% 100%;
            animation: rgbBar 6s linear infinite;
        }

        @keyframes rgbBar {
            0%   { background-position: 0% 0%; }
            100% { background-position: 300% 0%; }
        }

        .header-icon {
            width: clamp(56px, 12vw, 80px);
            height: clamp(56px, 12vw, 80px);
            border-radius: 16px;
            object-fit: cover;
            margin: 0 auto 16px;
            display: block;
            box-shadow: 0 4px 20px rgba(0,0,0,0.35);
            border: 3px solid rgba(255,255,255,0.25);
            transition: transform 0.25s ease, box-shadow 0.25s ease;
        }

        .header-icon:hover {
            transform: scale(1.08);
            box-shadow: 0 6px 28px rgba(0,0,0,0.45);
        }

        header h1 {
            font-size: clamp(1.4rem, 5vw, 2rem);
            font-weight: 700;
            margin-bottom: 8px;
            letter-spacing: -0.5px;
        }

        header p {
            opacity: 0.9;
            font-size: clamp(0.95rem, 2.5vw, 1.1rem);
        }

        .api-domain {
            display: inline-block;
            margin-top: 14px;
            background: rgba(255,255,255,0.55);
            border: 1px solid rgba(27,67,50,0.2);
            color: #1b4332;
            font-family: "SF Mono", Consolas, monospace;
            font-size: clamp(0.8rem, 2.2vw, 0.95rem);
            padding: 8px 16px;
            border-radius: 8px;
            word-break: break-all;
        }

        .badge {
            display: inline-block;
            background: linear-gradient(90deg, #2d6a4f, #40916c);
            color: white;
            font-size: clamp(0.7rem, 2vw, 0.8rem);
            font-weight: 600;
            padding: 6px 14px;
            border-radius: 20px;
            margin-top: 14px;
            letter-spacing: 0.4px;
        }

        /* ========== TIPOGRAFIA ========== */
        h2 {
            font-size: clamp(1.2rem, 3.5vw, 1.5rem);
            margin: 36px 0 16px;
            color: #1a1a2e;
            border-left: 5px solid transparent;
            border-image: linear-gradient(180deg, #ff0000, #00ff00, #0000ff) 1;
            padding-left: 14px;
        }

        h3 {
            font-size: clamp(1.05rem, 3vw, 1.2rem);
            margin: 24px 0 12px;
            color: #222;
        }

        p, li {
            margin-bottom: 10px;
            font-size: clamp(0.92rem, 2.5vw, 1rem);
        }

        /* ========== CARDS ========== */
        .card {
            background: var(--card);
            backdrop-filter: blur(8px);
            border-radius: 14px;
            padding: clamp(16px, 4vw, 24px);
            margin: 18px 0;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            border: 1px solid var(--border);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 28px rgba(0,0,0,0.12);
        }

        .card.highlight {
            border-left: 5px solid #e63946;
        }

        .card.success {
            border-left: 5px solid var(--success);
        }

        .card.warning {
            border-left: 5px solid #f4a261;
        }

        .grid-2 {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 18px;
            margin: 22px 0;
        }

        /* ========== PATH BOXES ========== */
        .path-box {
            background: #f1f3f5;
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 12px 16px;
            font-family: "SF Mono", "Fira Code", Consolas, monospace;
            font-size: clamp(0.8rem, 2.2vw, 0.95rem);
            word-break: break-all;
            margin: 12px 0;
        }

        .path-box.leitura {
            background: #e8f5e9;
            border-color: #a5d6a7;
            color: #1b5e20;
        }

        .path-box.download {
            background: #fff3e0;
            border-color: #ffcc80;
            color: #e65100;
        }

        /* ========== CÓDIGO ========== */
        code {
            background: #f1f3f5;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 0.9em;
            font-family: "SF Mono", Consolas, monospace;
        }

        pre {
            background: var(--code-bg);
            color: var(--code-text);
            padding: clamp(14px, 3vw, 18px);
            border-radius: 10px;
            overflow-x: auto;
            font-size: clamp(0.78rem, 2vw, 0.88rem);
            line-height: 1.5;
            margin: 14px 0;
            -webkit-overflow-scrolling: touch;
        }

        pre .comment { color: #6a9955; }
        pre .string  { color: #ce9178; }
        pre .keyword { color: #569cd6; }

        /* ========== TABELA ========== */
        .table-wrap {
            overflow-x: auto;
            margin: 18px 0;
            border-radius: 10px;
            -webkit-overflow-scrolling: touch;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: clamp(0.85rem, 2.2vw, 0.95rem);
            min-width: 520px;
        }

        th, td {
            padding: 12px 14px;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }

        th {
            background: #f1f3f5;
            font-weight: 600;
            color: #333;
            white-space: nowrap;
        }

        tr:hover td {
            background: rgba(0,0,0,0.02);
        }

        /* ========== ALERTAS ========== */
        .alert {
            padding: 14px 16px;
            border-radius: 8px;
            margin: 14px 0;
            font-size: clamp(0.88rem, 2.3vw, 0.95rem);
        }

        .alert-info {
            background: #e3f2fd;
            border-left: 4px solid #1976d2;
            color: #0d47a1;
        }

        .alert-success {
            background: #e8f5e9;
            border-left: 4px solid #2e7d32;
            color: #1b5e20;
        }

        .alert-warning {
            background: #fff8e1;
            border-left: 4px solid #f9a825;
            color: #e65100;
        }

        /* ========== TAGS ========== */
        .tag {
            display: inline-block;
            font-size: 0.72rem;
            font-weight: 600;
            padding: 3px 8px;
            border-radius: 4px;
            margin-right: 6px;
            white-space: nowrap;
        }

        .tag-get    { background: #e3f2fd; color: #1565c0; }
        .tag-post   { background: #fce4ec; color: #c2185b; }
        .tag-put    { background: #fff3e0; color: #ef6c00; }
        .tag-delete { background: #ffebee; color: #c62828; }

        ul {
            padding-left: 22px;
            margin-bottom: 14px;
        }

        /* ========== FOOTER ========== */
        footer {
            text-align: center;
            margin-top: 50px;
            padding: 24px 16px 10px;
            border-top: 1px solid rgba(0,0,0,0.1);
            color: var(--muted);
            font-size: clamp(0.8rem, 2vw, 0.9rem);
        }

        footer .api-footer {
            display: block;
            margin-top: 8px;
            font-family: "SF Mono", Consolas, monospace;
            color: #0e7490;
            word-break: break-all;
        }

        @media (max-width: 600px) {
            header {
                margin-left: -16px;
                margin-right: -16px;
                border-radius: 0;
            }
            .card {
                border-radius: 10px;
            }
        }
    </style>
</head>
<body>
    <header>
        <a href="https://api-databaseamheex1.onrender.com" target="_blank" rel="noopener" title="Abrir API Database">
            <img src="https://api-databaseamheex1.onrender.com/icon.png" alt="Ícone Database" class="header-icon">
        </a>
        <h1>Documentação Database API</h1>
        <p>API Estilo Firebase Local (Mapeamento Automático de JSON em Árvore de Diretórios)</p>
        <div class="api-domain">https://api-databaseamheex1.onrender.com</div>
        <div class="badge">GET (Ler/Árvore) &nbsp;|&nbsp; POST / PUT (Mapeamento JSON) &nbsp;|&nbsp; DELETE (Remover)</div>
    </header>

    <div class="container">

        <!-- RESUMO RÁPIDO -->
        <h2>Resumo Rápido (Métodos HTTP)</h2>

        <div class="grid-2">
            <div class="card highlight">
                <h3 style="margin-top:0; color:#c1121f;">Método GET (Leitura e Árvore)</h3>
                <p>Lê um arquivo JSON específico ou varre recursivamente pastas para reconstruir a árvore completa de dados (como o Firebase Realtime Database).</p>
                <div class="path-box leitura">
                    GET https://api-databaseamheex1.onrender.com/usuario
                </div>
                <p style="margin-bottom:0; font-size:0.9rem; color:var(--muted);">
                    Se o nó não existir, retorna <code>null</code>.
                </p>
            </div>

            <div class="card warning">
                <h3 style="margin-top:0; color:#e65100;">Método POST / PUT (Gravação Estruturada)</h3>
                <p>Recebe um objeto JSON e converte automaticamente cada chave em uma subpasta e cada valor em um arquivo <code>.json</code> correspondente.</p>
                <div class="path-box download">
                    POST/PUT https://api-databaseamheex1.onrender.com/usuario
                </div>
            </div>
        </div>

        <div class="alert alert-info">
            <strong>Mapeamento Automático de Chaves:</strong><br>
            • Enviar um objeto JSON cria pastas para subníveis e arquivos <code>.json</code> para valores finais.<br>
            • Permite a coexistência de arquivos diretos (ex: <code>usuario.json</code>) e subpastas estruturadas.
        </div>

        <!-- MÉTODOS DETALHADOS -->
        <h2>Operações da API</h2>

        <div class="card">
            <h3>1. GET - Ler dados ou Árvore</h3>
            <p>Retorna o conteúdo do arquivo JSON ou monta a árvore completa caso seja uma pasta.</p>
            <pre><span class="keyword">fetch</span>(<span class="string">"https://api-databaseamheex1.onrender.com/usuario"</span>)
  .then(res => res.json())
  .then(data => console.log(data));</pre>
        </div>

        <div class="card">
            <h3>2. POST / PUT - Salvar e Criar Subpastas/Arquivos Automaticamente</h3>
            <p>Envia dados estruturados para serem mapeados na árvore do servidor.</p>
            <pre><span class="keyword">fetch</span>(<span class="string">"https://api-databaseamheex1.onrender.com/usuario"</span>, {
  method: <span class="string">"POST"</span>,
  headers: { <span class="string">"Content-Type"</span>: <span class="string">"application/json"</span> },
  body: <span class="keyword">JSON.stringify</span>({
    "0": { "nome": "João", "status": "ativo" },
    "1": { "nome": "Maria", "status": "inativo" }
  })
})
.then(res => res.json())
.then(data => console.log(<span class="string">"Estrutura criada:"</span>, data));</pre>
        </div>

        <div class="card">
            <h3>3. DELETE - Remover Nó ou Pasta</h3>
            <p>Apaga o arquivo JSON correspondente ou remove a pasta inteira do servidor.</p>
            <pre><span class="keyword">fetch</span>(<span class="string">"https://api-databaseamheex1.onrender.com/usuario/0"</span>, {
  method: <span class="string">"DELETE"</span>
})
.then(res => console.log(<span class="string">"Removido com sucesso"</span>));</pre>
        </div>

        <!-- TABELA -->
        <h2>Tabela de Referência de Métodos</h2>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Método</th>
                        <th>Ação</th>
                        <th>Exemplo de Endpoint</th>
                        <th>Descrição</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><span class="tag tag-get">GET</span></td>
                        <td>Leitura / Árvore</td>
                        <td><code>/usuario</code></td>
                        <td>Retorna o JSON salvo ou monta a árvore de diretórios</td>
                    </tr>
                    <tr>
                        <td><span class="tag tag-post">POST</span></td>
                        <td>Gravação Recursiva</td>
                        <td><code>/usuario</code></td>
                        <td>Cria subpastas e arquivos <code>.json</code> para cada chave do objeto</td>
                    </tr>
                    <tr>
                        <td><span class="tag tag-put">PUT</span></td>
                        <td>Atualização</td>
                        <td><code>/usuario</code></td>
                        <td>Atualiza ou insere dados na árvore de nós</td>
                    </tr>
                    <tr>
                        <td><span class="tag tag-delete">DELETE</span></td>
                        <td>Exclusão</td>
                        <td><code>/usuario/0</code></td>
                        <td>Remove o arquivo ou diretório correspondente</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <footer>
            Documentação Database API · Integrado com a infraestrutura AMHEEX<br>
            Base URL: <span class="api-footer">https://api-databaseamheex1.onrender.com</span>
        </footer>
    </div>
</body>
</html>
