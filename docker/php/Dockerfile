FROM php:8.2-fpm

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_NO_INTERACTION=1

# Dependências de sistema
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    libicu-dev \
    libpq-dev \
 && rm -rf /var/lib/apt/lists/*

# Extensões PHP
RUN docker-php-ext-install \
    pdo \
    pdo_pgsql \
    zip \
    intl

# Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Código da aplicação
COPY supp-atendimento-back-end /var/www/html/

# Dependências de produção. --no-scripts evita que os auto-scripts do Symfony
# (cache:clear / assets:install) rodem como root durante o build, deixando
# /var/www/html/var/cache/prod com owner root e quebrando o PHP-FPM.
RUN APP_ENV=prod composer install --no-dev --no-scripts --optimize-autoloader --classmap-authoritative

# Garante diretórios graváveis e ownership correto antes do warmup
RUN mkdir -p /var/www/html/var/cache \
             /var/www/html/var/log \
             /var/www/html/public/uploads/attachments \
 && chown -R www-data:www-data /var/www/html

# Warmup do cache executado COMO www-data para que toda a árvore
# var/cache/prod nasça com o dono certo do FPM.
RUN su -s /bin/sh www-data -c "APP_ENV=prod php /var/www/html/bin/console cache:warmup --no-debug"

# Configuração PHP customizada
COPY supp-atendimento-back-end/php.ini /usr/local/etc/php/conf.d/app.ini

# PHP-FPM escutando em todas as interfaces (necessário no Kubernetes)
RUN sed -i 's|listen = .*|listen = 0.0.0.0:9000|' /usr/local/etc/php-fpm.d/www.conf

EXPOSE 9000

CMD ["php-fpm"]
