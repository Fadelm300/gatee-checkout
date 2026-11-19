FROM php:8.3-cli

RUN apt-get update \
    && apt-get install -y --no-install-recommends libcurl4-openssl-dev libonig-dev \
    && docker-php-ext-install pdo_mysql curl mbstring \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

CMD ["php", "-S", "0.0.0.0:8000", "-t", "public"]