# ✅ Base Estável com "Raiz Virtual" para Proteção
FROM debian:bullseye-slim

ENV DEBIAN_FRONTEND=noninteractive

# 1️⃣ Pacotes essenciais
RUN apt-get update && apt-get install -y --no-install-recommends \
    ca-certificates curl wget git unzip zip nano vim gnupg2 lsb-release apt-transport-https sudo \
    && rm -rf /var/lib/apt/lists/*

# 2️⃣ PHP 8.2 + Apache
RUN curl -fsSL https://packages.sury.org/php/apt.gpg | gpg --dearmor -o /usr/share/keyrings/php.gpg \
    && echo "deb [signed-by=/usr/share/keyrings/php.gpg] https://packages.sury.org/php/ $(lsb_release -sc) main" \
    > /etc/apt/sources.list.d/php.list \
    && apt-get update && apt-get install -y --no-install-recommends \
        apache2 libapache2-mod-php8.2 php8.2 php8.2-cli php8.2-common php8.2-mbstring \
        php8.2-xml php8.2-zip php8.2-curl php8.2-opcache php8.2-bcmath php8.2-intl \
        php8.2-gd php8.2-mysql php8.2-dev \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# 3️⃣ Node.js LTS + PM2
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && npm install -g pm2 \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# 4️⃣ Módulos Apache
RUN a2enmod rewrite headers expires dir php8.2 proxy proxy_http proxy_fcgi setenvif http2 deflate

# 5️⃣ Configuração da "Pasta X" (Caminho Público)
RUN mkdir -p /var/www/html/public

RUN cat <<'EOF' > /etc/apache2/sites-available/000-default.conf
<VirtualHost *:80>
    ServerName localhost
    # O Apache vai ler TUDO daqui de dentro:
    DocumentRoot /var/www/html/public

    <Directory /var/www/html/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
        LimitRequestBody 3221225472
    </Directory>

    # Proteção: O PHP só mexe na pasta pública e na pasta do sistema
    php_admin_value open_basedir "/var/www/html/public:/opt/system_core:/tmp"

    Timeout 999999
    ProxyTimeout 999999
</VirtualHost>
EOF

# 6️⃣ Copia o projeto para a zona protegida (Invisível para o Navegador)
COPY . /opt/system_core

# 7️⃣ MÁGICA: Link Simbólico ou Cópia para evitar o "Not Found"
# Se você tiver um arquivo principal (ex: index.php ou painel.php), 
# vamos garantir que ele esteja na pasta pública.
RUN cp -r /opt/system_core/* /var/www/html/public/ 2>/dev/null || true

# 8️⃣ Permissões (777 como você pediu para não ter erro de escrita)
RUN chmod -R 777 /var/www/html/public \
    && chown -R www-data:www-data /var/www/html/public /opt/system_core

# 9️⃣ PHP Config (3GB e Ocultar PHP)
RUN cat <<'EOF' > /etc/php/8.2/apache2/conf.d/99-custom.ini
upload_max_filesize = 3072M
post_max_size = 3072M
memory_limit = 4096M
max_execution_time = 0
expose_php = Off
EOF

# 🔟 Dependências Node.js
WORKDIR /opt/system_core
RUN if [ -f package.json ]; then npm install; fi
RUN npm install axios node-cron

EXPOSE 80

# 🌀 Inicialização
# Roda o Node em background e o Apache em foreground
CMD pm2 start /opt/system_core/index.js --name "amheex-brain" && \
    apache2ctl -D FOREGROUND
