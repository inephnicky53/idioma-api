#!/bin/sh
set -e

echo "Waiting for queue to be ready..."
wait-for queue:5672 -- echo "queue service is ready"

echo "Waiting for database to be ready..."
wait-for database:3306 -- echo "database service is ready"

echo "Waiting for api to be ready..."
wait-for api:80 --timeout=120 -- echo "api service is ready"

if [ "$APP_ENV" != 'prod' ]; then
    composer install --prefer-dist --no-progress --no-interaction
fi

exec "$@"