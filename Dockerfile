FROM php:8.4-cli

# Install ekstensi PHP yang dibutuhkan
RUN docker-php-ext-install mysqli pdo pdo_mysql
RUN apt-get update && apt-get install -y libcurl4-openssl-dev libpng-dev libjpeg-dev libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install curl gd \
    && rm -rf /var/lib/apt/lists/*

# Copy project files
COPY . /app
WORKDIR /app

# Buat folder uploads jika belum ada
RUN mkdir -p uploads/bukti uploads/qris uploads/service uploads/homepage uploads/barber \
    && chmod -R 777 uploads

# Expose port (Railway set $PORT otomatis)
EXPOSE ${PORT:-8080}

# Jalankan PHP built-in server
CMD php -S 0.0.0.0:${PORT:-8080} -t .
