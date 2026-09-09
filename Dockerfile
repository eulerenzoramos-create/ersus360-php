FROM php:8.4-fpm-alpine AS base

# Extensões necessárias
RUN apk add --no-cache \
        nginx \
        supervisor \
        curl \
        libzip-dev \
        oniguruma-dev \
        icu-dev \
        libpng-dev \
        libjpeg-turbo-dev \
        freetype-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
        pdo_mysql \
        mbstring \
        intl \
        zip \
        gd \
        opcache \
        bcmath \
    && rm -rf /var/cache/apk/*

# Composer
COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/ersus360

# Copiar dependências primeiro (cache layer)
COPY composer.json ./
RUN composer install --no-dev --optimize-autoloader --no-scripts --no-progress

# Copiar aplicação
COPY . .

# Permissões
RUN mkdir -p storage/logs storage/cache \
    && chown -R www-data:www-data storage \
    && chmod -R 775 storage

# OPcache para produção
RUN echo "opcache.enable=1" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.memory_consumption=256" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.interned_strings_buffer=16" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.max_accelerated_files=10000" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.validate_timestamps=0" >> /usr/local/etc/php/conf.d/opcache.ini

# Configurações nginx e supervisor
COPY docker/nginx.conf      /etc/nginx/nginx.conf
COPY docker/supervisord.conf /etc/supervisord.conf

EXPOSE 80

# Entrypoint: aguarda MySQL, roda migrações e sobe supervisor
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

CMD ["/entrypoint.sh"]
