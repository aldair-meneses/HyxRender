#!/bin/sh
set -e

# Writable dirs for the web server (dev only, lives in container volume/mount).
mkdir -p web/app/uploads web/app/cache
touch web/.htaccess
chmod 777 web/app/uploads web/app/cache
chmod 666 web/.htaccess

exec docker-php-entrypoint "$@"
