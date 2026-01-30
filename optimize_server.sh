#!/bin/bash
# ============================================
# PHP-FPM & Nginx Optimization Script
# ============================================

echo "🚀 Starting optimization process..."
echo "======================================"
echo ""

# ============================================
# BACKUP FILES
# ============================================
echo "📦 Step 1: Backup configuration files..."
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
sudo cp /etc/php/8.4/fpm/php.ini /etc/php/8.4/fpm/php.ini.backup_before_opt_${TIMESTAMP}
sudo cp /etc/nginx/nginx.conf /etc/nginx/nginx.conf.backup_before_opt_${TIMESTAMP}
echo "✅ Backups created with timestamp: ${TIMESTAMP}"
echo ""

# ============================================
# UPDATE PHP CONFIGURATION
# ============================================
echo "⚙️  Step 2: Update PHP-FPM configuration..."

# Update memory_limit, max_execution_time, max_input_time
sudo sed -i 's/^max_execution_time = 30/max_execution_time = 90/' /etc/php/8.4/fpm/php.ini
sudo sed -i 's/^max_input_time = 60/max_input_time = 90/' /etc/php/8.4/fpm/php.ini
sudo sed -i 's/^memory_limit = 128M/memory_limit = 512M/' /etc/php/8.4/fpm/php.ini

# Enable OPcache
sudo sed -i 's/^;opcache.enable=1/opcache.enable=1/' /etc/php/8.4/fpm/php.ini
sudo sed -i 's/^;opcache.enable_cli=0/opcache.enable_cli=0/' /etc/php/8.4/fpm/php.ini
sudo sed -i 's/^;opcache.memory_consumption=128/opcache.memory_consumption=128/' /etc/php/8.4/fpm/php.ini
sudo sed -i 's/^;opcache.interned_strings_buffer=8/opcache.interned_strings_buffer=8/' /etc/php/8.4/fpm/php.ini
sudo sed -i 's/^;opcache.max_accelerated_files=10000/opcache.max_accelerated_files=10000/' /etc/php/8.4/fpm/php.ini
sudo sed -i 's/^;opcache.revalidate_freq=2/opcache.revalidate_freq=60/' /etc/php/8.4/fpm/php.ini
sudo sed -i 's/^;opcache.save_comments=1/opcache.save_comments=1/' /etc/php/8.4/fpm/php.ini

echo "✅ PHP-FPM configuration updated"
echo ""

# ============================================
# UPDATE NGINX GZIP CONFIGURATION
# ============================================
echo "🌐 Step 3: Update Nginx gzip configuration..."

# Update gzip settings in nginx.conf
sudo sed -i 's/^	# gzip_vary on;/	gzip_vary on;/' /etc/nginx/nginx.conf
sudo sed -i 's/^	# gzip_proxied any;/	gzip_proxied any;/' /etc/nginx/nginx.conf
sudo sed -i 's/^	# gzip_comp_level 6;/	gzip_comp_level 6;/' /etc/nginx/nginx.conf
sudo sed -i 's/^	# gzip_buffers 16 8k;/	gzip_buffers 16 8k;/' /etc/nginx/nginx.conf
sudo sed -i 's/^	# gzip_http_version 1.1;/	gzip_http_version 1.1;/' /etc/nginx/nginx.conf
sudo sed -i 's/^	# gzip_types text/plain text\/css application\/json application\/javascript text\/xml application\/xml application\/xml\+rss text\/javascript;/	gzip_types text\/plain text\/css application\/json application\/javascript text\/xml application\/xml application\/xml\+rss text\/javascript image\/svg+xml application\/rss\+xml font\/truetype font\/opentype application\/vnd.ms-fontobject image\/x-icon;/' /etc/nginx/nginx.conf

echo "✅ Nginx gzip configuration updated"
echo ""

# ============================================
# TEST NGINX CONFIGURATION
# ============================================
echo "🧪 Step 4: Test Nginx configuration..."
sudo nginx -t
if [ $? -eq 0 ]; then
    echo "✅ Nginx configuration is valid"
else
    echo "❌ Nginx configuration has errors. Please fix before proceeding."
    exit 1
fi
echo ""

# ============================================
# RESTART SERVICES
# ============================================
echo "🔄 Step 5: Restart services..."

echo "   - Restarting PHP-FPM..."
sudo systemctl restart php8.4-fpm
sudo systemctl status php8.4-fpm --no-pager | head -5

echo "   - Reloading Nginx..."
sudo systemctl reload nginx
sudo systemctl status nginx --no-pager | head -5

echo "✅ Services restarted successfully"
echo ""

# ============================================
# VERIFY CONFIGURATIONS
# ============================================
echo "✅ Step 6: Verify configurations..."

echo "   PHP Configuration:"
php -i | grep -E "memory_limit|max_execution_time|max_input_time|upload_max_filesize|post_max_size"

echo ""
echo "   OPcache Status:"
php -r "echo opcache_get_status()['opcache_enabled'] ? 'OPcache: ENABLED' : 'OPcache: DISABLED';"
php -i | grep opcache | head -5

echo ""
echo "   Nginx Gzip:"
sudo nginx -T 2>/dev/null | grep -A1 "gzip on" | head -3

echo ""
echo "======================================"
echo "🎉 Optimization completed successfully!"
echo "======================================"
echo ""
echo "📊 Next Steps:"
echo "   1. Test application: curl -I https://anggota.plnipservices.or.id/"
echo "   2. Login and test all critical functions"
echo "   3. Check logs: tail -f /var/www/anggota.plnipservices.or.id/storage/logs/laravel.log"
echo "   4. Monitor resources: free -h && redis-cli info memory | grep used_memory_human"
echo ""
echo "📋 Rollback commands (if needed):"
echo "   sudo cp /etc/php/8.4/fpm/php.ini.backup_before_opt_${TIMESTAMP} /etc/php/8.4/fpm/php.ini"
echo "   sudo cp /etc/nginx/nginx.conf.backup_before_opt_${TIMESTAMP} /etc/nginx/nginx.conf"
echo "   sudo systemctl restart php8.4-fpm && sudo systemctl reload nginx"
echo ""
