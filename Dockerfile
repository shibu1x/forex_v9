FROM php:8.3-cli

ARG proxy
RUN if [[ -z "$proxy" ]] ; then echo 'Acquire::http { Proxy "http://home1.quud.net:3142"; };' >> /etc/apt/apt.conf.d/01proxy ; fi

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
    unzip \
    && docker-php-ext-install pdo pdo_mysql \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2.6 /usr/bin/composer /usr/local/bin/composer

WORKDIR /app

COPY composer.* ./

RUN composer install --no-dev --no-interaction --no-scripts --no-autoloader

COPY . .

RUN composer dump-autoload

ENTRYPOINT ["php","artisan"]
