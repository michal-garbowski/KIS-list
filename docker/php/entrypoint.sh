#!/bin/bash
set -e

if [ ! -d vendor ]; then
    composer install --no-interaction
    chown -R "$(stat -c '%u:%g' .)" vendor
fi

php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

exec docker-php-entrypoint "$@"
