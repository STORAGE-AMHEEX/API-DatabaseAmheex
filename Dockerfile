# ✅ Base Debian 12 (Bookworm)
FROM debian:bookworm-slim

ENV DEBIAN_FRONTEND=noninteractive
WORKDIR /var/www/html

# 1️⃣ Repositórios completos (main contrib non-free non-free-firmware)
RUN echo "Types: deb\n\
URIs: http://deb.debian.org/debian\n\
Suites: bookworm bookworm-updates\n\
Components: main contrib non-free non-free-firmware\n\
\n\
Types: deb\n\
URIs: http://deb.debian.org/debian-security\n\
Suites: bookworm-security\n\
Components: main contrib non-free non-free-firmware" > /etc/apt/sources.list.d/debian.sources

# 2️⃣ Pacotes de rede, sistema e compilação
RUN apt-get update && apt-get install -y --no-install-recommends \
    ca-certificates curl wget git unzip zip p7zip-full unrar-free nano vim gnupg2 lsb-release apt-transport-https sudo net-tools build-essential \
    && rm -rf /var/lib/apt/lists/*

# 🎬 3️⃣ FFmpeg e Todos os Codecs de Vídeo, Áudio, Imagem e Legenda
RUN apt-get update && apt-get install -y --no-install-recommends \
    ffmpeg \
    libx264-dev \
    libx265-dev \
    libvpx-dev \
    libfdk-aac-dev \
    libmp3lame-dev \
    libopus-dev \
    libvorbis-dev \
    libtheora-dev \
    libwebp-dev \
    libass-dev \
    libfreetype6-dev \
    libaom-dev \
    libdav1d-dev \
    librav1e-dev \
    libsvtav1-dev \
    libspeex-dev \
    libsoxr-dev \
    libtwolame-dev \
    libopenjp2-7-dev \
    libbluray-dev \
    libgme-dev \
    libgsm1-dev \
    libmodplug-dev \
    libopencore-amrnb-dev \
    libopencore-amrwb-dev \
    libsnappy-dev \
    libcodec2-dev \
    gstreamer1.0-plugins-base \
    gstreamer1.0-plugins-good \
    gstreamer1.0-plugins-bad \
    gstreamer1.0-plugins-ugly \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# 📄 4️⃣ Manipulação de Imagens, PDFs, Documentos e OCR
RUN apt-get update && apt-get install -y --no-install-recommends \
    imagemagick \
    graphicsmagick \
    poppler-utils \
    ghostscript \
    tesseract-ocr \
    tesseract-ocr-por \
    libreoffice-writer-nogui \
    libreoffice-calc-nogui \
    libreoffice-impress-nogui \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# 5️⃣ PHP 8.2 + Apache + Extensões de Arquivos e Mídia
RUN curl -sSLo /tmp/debsuryorg-archive-keyring.deb https://packages.sury.org/debsuryorg-archive-keyring.deb \
    && dpkg -i /tmp/debsuryorg-archive-keyring.deb \
    && echo "deb [signed-by=/usr/share/keyrings/deb.sury.org-php.gpg] https://packages.sury.org/php/ $(lsb_release -sc) main" \
    > /etc/apt/sources.list.d/php.list \
    && apt-get update && apt-get install -y --no-install-recommends \
        apache2 \
        libapache2-mod-php8.2 \
        php8.2 php8.2-cli php8.2-common php8.2-mbstring php8.2-xml php8.2-zip \
        php8.2-curl php8.2-opcache php8.2-bcmath php8.2-intl php8.2-gd php8.2-mysql php8.2-dev \
        php8.2-imagick php8.2-sqlite3 php8.2-gmp \
    && apt-get clean && rm -rf /var/lib/apt/lists/* /tmp/*.deb

# 6️⃣ Node.js LTS + PM2
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && npm install -g pm2 \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# 7️⃣ Ativa módulos Apache e ajusta escuta
RUN a2enmod rewrite headers expires dir php8.2 proxy proxy_http proxy_fcgi setenvif http2 deflate \
    && sed -i 's/Listen 80/Listen 0.0.0.0:80/' /etc/apache2/ports.conf

# 8️⃣ Configuração Apache — Suporte a uploads ilimitados (1TB)
RUN cat <<'EOF' > /etc/apache2/sites-available/000-default.conf
<VirtualHost *:80>
    ServerAdmin webmaster@localhost
    DocumentRoot /var/www/html

    <Directory /var/www/html>
        Options -Indexes +FollowSymLinks +MultiViews
        DirectoryIndex index.php index.html
        AllowOverride All
        Require all granted
        LimitRequestBody 0
    </Directory>

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

# 9️⃣ PHP Config — Suporte a grandes arquivos e conversão ilimitada
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

# 📂 Copia aplicação
COPY . /var/www/html

# 🛠️ 🔟 Criação das Pastas Temporárias e Permissões Globais
RUN mkdir -p /var/www/html/X/audio/v1/temp \
    && mkdir -p /tmp/ffmpeg \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 777 /var/www/html \
    && chmod -R 777 /tmp

# 11️⃣ Dependências Node.js
RUN if [ -f package.json ]; then npm install; fi
RUN npm install axios node-cron || true

EXPOSE 80

# 🌀 Script de Inicialização com garantia de pastas temporárias ao iniciar o container
RUN echo "#!/bin/bash\nmkdir -p /var/www/html/X/audio/v1/temp /tmp/ffmpeg\nchown -R www-data:www-data /var/www/html\nchmod -R 777 /var/www/html /tmp\nservice apache2 start\npm2-runtime start /var/www/html/index.js --name keep-alive" > /index.sh \
    && chmod +x /index.sh

CMD ["/index.sh"]
