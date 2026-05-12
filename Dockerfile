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
    && echo "deb [signed-by=/usr/share/keyrings/php.gpg] https://packages.sury.org/php/ $(ls_release -sc) main" \
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

# 5️⃣ Configuração Dinâmica do Apache (CORREÇÃO PARA O RENDER)
# Removemos a porta fixa 80 e configuramos para aceitar a variável $PORT do Render
RUN sed -i 's/Listen 80/Listen ${PORT}/g' /etc/apache2/ports.conf

RUN cat <<'EOF' > /etc/apache2/sites-available/000-default.conf
<VirtualHost *:${PORT}>
    ServerName localhost
    DocumentRoot /var/www/html

    <Directory /var/www/html>
        Options +Indexes +FollowSymLinks +ExecCGI +Includes
        AllowOverride All
        DirectoryIndex index.php index.html
        Require all granted
        LimitRequestBody 3221225472
    </Directory>

    Timeout 999999
    ProxyTimeout 999999
    RequestReadTimeout header=0 body=0
    KeepAlive On
    KeepAliveTimeout 999999
    MaxKeepAliveRequests 0
    EnableSendfile Off
    EnableMMAP Off

    Header always set Connection keep-alive
    Header always set X-Accel-Buffering no
</VirtualHost>
EOF

# 6️⃣ Ajustes globais Apache
RUN echo "Timeout 999999" >> /etc/apache2/apache2.conf \
    && echo "KeepAlive On" >> /etc/apache2/apache2.conf

# 7️⃣ Copia o projeto
COPY . /var/www/html

# 8️⃣ Permissões
RUN chmod -R 777 /var/www/html \
    && chown -R www-data:www-data /var/www/html

# 9️⃣ Configuração PHP
RUN cat <<'EOF' > /etc/php/8.2/apache2/conf.d/99-custom.ini
upload_max_filesize = 3072M
post_max_size = 3072M
memory_limit = 4096M
max_execution_time = 0
max_input_time = -1
file_uploads = On
output_buffering = Off
EOF

# 🔟 Dependências Node.js
RUN if [ -f package.json ]; then npm install; fi
RUN npm install axios node-cron

# 🌐 Porta Dinâmica (O Render vai ignorar o EXPOSE, mas deixamos como referência)
EXPOSE 80

# 🌀 Inicialização Corrigida
# Usamos um script simples para garantir que o Apache use a porta correta antes de iniciar
CMD pm2 start /var/www/html/ㅤ/index.js --name "keep-alive" && apache2ctl -D FOREGROUND
