# Production image: Yii API + SvelteKit static build on one Apache (same origin).
# Build context: repository root (see deploy/docker/docker-compose.prod.yml).

FROM node:22-alpine AS frontend
WORKDIR /src
COPY frontend/package.json frontend/package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY frontend/ ./
RUN npm run build

FROM yiisoftware/yii2-php:8.5-apache
ENV COMPOSER_ALLOW_SUPERUSER=1 TZ=Europe/Rome
WORKDIR /app
COPY backend/composer.json backend/composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist --no-scripts --optimize-autoloader
COPY backend/ ./
RUN composer dump-autoload --no-dev --optimize \
    && rm -f web/index-test.php \
    && rm -rf tests codeception.yml phpstan.neon
COPY --from=frontend /src/build/ /app/web/
COPY deploy/docker/apache.htaccess /app/web/.htaccess
COPY deploy/docker/cron.sh /usr/local/bin/hq-cron
RUN chmod +x /usr/local/bin/hq-cron \
    && mkdir -p runtime web/assets web/uploads \
    && chown -R www-data:www-data runtime web/assets web/uploads
