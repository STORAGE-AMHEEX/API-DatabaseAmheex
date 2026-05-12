# ✅ Base estável — Com Proteção de Arquivos
FROM debian:bullseye-slim

ENV DEBIAN_FRONTEND=noninteractive
# Mudamos o WORKDIR para uma pasta interna que não é a pública
WORKDIR /opt/internal_app 

# 1️⃣ Pacotes essenciais
RUN apt-get update && apt-get install -y --no-install-recommends \
    ca-certificates curl wget git unzip zip nano vim gnupg2 lsb-release apt-transport-https sudo \
    && rm -rf /var/lib/apt/lists/*

# 2️⃣ PHP 8.2 + Apache
RUN curl -fsSL https://packages.sury.org/php/apt.gpg | gpg --dearmor -o /usr/share/keyrings/php.gpg \
    && echo "deb [signed-by=/usr/share/keyrings/php.gpg] https://packages.sury.org/php/ $(lsb_release -sc) main" \
    > /etc/apt/sources.list.d/php.list \
    && apt-get update && apt-get install -y --no-install-recommends \
        apache2 \
        libapache2-mod-php8.2 \
        php8.2 php8.2-cli php8.2-common php8.2-mbstring php8.2-xml php8.2-zip \
        php8.2-curl php8.2-opcache php8.2-bcmath php8.2-intl php8.2-gd php8.2-mysql php8.2-dev \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# 3️⃣ Node.js LTS + PM2
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && npm install -g pm2 \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# 4️⃣ Ativa módulos Apache
RUN a2enmod rewrite headers expires dir php8.2 proxy proxy_http proxy_fcgi setenvif http2 deflate

# 5️⃣ Configuração Apache — Ocultando Listagem e Protegendo Raiz
RUN cat <<'EOF' > /etc/apache2/sites-available/000-default.conf
<VirtualHost *:80>
    ServerName localhost
    # DocumentRoot continua sendo o padrão, mas vamos deixá-lo quase vazio
    DocumentRoot /var/www/html

    <Directory /var/www/html>
        # 🛡️ REMOVIDO +Indexes (Isso impede que vejam a lista de arquivos)
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
        LimitRequestBody 3221225472
    </Directory>

    # 🔒 Proteção extra: Bloqueia acesso direto a arquivos .php sensíveis se estiverem no Root
    <FilesMatch "\.(phps|ini|log|sh|js)$">
        Require all denied
    </FilesMatch>

    Timeout 999999
    ProxyTimeout 999999
    Header always set Connection keep-alive
</VirtualHost>
EOF

# 6️⃣ Ajustes globais Apache (Seus timeouts altos mantidos)
RUN echo "ServerTokens Prod" >> /etc/apache2/apache2.conf && \
    echo "ServerSignature Off" >> /etc/apache2/apache2.conf

# 7️⃣ COPIA O PROJETO PARA FORA DO DOCUMENT ROOT (A MÁGICA ACONTECE AQUI)
# Em vez de /var/www/html, jogamos para /opt/system_files
COPY . /opt/system_files

# 8️⃣ Permissões e Limpeza
# Removemos o que foi copiado por engano para a pasta pública
RUN rm -rf /var/www/html/* && \
    # Criamos apenas um "túnel" para o arquivo principal se necessário, ou deixamos vazio
    # Se o seu sistema roda via Node (PM2), o PHP não precisa estar na pasta pública
    chown -R www-data:www-data /opt/system_files && \
    chmod -R 755 /opt/system_files

# 9️⃣ Configuração PHP (Seus limites de 3GB mantidos)
RUN cat <<'EOF' > /etc/php/8.2/apache2/conf.d/99-custom.ini
upload_max_filesize = 3072M
post_max_size = 3072M
memory_limit = 4096M
max_execution_time = 0
expose_php = Off
EOF

# 🔟 Dependências Node.js (Rodando na pasta oculta)
WORKDIR /opt/system_files
RUN if [ -f package.json ]; then npm install; fi
RUN npm install axios node-cron

EXPOSE 80

# 🌀 Inicialização: PM2 roda os arquivos da pasta OCULTA
# O Apache inicia, mas a pasta /var/www/html estará vazia ou com um index falso.
CMD pm2 start /opt/system_files/index.js --name "keep-alive" && \
    apache2ctl -D FOREGROUND
