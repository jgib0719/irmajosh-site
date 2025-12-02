#!/bin/bash
set -euo pipefail

APP_DIR="/var/www/irmajosh.com"

echo "Starting deployment..."
cd "$APP_DIR"

# Verify we're on main branch
CURRENT_BRANCH=$(git rev-parse --abbrev-ref HEAD)
if [ "$CURRENT_BRANCH" != "main" ]; then
    echo "ERROR: Not on main branch (currently on: $CURRENT_BRANCH)"
    echo "Switch to main before deploying: git checkout main"
    exit 1
fi

# Check for uncommitted changes
if [ -n "$(git status --porcelain)" ]; then
    echo "ERROR: Uncommitted changes detected"
    echo "Commit or stash changes before deploying"
    git status
    exit 1
fi

echo "Creating backup..."
bash scripts/backup.sh

echo "Pulling from GitHub..."
git pull --ff-only origin main

echo "Installing dependencies..."
composer install --no-dev --optimize-autoloader --classmap-authoritative
composer validate --no-interaction

echo "Running migrations..."
php scripts/migrate.php

echo "Updating crontab..."
crontab config/crontab

echo "Setting permissions..."
mkdir -p storage/logs storage/cache
chown -R www-data:www-data storage/
chmod -R 755 storage/
chmod 640 .env || true

echo "Clearing app cache..."
rm -f storage/cache/* || true

echo "Reloading services..."
systemctl reload php8.4-fpm
systemctl reload apache2

echo "Deployment complete!"
