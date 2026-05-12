#!/bin/bash
set -e

echo "Starting build process..."

if [ ! -f "frankenphp" ]; then
    echo "Downloading FrankenPHP..."
    curl -L https://github.com/dunglas/frankenphp/releases/latest/download/frankenphp-linux-x86_64 -o frankenphp
    chmod +x frankenphp
fi

# Налаштовуємо глобальну змінну PHP_BINARY для Laravel (composer/artisan), щоб вони використовували frankenphp
export PHP_BINARY=$(pwd)/frankenphp
# Створюємо символічне посилання 'php', якщо його немає в PATH
mkdir -p .bin
ln -sf $(pwd)/frankenphp .bin/php
export PATH=$(pwd)/.bin:$PATH

if [ ! -f "composer.phar" ]; then
    echo "Downloading Composer..."
    curl -sS https://getcomposer.org/installer -o composer-setup.php
    php composer-setup.php
    rm composer-setup.php
fi

echo "Installing PHP dependencies..."
php composer.phar install --no-dev --optimize-autoloader

echo "Installing Node dependencies..."
npm install

echo "Building assets..."
npm run build

echo "Clearing caches..."
php artisan config:clear || true
php artisan route:clear || true
php artisan view:clear || true

echo "Running migrations..."
php artisan migrate --force || true

echo "Build finished!"
