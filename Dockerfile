FROM node:22-alpine AS frontend
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm install --no-audit --legacy-peer-deps
COPY vite.config.js tsconfig.json ./
COPY resources/ resources/
RUN npm run build

FROM php:8.4-fpm-alpine
RUN apk add --no-cache nginx supervisor libpng-dev libjpeg-turbo-dev freetype-dev libzip-dev zip unzip git libxml2-dev oniguruma-dev curl mysql-client
RUN docker-php-ext-configure gd --with-freetype --with-jpeg && docker-php-ext-install -j$(nproc) pdo pdo_mysql mbstring xml bcmath gd zip opcache exif pcntl
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www
COPY . .
COPY --from=frontend /app/public/build ./public/build
RUN composer install --no-dev --no-interaction --optimize-autoloader --no-progress
RUN chown -R www-data:www-data storage bootstrap/cache && chmod -R 775 storage bootstrap/cache
COPY docker/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/99-hotel.ini
EXPOSE 80
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]