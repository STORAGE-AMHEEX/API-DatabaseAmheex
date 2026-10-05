# ✅ Base modificada para upload de até 1 Terabyte (1048576M)
FROM debian:bookworm-slim

ENV DEBIAN_FRONTEND=noninteractive
WORKDIR /var/www/html

# 1️⃣ Pacotes essenciais
RUN apt-get update && apt-get install -y --no-install-recommends \
    ca-certificates curl wget git unzip zip nano vim gnupg2 lsb-release \
    apt-transport-https sudo net-tools \
    && rm -rf /var/lib/apt/lists/*

# 2️⃣ PHP 8.2 + Apache
RUN curl -sSLo /tmp/debsuryorg-archive-keyring.deb \
    https://packages.sury.org/debsuryorg-archive-keyring.deb \
    && dpkg -i /tmp/debsuryorg-archive-keyring.deb \
    && echo "deb [signed-by=/usr/share/keyrings/deb.sury.org-php.gpg] https://packages.sury.org/php/ $(lsb_release -sc) main" \
    > /etc/apt/sources.list.d/php.list \
    && apt-get update \
    && apt-get install -y --no-install-recommends \
        apache2 \
        libapache2-mod-php8.2 \
        php8.2 \
        php8.2-cli \
        php8.2-common \
        php8.2-mbstring \
        php8.2-xml \
        php8.2-zip \
        php8.2-curl \
        php8.2-opcache \
        php8.2-bcmath \
        php8.2-intl \
        php8.2-gd \
        php8.2-mysql \
        php8.2-dev \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/*.deb

# 3️⃣ Node.js LTS + PM2
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && npm install -g pm2 \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# 4️⃣ Módulos Apache
RUN a2enmod \
        rewrite \
        headers \
        expires \
        dir \
        php8.2 \
        proxy \
        proxy_http \
        proxy_fcgi \
        setenvif \
        http2 \
        deflate \
    && sed -i 's/^Listen 80$/Listen 0.0.0.0:80/' /etc/apache2/ports.conf

# 5️⃣ Apache configurado para aceitar e processar .htaccess
RUN cat <<'EOF' > /etc/apache2/sites-available/000-default.conf
<VirtualHost *:80>

    ServerAdmin webmaster@localhost

    DocumentRoot /var/www/html

    # ============================================================
    # .HTACCESS ATIVO
    # ============================================================
    <Directory /var/www/html>

        Options -Indexes +FollowSymLinks +MultiViews

        DirectoryIndex index.php index.html

        # Permite que o .htaccess controle o diretório
        AllowOverride All

        # Permite acesso aos arquivos
        Require all granted

        # Sem limite de tamanho da requisição
        LimitRequestBody 0

    </Directory>

    # Nome padrão do arquivo de configuração por diretório
    AccessFileName .htaccess

    # ============================================================
    # TIMEOUTS
    # ============================================================
    Timeout 999999
    ProxyTimeout 999999

    RequestReadTimeout header=0 body=0

    KeepAlive On
    MaxKeepAliveRequests 0
    KeepAliveTimeout 999999

    EnableSendfile Off
    EnableMMAP Off

    Header always set Connection keep-alive
    Header always set X-Accel-Buffering no

    ErrorLog ${APACHE_LOG_DIR}/error.log
    CustomLog ${APACHE_LOG_DIR}/access.log combined

</VirtualHost>
EOF

# 6️⃣ PHP — Upload de até 1TB
RUN cat <<'EOF' > /etc/php/8.2/apache2/conf.d/99-custom.ini
upload_max_filesize = 1048576M
post_max_size = 1048576M
memory_limit = 8192M

max_execution_time = 0
max_input_time = -1
max_input_vars = 9999999

file_uploads = On

output_buffering = Off
zlib.output_compression = Off
implicit_flush = On
EOF

# 7️⃣ Copia o projeto
COPY . /var/www/html

# 8️⃣ Garante que o .htaccess seja preservado
RUN if [ -f /var/www/html/.htaccess ]; then \
        chmod 644 /var/www/html/.htaccess; \
    fi

# 9️⃣ Permissões
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 777 /var/www/html

# 🔟 Dependências Node.js
RUN if [ -f /var/www/html/package.json ]; then \
        cd /var/www/html && npm install; \
    fi

RUN cd /var/www/html \
    && npm install axios node-cron || true

# 1️⃣1️⃣ Verifica a configuração do Apache durante o build
RUN apache2ctl configtest

# 🌐 Porta 80
EXPOSE 80

# 🌀 Inicialização
RUN cat <<'EOF' > /index.sh
#!/bin/bash

set -e

# Garante que o Apache reconheça .htaccess
a2enmod rewrite >/dev/null 2>&1 || true

# Valida Apache
apache2ctl configtest

# Inicia Apache em foreground
exec apache2ctl -D FOREGROUND
EOF

RUN chmod +x /index.sh

CMD ["/index.sh"]