#!/bin/bash

# QR Absence System - Robust Setup Script
# Usage: ./setup.sh

set -e

echo "🚀 Starting robust setup..."

# 1. Environment File (Crucial for DB config)
if [ ! -f .env ]; then
    echo "📄 Creating .env from .env.example..."
    cp .env.example .env
    echo "⚠️  Please update .env with your database credentials if not using SQLite."
else
    echo "✅ .env already exists."
fi

# 2. Pre-setup Database (Prevent boot crashes)
# We do this BEFORE composer install because post-install scripts trigger app boot
DB_CONN=$(grep "^DB_CONNECTION=" .env | cut -d '=' -f2 | tr -d '\r')
if [ "$DB_CONN" = "sqlite" ]; then
    DB_PATH=$(grep "^DB_DATABASE=" .env | cut -d '=' -f2 | tr -d '\r')
    # Default to database/database.sqlite if not specified or is relative
    if [ -z "$DB_PATH" ] || [ "$DB_PATH" = "db_qr_system" ]; then
        DB_PATH="database/database.sqlite"
    fi
    
    echo "💾 Initializing SQLite database at: $DB_PATH"
    mkdir -p "$(dirname "$DB_PATH")"
    touch "$DB_PATH"
fi

# 3. Composer Dependencies
echo "📦 Installing PHP dependencies..."
# Use --no-scripts first to prevent boot errors if DB config is wrong
composer install --no-interaction --prefer-dist --optimize-autoloader --no-scripts
# Now run scripts (discovery) after dependencies are safe
composer run-script post-autoload-dump

# 4. Application Key
echo "🔑 Generating application key..."
php artisan key:generate --force

# 5. Migrations
echo "🗄️ Running initial database migrations..."
php artisan migrate --force --no-interaction

# 6. Publish Assets (Fixed: Pennant has no 'install' command)
echo "🔭 Publishing Horizon, Telescope & Pennant assets..."
php artisan telescope:install --no-interaction || echo "Telescope already installed"
php artisan horizon:publish --no-interaction
php artisan vendor:publish --tag=pennant-migrations --no-interaction || true

# 7. Final Migration (Catching newly published migrations)
echo "🔄 Finalizing database schema..."
php artisan migrate --force --no-interaction

# 8. Laravel Reverb
echo "📡 Initializing Laravel Reverb..."
php artisan reverb:install --no-interaction || echo "Reverb already setup"

# 9. Storage Link
echo "🔗 Creating storage symbolic link..."
php artisan storage:link --force

# 10. Frontend Assets
if command -v bun &> /dev/null; then
    echo "🍞 Installing JS dependencies with Bun..."
    bun install
    echo "⚡ Building frontend with Bun..."
    bun run build
elif command -v npm &> /dev/null; then
    echo "📦 Installing JS dependencies with NPM..."
    npm install
    echo "⚡ Building frontend with NPM..."
    npm run build
fi

echo ""
echo "✨ Robust setup complete!"
echo "-------------------------------------------------------"
echo "🛠️  Server:  php artisan octane:start --server=frankenphp"
echo "📡 Reverb:  php artisan reverb:start"
echo "👷 Queue:   php artisan horizon"
echo "-------------------------------------------------------"