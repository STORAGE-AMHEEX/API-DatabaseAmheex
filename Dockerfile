# ============================================================
# BASE
# ============================================================
# Suporte configurado para uploads de até 1 Terabyte
FROM debian:bookworm-slim

ENV DEBIAN_FRONTEND=noninteractive

WORKDIR /var/www/html


# ============================================================
# 1. PACOTES ESSENCIAIS
# ============================================================
RUN apt-get update && apt-get install -y --no-install-recommends \
    ca-certificates \
    curl \
    wget \
    git \
    unzip \
    zip \
    nano \
    vim \
    gnupg2 \
    lsb-release \
    apt-transport-https \
    sudo \
    net-tools \
    && rm -rf /var/lib/apt/lists/*


# ============================================================
# 2. PHP 8.2 + APACHE
# ============================================================
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
    && rm -rf /var/lib/apt/lists/* \
    && rm -f /tmp/*.deb


# ============================================================
# 3. NODE.JS 20 + PM2
# ============================================================
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && npm install -g pm2 \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*


# ============================================================
# 4. MÓDULOS APACHE
# ============================================================
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
    && sed -i \
        's/^Listen 80$/Listen 0.0.0.0:80/' \
        /etc/apache2/ports.conf


# ============================================================
# 5. APACHE
# ============================================================
#
# IMPORTANTE:
#
# Arquivo existente:
#
#   /pasta/arquivo.js
#
# continua sendo servido normalmente.
#
# Arquivo inexistente:
#
#   /pasta1/pasta2/pasta3/qualquer-coisa
#
# é encaminhado para:
#
#   /var/www/html/index.php
#
# O REQUEST_URI original continua disponível no PHP.
#
# ============================================================

RUN cat <<'EOF' > /etc/apache2/sites-available/000-default.conf
<VirtualHost *:80>

    ServerAdmin webmaster@localhost

    DocumentRoot /var/www/html


    # ========================================================
    # DIRETÓRIO PRINCIPAL
    # ========================================================

    <Directory /var/www/html>

        Options -Indexes +FollowSymLinks

        AllowOverride All

        Require all granted

        DirectoryIndex index.php index.html

        LimitRequestBody 0

    </Directory>


    # ========================================================
    # REWRITE / FALLBACK
    # ========================================================
    #
    # Se o caminho NÃO for arquivo físico
    # E NÃO for diretório físico,
    # executa o index.php principal.
    #
    # Funciona também com várias subpastas.
    #
    # ========================================================

    RewriteEngine On

    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d

    RewriteRule ^ index.php [END]


    # ========================================================
    # TIMEOUTS
    # ========================================================

    Timeout 999999

    ProxyTimeout 999999

    RequestReadTimeout header=0 body=0


    # ========================================================
    # KEEP ALIVE
    # ========================================================

    KeepAlive On

    MaxKeepAliveRequests 0

    KeepAliveTimeout 999999


    # ========================================================
    # PERFORMANCE
    # ========================================================

    EnableSendfile Off

    EnableMMAP Off


    # ========================================================
    # HEADERS
    # ========================================================

    Header always set X-Accel-Buffering no


    # ========================================================
    # LOGS
    # ========================================================

    ErrorLog ${APACHE_LOG_DIR}/error.log

    CustomLog ${APACHE_LOG_DIR}/access.log combined

</VirtualHost>
EOF


# ============================================================
# 6. PHP CONFIG
# ============================================================
#
# 1048576M = 1 TB
#
# ============================================================

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


# ============================================================
# 7. COPIA A APLICAÇÃO
# ============================================================

COPY . /var/www/html


# ============================================================
# 8. PERMISSÕES
# ============================================================
#
# Executado DEPOIS do COPY para garantir que os arquivos
# copiados também recebam as permissões.
#
# ============================================================

RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 777 /var/www/html


# ============================================================
# 9. DEPENDÊNCIAS NODE
# ============================================================

RUN if [ -f /var/www/html/package.json ]; then \
        cd /var/www/html && npm install; \
    fi

RUN cd /var/www/html \
    && npm install axios node-cron || true


# ============================================================
# 10. PORTA
# ============================================================

EXPOSE 80


# ============================================================
# 11. INICIALIZAÇÃO
# ============================================================
#
# Apache atende HTTP.
#
# PM2 mantém O.js executando.
#
# ============================================================

RUN cat <<'EOF' > /index.sh
#!/bin/bash

set -e

echo "=========================================="
echo " Iniciando Apache"
echo "=========================================="

service apache2 start


echo "=========================================="
echo " Iniciando PM2"
echo "=========================================="

if [ -f /var/www/html/O.js ]; then

    pm2-runtime start /var/www/html/O.js \
        --name keep-alive

else

    echo "O.js não encontrado."

    # Mantém o container ativo enquanto o Apache estiver rodando.
    tail -f /dev/null

fi
EOF

RUN chmod +x /index.sh


# ============================================================
# 12. START
# ============================================================

CMD ["/index.sh"]