
FROM php:8.4-cli-bookworm AS build

RUN apt-get update && apt-get install -y --no-install-recommends \
    git unzip libpq-dev libzip-dev libicu-dev libcurl4-openssl-dev \
    ca-certificates curl gnupg \
    && docker-php-ext-install pdo_pgsql bcmath intl zip curl \
    && rm -rf /var/lib/apt/lists/*

RUN curl -fsSL https://deb.nodesource.com/setup_24.x | bash - \
    && apt-get update \
    && apt-get install -y --no-install-recommends nodejs \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY . .

RUN composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction

RUN npm ci
RUN npm run build

FROM php:8.4-apache-bookworm AS production

RUN apt-get update && apt-get install -y --no-install-recommends \
    libpq-dev libzip-dev libicu-dev libcurl4-openssl-dev \
    && docker-php-ext-install pdo_pgsql bcmath intl zip curl \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

RUN sed -ri 's!/var/www/html!/var/www/html/public!g' \
    /etc/apache2/sites-available/000-default.conf \
    && printf '<Directory /var/www/html/public>\nAllowOverride All\nRequire all granted\n</Directory>\n' \
    > /etc/apache2/conf-available/laravel.conf \
    && a2enconf laravel

WORKDIR /var/www/html

COPY . .
COPY --from=build /app/vendor ./vendor
COPY --from=build /app/public/build ./public/build

RUN mkdir -p storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs bootstrap/cache database \
    && chown -R www-data:www-data storage bootstrap/cache database \
    && chmod -R ug+rwX storage bootstrap/cache database

EXPOSE 80

CMD ["apache2-foreground"]
