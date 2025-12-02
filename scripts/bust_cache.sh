#!/bin/bash
#
# Cache Busting Script
# Run this after making changes to CSS/JS to force browsers to update
#

echo "🔄 Busting cache for frontend assets..."

# Regenerate autoloader to pick up new classes
echo "📚 Updating Composer autoloader..."
composer dump-autoload -o

# Touch CSS and JS files to update their modification time
touch /var/www/irmajosh.com/public_html/assets/css/style.css
touch /var/www/irmajosh.com/public_html/assets/js/app.js
touch /var/www/irmajosh.com/public_html/assets/js/modal.js
touch /var/www/irmajosh.com/public_html/assets/js/form-utils.js

# Reload PHP-FPM to clear OPcache
echo "🔄 Reloading PHP-FPM..."
sudo systemctl reload php8.4-fpm

# Show new versions
echo ""
echo "✅ New asset versions:"
echo "   CSS: $(stat -c %Y /var/www/irmajosh.com/public_html/assets/css/style.css)"
echo "   app.js: $(stat -c %Y /var/www/irmajosh.com/public_html/assets/js/app.js)"
echo ""
echo "📱 Browsers will fetch new versions within 1 hour (max-age=3600)"
echo "💡 For immediate update: Hard refresh or clear browser cache"
echo ""
echo "✅ Cache busting complete!"
