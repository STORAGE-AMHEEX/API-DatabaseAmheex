# ✅ Base estável
FROM debian:bullseye-slim

ENV DEBIAN_FRONTEND=noninteractive
WORKDIR /var/www/html

# 1️⃣ Pacotes essenciais
RUN apt-get update && apt-get install -y --no-install-recommends \
    ca-certificates curl wget git unzip zip gnupg2 lsb-release apt-transport-https sudo \
    && rm -rf /var/lib/apt/lists/*

# 2️⃣ PHP 8.2 + Apache
RUN curl -fsSL https://packages.sury.org/php/apt.gpg | gpg --dearmor -o /usr/share/keyrings/php.gpg \
    && echo "deb [signed-by=/usr/share/keyrings/php.gpg] https://packages.sury.org/php/ $(lsb_release -sc) main" \
    > /etc/apt/sources.list.d/php.list \
    && apt-get update && apt-get install -y --no-install-recommends \
        apache2 libapache2-mod-php8.2 php8.2 php8.2-cli php8.2-mbstring php8.2-xml php8.2-zip \
        php8.2-curl php8.2-mysql \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# 3️⃣ Node.js LTS + PM2
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs \
    && npm install -g pm2 \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# 4️⃣ Ativa módulos Apache
RUN a2enmod rewrite headers proxy proxy_http

# 5️⃣ Configuração da Porta do Render (Ajuste direto no ports.conf)
RUN sed -i 's/Listen 80/Listen ${PORT}/g' /etc/apache2/ports.conf

# 6️⃣ Configuração do VirtualHost (Usando printf para evitar erro de 'cat <<EOF')
RUN printf "<VirtualHost *:\${PORT}>\n\
    DocumentRoot /var/www/html\n\
    <Directory /var/www/html>\n\
        AllowOverride All\n\
        Require all granted\n\
    </Directory>\n\
    Timeout 999999\n\
</VirtualHost>" > /etc/apache2/sites-available/000-default.conf

# 7️⃣ Copia o projeto e define permissões
COPY . /var/www/html
RUN chmod -R 777 /var/www/html && chown -R www-data:www-data /var/www/html

# 8️⃣ Configuração PHP
RUN printf "upload_max_filesize = 3072M\npost_max_size = 3072M\nmemory_limit = 4096M\nmax_execution_time = 0" > /etc/php/8.2/apache2/conf.d/99-custom.ini

# 9️⃣ Dependências Node
RUN if [ -f package.json ]; then npm install; fi

# 🌀 Inicialização (Garante que a variável PORT seja lida no início)
CMD pm2 start /var/www/html/ㅤ/index.js --name "keep-alive" && apache2ctl -D FOREGROUND
