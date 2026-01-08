#!/bin/bash

# ==============================================
# MangOyen API - Production Deployment Script
# ==============================================
# Usage: ./deploy.sh
# Run this script on your production server
# ==============================================

set -e

echo "🚀 Starting MangOyen API Deployment..."
echo "========================================"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Configuration
APP_DIR="/home/mangoyen/web/api.mangoyen.com/public_html"

# Navigate to app directory
cd "$APP_DIR" || { echo -e "${RED}❌ Directory not found: $APP_DIR${NC}"; exit 1; }

echo -e "${YELLOW}📂 Working directory: $(pwd)${NC}"

# 1. Enable maintenance mode
echo -e "\n${YELLOW}🔧 Enabling maintenance mode...${NC}"
php artisan down --retry=60 || true

# 2. Pull latest code
echo -e "\n${YELLOW}📥 Pulling latest code from GitHub...${NC}"
git fetch origin main
git reset --hard origin/main

# 3. Install/update dependencies
echo -e "\n${YELLOW}📦 Installing Composer dependencies...${NC}"
composer install --no-dev --optimize-autoloader --no-interaction --ignore-platform-reqs

# 4. Run migrations
echo -e "\n${YELLOW}🗄️ Running database migrations...${NC}"
php artisan migrate --force

# 5. Clear and rebuild caches
echo -e "\n${YELLOW}🧹 Clearing and rebuilding caches...${NC}"
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 6. Restart queue workers (if using Supervisor)
echo -e "\n${YELLOW}🔄 Restarting queue workers...${NC}"
php artisan queue:restart || true
# Uncomment if using Supervisor:
# sudo supervisorctl restart all

# 7. Set proper permissions
echo -e "\n${YELLOW}🔐 Setting permissions...${NC}"
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

# 8. Disable maintenance mode
echo -e "\n${YELLOW}✅ Disabling maintenance mode...${NC}"
php artisan up

echo ""
echo -e "${GREEN}========================================"
echo "🎉 Deployment completed successfully!"
echo "========================================${NC}"
echo ""
echo "📋 Checklist:"
echo "   ✓ Code updated from GitHub"
echo "   ✓ Dependencies installed"
echo "   ✓ Migrations executed"
echo "   ✓ Caches rebuilt"
echo "   ✓ Queue workers restarted"
echo "   ✓ Permissions set"
echo ""
echo -e "${YELLOW}⚠️  Don't forget to check:${NC}"
echo "   - .env file has correct production values"
echo "   - Midtrans webhook URL is configured"
echo "   - Cron job for scheduler is active"
echo ""
