#!/bin/bash
set -e

echo "Starting build process..."

if [ ! -f "frankenphp" ]; then
    echo "Downloading FrankenPHP..."
    curl -L https://github.com/dunglas/frankenphp/releases/latest/download/frankenphp-linux-x86_64 -o frankenphp
    chmod +x frankenphp
fi

if [ ! -f "composer.phar" ]; then
    echo "Downloading Composer..."
    curl -sS https://getcomposer.org/installer -o composer-setup.php
    ./frankenphp php-cli composer-setup.php
    rm composer-setup.php
fi

echo "Installing PHP dependencies..."
./frankenphp php-cli composer.phar install --no-dev --optimize-autoloader

echo "Installing Node dependencies..."
npm install

echo "Building assets..."
npm run build

echo "Clearing caches..."
./frankenphp php-cli artisan config:clear || true
./frankenphp php-cli artisan route:clear || true
./frankenphp php-cli artisan view:clear || true

echo "Build finished!"
