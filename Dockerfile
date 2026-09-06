# syntax=docker/dockerfile:1

# Assets construits par Vite, puis simplement recopiés : Node n'existe pas dans
# l'image finale.
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY vite.config.js ./
COPY resources ./resources
RUN npm run build

# Dépendances PHP de production, sans les outils de développement.
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction
COPY . .
RUN composer dump-autoload --classmap-authoritative --no-dev

# FrankenPHP sert public/, exécute PHP et obtient le certificat TLS lui-même :
# un seul conteneur en façade, donc une seule chose à surveiller sur le serveur.
FROM dunglas/frankenphp:php8.4-alpine AS runtime

# gd et exif lisent les photos pour l'aperçu ; ffmpeg sonde les vidéos et en
# tire une image. Aucun des trois ne touche jamais au fichier d'origine.
# cjxl/djxl et zstd servent l'archivage sans perte : l'original recompressé se
# reconstruit octet pour octet, l'empreinte SHA-256 le vérifie à chaque fois.
RUN install-php-extensions pdo_mysql pdo_pgsql intl opcache gd exif \
    && apk add --no-cache ffmpeg libjxl-tools zstd

WORKDIR /app

COPY --from=vendor /app /app
COPY --from=assets /app/public/build /app/public/build
COPY docker/Caddyfile /etc/caddy/Caddyfile
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-drop-picture.ini
COPY docker/entrypoint.sh /usr/local/bin/entrypoint

# Les assets de développement n'ont rien à faire en production.
RUN rm -rf /app/node_modules /app/tests \
    && chmod +x /usr/local/bin/entrypoint \
    && chown -R www-data:www-data /app/storage /app/bootstrap/cache

ENTRYPOINT ["entrypoint"]
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]
