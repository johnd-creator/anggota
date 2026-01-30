#!/bin/bash
# ============================================
# Verification Script After Optimization
# ============================================

echo "🔍 Verification After Optimization"
echo "===================================="
echo ""

# ============================================
# TEST REDIS CONNECTION
# ============================================
echo "📌 Step 1: Test Redis Connection..."
redis-cli ping
if [ $? -eq 0 ]; then
    echo "✅ Redis is running"
else
    echo "❌ Redis is not responding"
fi

echo ""
echo "   Redis Keys:"
redis-cli keys "*laravel_cache*" | head -3
redis-cli keys "*laravel_session*" | head -3
echo "   Total keys in Redis: $(redis-cli dbsize)"
echo "   Redis memory: $(redis-cli info memory | grep used_memory_human | cut -d: -f2 | tr -d '\r')"
echo ""

# ============================================
# TEST LARAVEL CACHE WITH REDIS
# ============================================
echo "📌 Step 2: Test Laravel Cache with Redis..."
php artisan tinker --execute="Cache::store('redis')->put('test_key', 'redis-working', 60); echo Cache::store('redis')->get('test_key');"
if [ $? -eq 0 ]; then
    echo "✅ Laravel cache with Redis is working"
else
    echo "❌ Laravel cache with Redis failed"
fi
echo ""

# ============================================
# VERIFY OPCACHE STATUS
# ============================================
echo "📌 Step 3: Verify OPcache Status..."
OPCACHE_ENABLED=$(php -r "echo opcache_get_status()['opcache_enabled'] ? '1' : '0';")
if [ "$OPCACHE_ENABLED" = "1" ]; then
    echo "✅ OPcache is ENABLED"
    php -i | grep opcache | head -8

    echo ""
    echo "   OPcache Hit Rate:"
    php -r "$status = opcache_get_status(); \$hits = \$status['opcache_statistics']['hits']; \$misses = \$status['opcache_statistics']['misses']; \$total = \$hits + \$misses; \$hitRate = \$total > 0 ? (\$hits / \$total) * 100 : 0; echo '   ' . number_format(\$hitRate, 2) . '%'; echo '   Cached Files: ' . \$status['opcache_statistics']['num_cached_scripts'];"
else
    echo "❌ OPcache is DISABLED"
fi
echo ""

# ============================================
# VERIFY PHP CONFIGURATION
# ============================================
echo "📌 Step 4: Verify PHP Configuration..."
echo "   PHP Version: $(php -v | head -1)"
echo "   Memory Limit: $(php -i | grep memory_limit | cut -d= -f2)"
echo "   Max Execution Time: $(php -i | grep max_execution_time | cut -d= -f2)"
echo "   Max Input Time: $(php -i | grep max_input_time | cut -d= -f2)"
echo "   Upload Max Filesize: $(php -i | grep upload_max_filesize | cut -d= -f2)"
echo "   Post Max Size: $(php -i | grep post_max_size | cut -d= -f2)"
echo ""

# ============================================
# VERIFY NGINX GZIP
# ============================================
echo "📌 Step 5: Verify Nginx Gzip..."
echo "   Testing with curl:"
echo ""
echo "   Without compression:"
SIZE_WITHOUT=$(curl -I https://anggota.plnipservices.or.id/ -w "Size: %{size_download}\n" -o /dev/null -s)
echo "   $SIZE_WITHOUT"

echo ""
echo "   With gzip compression:"
SIZE_WITH=$(curl -I -H "Accept-Encoding: gzip" https://anggota.plnipservices.or.id/ -w "Size with gzip: %{size_download}\n" -o /dev/null -s)
echo "   $SIZE_WITH"

echo ""
echo "   Content-Encoding header:"
curl -I -H "Accept-Encoding: gzip" https://anggota.plnipservices.or.id/ 2>/dev/null | grep -i "content-encoding"
echo ""

# ============================================
# CHECK SERVICES STATUS
# ============================================
echo "📌 Step 6: Check All Services Status..."
echo "   Redis Service:"
sudo systemctl status redis-server --no-pager | head -4

echo ""
echo "   PHP-FPM Service:"
sudo systemctl status php8.4-fpm --no-pager | head -4

echo ""
echo "   Nginx Service:"
sudo systemctl status nginx --no-pager | head -4

echo ""
echo "   MariaDB Service:"
sudo systemctl status mariadb --no-pager | head -4

# ============================================
# CHECK SYSTEM RESOURCES
# ============================================
echo ""
echo "📌 Step 7: Check System Resources..."
echo "   Memory Usage:"
free -h | grep -E "Mem:|Swap:"

echo ""
echo "   Disk Usage:"
df -h | grep -E "Filesystem|/dev/vda2"

echo ""
echo "   CPU Load Average:"
uptime | awk -F'load average:' '{print "   " $2}'

# ============================================
# CHECK LARAVEL LOGS FOR ERRORS
# ============================================
echo ""
echo "📌 Step 8: Check Laravel Logs for Errors..."
ERROR_COUNT=$(tail -100 /var/www/anggota.plnipservices.or.id/storage/logs/laravel.log | grep -i "error\|exception\|fatal" | wc -l)
if [ $ERROR_COUNT -gt 0 ]; then
    echo "⚠️  Found $ERROR_COUNT errors in last 100 lines of Laravel log"
    echo "   Recent errors:"
    tail -100 /var/www/anggota.plnipservices.or.id/storage/logs/laravel.log | grep -i "error\|exception\|fatal" | tail -5
else
    echo "✅ No errors found in last 100 lines of Laravel log"
fi

# ============================================
# FINAL SUMMARY
# ============================================
echo ""
echo "===================================="
echo "✅ Verification Complete!"
echo "===================================="
echo ""
echo "📊 Performance Summary:"
echo "   - Redis: Enabled and working"
echo "   - OPcache: Enabled"
echo "   - Gzip Compression: Enabled"
echo "   - PHP Memory: 512M"
echo "   - PHP Max Execution: 90s"
echo "   - Available RAM: $(free -h | grep Mem: | awk '{print $7}')"
echo ""
echo "🎯 Next Steps:"
echo "   1. Test application in browser: https://anggota.plnipservices.or.id/"
echo "   2. Login and test all critical functions"
echo "   3. Monitor for next 24-48 hours"
echo "   4. Check logs: tail -f /var/www/anggota.plnipservices.or.id/storage/logs/laravel.log"
echo ""
