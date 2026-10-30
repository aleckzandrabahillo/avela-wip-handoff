FROM composer:2 AS composer
FROM clamav/clamav:1.4 AS signatures

FROM php:8.4-apache-bookworm
RUN apt-get update \
    && apt-get install -y --no-install-recommends ca-certificates libcurl4-openssl-dev \
       libjpeg62-turbo-dev libonig-dev libpng-dev libwebp-dev python3 unzip \
       clamav-daemon clamav-freshclam clamav clamdscan util-linux \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install -j2 curl gd mbstring pdo_mysql opcache \
    && test -x /usr/bin/clamdscan \
    && a2enmod rewrite headers \
    && usermod -a -G www-data clamav \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer /usr/bin/composer /usr/local/bin/composer
COPY --from=signatures /var/lib/clamav/ /opt/avela-clamav/
WORKDIR /var/www/avela
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress --no-plugins --no-scripts
COPY app ./app
COPY config ./config
COPY database ./database
COPY nlp ./nlp
COPY public ./public
COPY bootstrap.php router.php ./
COPY deploy ./deploy
COPY deploy/apache.conf /etc/apache2/sites-available/000-default.conf
COPY deploy/php.ini /usr/local/etc/php/conf.d/avela.ini
COPY deploy/prefork.conf /etc/apache2/mods-available/mpm_prefork.conf
COPY deploy/clamd.conf /etc/clamav/clamd.conf
COPY deploy/freshclam.conf /etc/clamav/freshclam.conf
COPY deploy/start.sh /usr/local/bin/avela-start

RUN printf 'Listen ${PORT}\n' > /etc/apache2/ports.conf \
    && mkdir -p storage/logs /opt/avela-image-uploads \
    && cp -a public/uploads/. /opt/avela-image-uploads/ \
    && rm -rf public/uploads storage/logs \
    && ln -s /data/uploads public/uploads \
    && ln -s /data/logs storage/logs \
    && chmod 755 /usr/local/bin/avela-start
ENV PORT=10000 APP_ENV=production APP_DEBUG=false SESSION_SECURE_COOKIE=true \
    NLP_PYTHON_BINARY=/usr/bin/python3 PII_KEYRING_FILE=/data/keys/pii-keyring.json \
    DB_TLS_REQUIRED=true DB_SSL_CA=/data/keys/db-ca.pem UPLOAD_SCANNER=clamd \
    MAYA_SANDBOX_ENABLED=false MAYA_LOCAL_HOSTED_SANDBOX=false MAYA_LOCAL_SIMULATION=false \
    MAYA_WEBHOOK_READY=false MAYA_RECONCILIATION_READY=false
EXPOSE 10000
CMD ["avela-start"]
