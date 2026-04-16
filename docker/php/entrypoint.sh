#!/bin/sh
set -e

cd /var/www

if [ ! -f .env ]; then
  cp .env.example .env
fi

if [ ! -f vendor/autoload.php ]; then
  composer install
fi

php artisan key:generate --force
php artisan migrate --force
php artisan serve --host=0.0.0.0 --port=8000
