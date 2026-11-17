# Imagem de produção (Render). FrankenPHP = servidor web + PHP num processo só.
FROM dunglas/frankenphp:php8.5

RUN install-php-extensions pdo_pgsql opcache zip intl \
    # Render roda com no-new-privileges: binário com capability falha ("Operation not permitted"). Porta alta não precisa dela.
    && setcap -r /usr/local/bin/frankenphp

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

COPY . .
RUN composer dump-autoload --optimize --no-dev \
    && php artisan package:discover --ansi

# Render injeta PORT. Migrations e cache de config rodam no start porque as variáveis só existem em runtime.
CMD php artisan migrate --force \
    && php artisan config:cache \
    && php artisan route:cache \
    && SERVER_NAME=":${PORT:-8080}" exec frankenphp run --config /etc/frankenphp/Caddyfile
