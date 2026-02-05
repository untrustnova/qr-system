# QR Absence System - Setup Script (PowerShell)
# Usage: .\setup.ps1

$ErrorActionPreference = "Stop"

Write-Host "🚀 Starting robust setup for Windows..." -ForegroundColor Cyan

# 1. Environment File
if (-not (Test-Path ".env")) {
    Write-Host "📄 Creating .env from .env.example..." -ForegroundColor Yellow
    Copy-Item ".env.example" ".env"
    Write-Host "⚠️  Please update .env with your database credentials if not using SQLite." -ForegroundColor Magenta
} else {
    Write-Host "✅ .env already exists." -ForegroundColor Green
}

# 2. Pre-setup Database
$envContent = Get-Content .env
$dbConn = ($envContent | Select-String "^DB_CONNECTION=").ToString().Split('=')[1].Trim()

if ($dbConn -eq "sqlite") {
    $dbPath = ($envContent | Select-String "^DB_DATABASE=").ToString().Split('=')[1].Trim()
    if ([string]::IsNullOrWhiteSpace($dbPath) -or $dbPath -eq "db_qr_system") {
        $dbPath = "database/database.sqlite"
    }
    
    $fullPath = Resolve-Path -Path "." -Relative
    $dbFullPath = Join-Path $fullPath $dbPath
    
    Write-Host "💾 Initializing SQLite database at: $dbPath" -ForegroundColor Yellow
    
    $parentDir = Split-Path $dbFullPath -Parent
    if (-not (Test-Path $parentDir)) {
        New-Item -ItemType Directory -Path $parentDir | Out-Null
    }
    
    if (-not (Test-Path $dbFullPath)) {
        New-Item -ItemType File -Path $dbFullPath | Out-Null
    }
}

# 3. Composer Dependencies
Write-Host "📦 Installing PHP dependencies..." -ForegroundColor Cyan
composer install --no-interaction --prefer-dist --optimize-autoloader --no-scripts
composer run-script post-autoload-dump

# 4. Application Key
Write-Host "🔑 Generating application key..." -ForegroundColor Cyan
php artisan key:generate --force

# 5. Migrations
Write-Host "🗄️ Running initial database migrations..." -ForegroundColor Cyan
php artisan migrate --force --no-interaction

# 6. Publish Assets
Write-Host "🔭 Publishing Horizon, Telescope & Pennant assets..." -ForegroundColor Cyan
php artisan telescope:install --no-interaction 2>$null
php artisan horizon:publish --no-interaction
php artisan vendor:publish --tag=pennant-migrations --no-interaction 2>$null

# 7. Final Migration
Write-Host "🔄 Finalizing database schema..." -ForegroundColor Cyan
php artisan migrate --force --no-interaction

# 8. Laravel Reverb
Write-Host "📡 Initializing Laravel Reverb..." -ForegroundColor Cyan
php artisan reverb:install --no-interaction 2>$null

# 9. Storage Link
Write-Host "🔗 Creating storage symbolic link..." -ForegroundColor Cyan
php artisan storage:link --force

# 10. Frontend Assets
if (Get-Command "bun" -ErrorAction SilentlyContinue) {
    Write-Host "🍞 Installing JS dependencies with Bun..." -ForegroundColor Cyan
    bun install
    Write-Host "⚡ Building frontend with Bun..." -ForegroundColor Cyan
    bun run build
} elseif (Get-Command "npm" -ErrorAction SilentlyContinue) {
    Write-Host "📦 Installing JS dependencies with NPM..." -ForegroundColor Cyan
    npm install
    Write-Host "⚡ Building frontend with NPM..." -ForegroundColor Cyan
    npm run build
}

Write-Host ""
Write-Host "✨ Robust setup complete!" -ForegroundColor Green
Write-Host "-------------------------------------------------------"
Write-Host "🛠️  Server:  php artisan octane:start --server=frankenphp"
Write-Host "📡 Reverb:  php artisan reverb:start"
Write-Host "👷 Queue:   php artisan horizon"
Write-Host "-------------------------------------------------------"
