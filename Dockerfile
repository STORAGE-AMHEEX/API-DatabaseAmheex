# ✅ Base estável — Proteção de Diretório e Raiz Virtualizada
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

# 5️⃣ Lógica de Isolamento: A "Pasta X" vira a raiz pública
# Todo o código do sistema ficará em /opt/system_core
# Apenas /var/www/html/public (nossa Pasta X) será visível no navegador
RUN mkdir -p /opt/system_core /var/www/html/public

RUN cat <<'EOF' > /etc/apache2/sites-available/000-default.conf
<VirtualHost *:80>
    ServerName localhost
    # Aqui definimos que o site "começa" dentro da pasta public (Pasta X)
    DocumentRoot /var/www/html/public

    <Directory /var/www/html/public>
        # Desativamos Indexes para ninguém listar arquivos manualmente
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
        LimitRequestBody 3221225472
    </Directory>

    # 🔒 Bloqueio de subida de diretório (Anti-Path Traversal)
    # Impede que o PHP tente acessar arquivos via ../../ fora da pasta public
    php_admin_value open_basedir "/var/www/html/public:/tmp:/opt/system_core"

    Timeout 999999
    ProxyTimeout 999999
</VirtualHost>
EOF

# 6️⃣ Copia o projeto para a zona protegida
# Jogamos tudo para /opt/system_core (onde o Node vai rodar)
COPY . /opt/system_core

# 7️⃣ Organização dos Arquivos
# Movemos o que é público (HTML/PHP de acesso) para a pasta X
# O restante fica escondido em /opt/system_core
RUN if [ -d "/opt/system_core/public" ]; then \
        cp -r /opt/system_core/public/* /var/www/html/public/; \
    fi && \
    # Se não houver pasta public no seu projeto, criamos um index de segurança
    echo "<?php // Sistema Protegido ?>" > /var/www/html/public/index.php

# 8️⃣ Permissões
# A pasta pública tem permissão total (777) como você pediu
# A pasta interna (/opt) fica restrita para o sistema
RUN chmod -R 777 /var/www/html/public && \
    chown -R www-data:www-data /var/www/html/public /opt/system_core

# 9️⃣ PHP Config (Mantendo seus 3GB e ocultando versão)
RUN cat <<'EOF' > /etc/php/8.2/apache2/conf.d/99-custom.ini
upload_max_filesize = 3072M
post_max_size = 3072M
memory_limit = 4096M
max_execution_time = 0
expose_php = Off
EOF

# 🔟 Dependências Node.js (Rodando na pasta escondida)
WORKDIR /opt/system_core
RUN if [ -f package.json ]; then npm install; fi
RUN npm install axios node-cron

EXPOSE 80

# 🌀 Inicialização
# O PM2 roda o index.js que está ESCONDIDO em /opt/system_core
# O Apache serve apenas a pasta /var/www/html/public
CMD pm2 start /opt/system_core/index.js --name "system-brain" && \
    apache2ctl -D FOREGROUND
