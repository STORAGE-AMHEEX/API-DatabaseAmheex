# ✅ Base estável — Upload até 3 GB, sem timeout e permissões totais
FROM debian:bullseye-slim

ENV DEBIAN_FRONTEND=noninteractive
WORKDIR /var/www/html

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

# 5️⃣ Configuração Apache — permissões totais e limite de 3 GB
RUN cat <<'EOF' > /etc/apache2/sites-available/000-default.conf
<VirtualHost *:80>
    ServerName localhost
    DocumentRoot /var/www/html

    <Directory /var/www/html>
        Options +Indexes +FollowSymLinks +ExecCGI +Includes
        AllowOverride All
        DirectoryIndex index.php index.html
        Require all granted
        # 🔒 Limite de upload 3GB (3072MB)
        LimitRequestBody 3221225472
    </Directory>

    # 🕓 Timeouts altos
    Timeout 999999
    ProxyTimeout 999999
    RequestReadTimeout header=0 body=0

    # 🔁 Conexão persistente
    KeepAlive On
    KeepAliveTimeout 999999
    MaxKeepAliveRequests 0

    # ⚡ Desativa buffers
    EnableSendfile Off
    EnableMMAP Off

    # 🔒 Cabeçalhos
    Header always set Connection keep-alive
    Header always set X-Accel-Buffering no
    Header always unset Content-Length

    ErrorLog ${APACHE_LOG_DIR}/error.log
    CustomLog ${APACHE_LOG_DIR}/access.log combined
</VirtualHost>
EOF

# 6️⃣ Ajustes globais Apache
RUN echo "Timeout 999999" >> /etc/apache2/apache2.conf \
    && echo "KeepAlive On" >> /etc/apache2/apache2.conf \
    && echo "MaxKeepAliveRequests 0" >> /etc/apache2/apache2.conf \
    && echo "KeepAliveTimeout 999999" >> /etc/apache2/apache2.conf \
    && echo "RequestReadTimeout header=0 body=0" >> /etc/apache2/apache2.conf \
    && echo "EnableSendfile Off" >> /etc/apache2/apache2.conf \
    && echo "EnableMMAP Off" >> /etc/apache2/apache2.conf

# 7️⃣ Copia o projeto
COPY . /var/www/html

# 8️⃣ Permissões globais
RUN chmod -R 777 /var/www/html \
    && chown -R www-data:www-data /var/www/html \
    && find /var/www/html -type d -exec chmod 777 {} \; \
    && find /var/www/html -type f -exec chmod 666 {} \;

# 9️⃣ Configuração PHP — limite 3 GB
RUN cat <<'EOF' > /etc/php/8.2/apache2/conf.d/99-custom.ini
upload_max_filesize = 3072M
post_max_size = 3072M
memory_limit = 4096M
max_execution_time = 0
max_input_time = -1
max_input_vars = 9999999
file_uploads = On
output_buffering = Off
realpath_cache_size = 4096K
realpath_cache_ttl = 7200
expose_php = Off
session.gc_maxlifetime = 0
implicit_flush = On
zlib.output_compression = Off
EOF

# 🔎 PHP Debug
RUN cat <<'EOF' > /etc/php/8.2/apache2/conf.d/98-debug.ini
display_errors = On
display_startup_errors = On
error_reporting = E_ALL
log_errors = On
error_log = /var/log/php_errors.log
EOF

# 🔟 Dependências Node.js
RUN if [ -f package.json ]; then npm install; fi
RUN npm install axios node-cron

# 🌐 Porta
EXPOSE 80

# 🌀 Inicialização
CMD pm2 start /var/www/html/#/index.js --name "keep-alive" --no-daemon & \
    apache2ctl -D FOREGROUND