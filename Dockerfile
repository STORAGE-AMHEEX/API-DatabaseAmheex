# ✅ Base estável — Upload até 3 GB, sem timeout e permissões totais
FROM debian:bullseye-slim

ENV DEBIAN_FRONTEND=noninteractive
WORKDIR /var/www/html

# 1️⃣ Pacotes essenciais e dependências de rede
RUN apt-get update && apt-get install -y --no-install-recommends \
    ca-certificates curl wget git unzip zip nano vim gnupg2 lsb-release apt-transport-https sudo net-tools \
    && rm -rf /var/lib/apt/lists/*

# 2️⃣ PHP 8.2 + Apache (Porta 80)
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

# 4️⃣ Ativa módulos Apache e Garante Porta 80
RUN a2enmod rewrite headers expires dir php8.2 proxy proxy_http proxy_fcgi setenvif http2 deflate \
    && sed -i 's/Listen 80/Listen 0.0.0.0:80/' /etc/apache2/ports.conf

# 5️⃣ Configuração Apache — Permissões Totais (000-default.conf)
RUN cat <<'EOF' > /etc/apache2/sites-available/000-default.conf
<VirtualHost *:80>
    ServerAdmin webmaster@localhost
    DocumentRoot /var/www/html

    <Directory /var/www/html>
        Options +Indexes +FollowSymLinks +MultiViews
        AllowOverride All
        Require all granted
        # 🔒 Limite de upload 3GB
        LimitRequestBody 3221225472
    </Directory>

    # 🕓 Timeouts Infinitos para Processos Longos
    Timeout 999999
    ProxyTimeout 999999
    RequestReadTimeout header=0 body=0

    # 🔁 Keep-Alive Total
    KeepAlive On
    MaxKeepAliveRequests 0
    KeepAliveTimeout 999999

    # ⚡ Performance e No-Buffer
    EnableSendfile Off
    EnableMMAP Off
    Header always set Connection keep-alive
    Header always set X-Accel-Buffering no

    ErrorLog ${APACHE_LOG_DIR}/error.log
    CustomLog ${APACHE_LOG_DIR}/access.log combined
</VirtualHost>
EOF

# 6️⃣ PHP Config — Suporte a arquivos gigantes e tempo ilimitado
RUN cat <<'EOF' > /etc/php/8.2/apache2/conf.d/99-custom.ini
upload_max_filesize = 3072M
post_max_size = 3072M
memory_limit = 4096M
max_execution_time = 0
max_input_time = -1
max_input_vars = 9999999
file_uploads = On
output_buffering = Off
zlib.output_compression = Off
implicit_flush = On
EOF

# 7️⃣ Permissões Críticas (Full Access)
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 777 /var/www/html

# 8️⃣ Dependências Node.js (Se houver)
COPY . /var/www/html
RUN if [ -f package.json ]; then npm install; fi
RUN npm install axios node-cron || true

# 🌐 Porta 80 liberada
EXPOSE 80

# 🌀 Script de Inicialização para rodar PM2 e Apache Juntos
RUN echo "#!/bin/bash\nservice apache2 start\npm2-runtime start /var/www/html/ㅤ/index.js --name keep-alive" > /start.sh \
    && chmod +x /start.sh

CMD ["/start.sh"]
