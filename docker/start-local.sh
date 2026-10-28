#!/bin/sh
set -eu

if [ "${APP_ENV:-dev}" = "prod" ]; then
    composer install --no-interaction --prefer-dist --no-dev --optimize-autoloader
else
    composer install --no-interaction --prefer-dist
fi
php bin/console lexik:jwt:generate-keypair --skip-if-exists
php bin/console doctrine:migrations:migrate --no-interaction
if [ "${APP_ENV:-dev}" = "dev" ] && [ "${APP_LOCAL_SETUP:-0}" = "1" ]; then
    php bin/console app:seed-local
fi
php bin/console cache:clear

exec php -S 0.0.0.0:8000 -t public
