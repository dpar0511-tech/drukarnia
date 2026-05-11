#!/bin/bash
export APP_PORT=${PORT:-10000}

if [ ! -f "frankenphp" ]; then
    curl -L https://github.com/dunglas/frankenphp/releases/latest/download/frankenphp-linux-x86_64 -o frankenphp
    chmod +x frankenphp
fi

echo "Starting FrankenPHP server on port $APP_PORT..."
./frankenphp php-server --listen :$APP_PORT --root public/
