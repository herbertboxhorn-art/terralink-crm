#!/bin/sh
set -e
# Pass env vars through to PHP under Apache
php /var/www/crm/bin/migrate.php
php /var/www/crm/bin/setup-token.php
exec docker-php-entrypoint "$@"
