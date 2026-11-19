# TerraLink CRM — PHP 8.3 + Apache
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

FROM php:8.3-apache
RUN docker-php-ext-install pdo_mysql \
 && a2enmod rewrite headers remoteip \
 && mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
 && { echo 'expose_php=Off'; echo 'memory_limit=256M'; echo 'upload_max_filesize=8M'; echo 'post_max_size=10M'; echo 'date.timezone=America/Vancouver'; } > "$PHP_INI_DIR/conf.d/crm.ini"
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
WORKDIR /var/www/crm
COPY --from=vendor /app/vendor ./vendor
COPY src ./src
COPY views ./views
COPY public ./public
COPY sql ./sql
COPY bin ./bin
COPY docker/entrypoint.sh /usr/local/bin/crm-entrypoint
RUN chmod +x /usr/local/bin/crm-entrypoint && rm -f public/dev-router.php && chown -R root:www-data /var/www/crm && chmod -R a-w /var/www/crm
EXPOSE 80
HEALTHCHECK --interval=30s --timeout=5s --retries=3 CMD curl -fsS http://localhost/api/health || exit 1
ENTRYPOINT ["crm-entrypoint"]
CMD ["apache2-foreground"]
