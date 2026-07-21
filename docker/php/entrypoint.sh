#!/bin/bash
set -e

if [ ! -d vendor ]; then
    composer install --no-interaction
fi

php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

# composer/console run as root and write into the bind-mounted host directory
# (vendor/, var/cache, var/log) - hand ownership back to the host user.
chown -R "$(stat -c '%u:%g' .)" vendor var

exec docker-php-entrypoint "$@"
