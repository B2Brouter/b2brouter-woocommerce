# Local WordPress Development Environment Setup

**For B2Brouter WooCommerce Plugin Testing on Debian**

This guide provides multiple approaches to set up a local WordPress environment for testing the B2Brouter WooCommerce plugin on Debian.

---

## Table of Contents

1. [Option 1: PHP Built-in Server (Recommended - No Web Server)](#option-1-php-built-in-server-recommended)
2. [Option 2: Nginx + PHP-FPM (Production-like)](#option-2-nginx--php-fpm)
3. [Post-Installation: Plugin Setup](#post-installation-plugin-setup)
4. [Troubleshooting](#troubleshooting)

---

## Option 1: PHP Built-in Server (Recommended)

**Advantages**: No web server needed, fastest setup, perfect for development.

### Prerequisites

```bash
# Install required packages
# Note: Use php-cli instead of php to avoid installing Apache2
sudo apt update
sudo apt install -y php-cli php-mysql php-xml php-mbstring php-curl \
    php-zip php-gd php-intl mariadb-server wget unzip

# Check PHP version (should be 7.4+)
php -v
```

### Step 1: Install and Configure MariaDB

```bash
# Start MariaDB
sudo systemctl start mariadb
sudo systemctl enable mariadb

# Secure installation (optional for local dev)
sudo mysql_secure_installation

# Create WordPress database and user
sudo mysql -u root -p
```

In the MySQL prompt:
```sql
CREATE DATABASE wordpress_local CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'wpuser'@'localhost' IDENTIFIED BY 'wppass123';
GRANT ALL PRIVILEGES ON wordpress_local.* TO 'wpuser'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### Step 2: Download and Install WordPress

```bash
# Create directory for WordPress
mkdir -p ~/local-wordpress
cd ~/local-wordpress

# Download WordPress
wget https://wordpress.org/latest.tar.gz
tar -xzf latest.tar.gz
cd wordpress

# Create wp-config.php
cp wp-config-sample.php wp-config.php
```

Edit `wp-config.php`:
```bash
vim wp-config.php
```

Update these lines:
```php
define( 'DB_NAME', 'wordpress_local' );
define( 'DB_USER', 'wpuser' );
define( 'DB_PASSWORD', 'wppass123' );
define( 'DB_HOST', 'localhost' );

// Add these for debugging
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );
```

Generate security keys from https://api.wordpress.org/secret-key/1.1/salt/ and replace the existing keys.

### Step 3: Start PHP Built-in Server

```bash
# From the wordpress directory
cd ~/local-wordpress/wordpress

# Start server on localhost:8000
php -S localhost:8000

# Alternative: Bind to all interfaces if needed
# php -S 0.0.0.0:8000
```

### Step 4: Complete WordPress Installation

1. Open browser: http://localhost:8000
2. Select language: English
3. Create admin account:
   - Site Title: "B2Brouter Local Dev"
   - Username: admin
   - Password: (choose a strong password)
   - Email: your-email@example.com
4. Click "Install WordPress"
5. Log in to WordPress admin

### Step 5: Install WooCommerce

```bash
# Download WooCommerce
cd ~/local-wordpress/wordpress/wp-content/plugins/
wget https://downloads.wordpress.org/plugin/woocommerce.latest-stable.zip
unzip woocommerce.latest-stable.zip
rm woocommerce.latest-stable.zip
```

Or via WordPress admin:
1. Go to http://localhost:8000/wp-admin/
2. Navigate to **Plugins → Add New**
3. Search for "WooCommerce"
4. Click "Install Now" → "Activate"
5. Follow WooCommerce setup wizard

### Step 6: Install B2Brouter WooCommerce Plugin

**Important:** The plugin requires the B2Brouter PHP SDK, which is installed via Composer.

```bash
# First, install Composer dependencies in your plugin directory
cd ~/projects/ingent/b2b-woocommerce
composer install

# This will download:
# - b2brouter/b2brouter-php (^0.9.0) - Official SDK from Packagist
# - phpunit/phpunit (dev dependency for testing)
```

Then link or copy the plugin to WordPress:

```bash
# Create symbolic link to your development plugin (recommended)
cd ~/local-wordpress/wordpress/wp-content/plugins/
ln -s ~/projects/ingent/b2b-woocommerce b2brouter-woocommerce

# Or copy the plugin (alternative)
# cp -r ~/projects/ingent/b2b-woocommerce ~/local-wordpress/wordpress/wp-content/plugins/b2brouter-woocommerce
# cd ~/local-wordpress/wordpress/wp-content/plugins/b2brouter-woocommerce
# composer install --no-dev
```

Activate in WordPress admin:
1. Go to **Plugins → Installed Plugins**
2. Find "B2Brouter for WooCommerce"
3. Click "Activate"

### Step 7: Configure B2Brouter Plugin

1. Go to **B2Brouter → Settings**
2. Enter your B2Brouter API key
3. Select invoice mode (automatic/manual)
4. Click "Save Settings"

---

## Option 2: Nginx + PHP-FPM

**Advantages**: More production-like, better performance, supports multiple sites.

### Prerequisites

```bash
sudo apt update
sudo apt install -y nginx php-cli php-fpm php-mysql php-xml php-mbstring \
    php-curl php-zip php-gd php-intl mariadb-server
```

### Step 1: Configure PHP-FPM

Check PHP version:
```bash
php -v  # e.g., PHP 8.2.x
```

Edit PHP-FPM config:
```bash
sudo vim /etc/php/8.2/fpm/php.ini
```

Update:
```ini
upload_max_filesize = 64M
post_max_size = 64M
max_execution_time = 300
memory_limit = 256M
```

Restart PHP-FPM:
```bash
sudo systemctl restart php8.2-fpm
```

### Step 2: Install WordPress

Same as Option 1, Steps 1-2 (database + WordPress download).

### Step 3: Configure Nginx

Create site configuration:
```bash
sudo vim /etc/nginx/sites-available/wordpress-local
```

Add configuration:
```nginx
server {
    listen 127.0.0.1:8000;
    server_name localhost;

    root /home/YOUR_USERNAME/local-wordpress/wordpress;
    index index.php index.html;

    access_log /var/log/nginx/wordpress-access.log;
    error_log /var/log/nginx/wordpress-error.log;

    # Prevent access from outside localhost
    allow 127.0.0.1;
    deny all;

    location / {
        try_files $uri $uri/ /index.php?$args;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.ht {
        deny all;
    }

    location = /favicon.ico {
        log_not_found off;
        access_log off;
    }

    location = /robots.txt {
        log_not_found off;
        access_log off;
        allow all;
    }

    location ~* \.(css|gif|ico|jpeg|jpg|js|png)$ {
        expires max;
        log_not_found off;
    }
}
```

**Important**: Replace `YOUR_USERNAME` with your actual username.

Enable site:
```bash
sudo ln -s /etc/nginx/sites-available/wordpress-local /etc/nginx/sites-enabled/
sudo nginx -t  # Test configuration
sudo systemctl restart nginx
```

### Step 4: Set Permissions

```bash
# Make nginx user (www-data) able to read files
sudo chown -R $USER:www-data ~/local-wordpress/wordpress
sudo find ~/local-wordpress/wordpress -type d -exec chmod 755 {} \;
sudo find ~/local-wordpress/wordpress -type f -exec chmod 644 {} \;

# Make uploads and cache writable
sudo chmod -R 775 ~/local-wordpress/wordpress/wp-content
```

### Step 5: Complete Setup

Follow Steps 4-7 from Option 1 (WordPress installation, WooCommerce, B2Brouter plugin).

Access at: http://localhost:8000

---

## Post-Installation: Plugin Setup

### Creating Test Products in WooCommerce

1. Go to **Products → Add New**
2. Create a test product:
   - Name: "Test Product"
   - Regular price: 50.00
   - **Publish**

### Creating Test Orders

1. Go to **WooCommerce → Orders → Add New**
2. Add customer billing details
3. Add products
4. Set order status to "Completed" to trigger automatic invoice generation (if enabled)

### Testing B2Brouter Plugin

1. **Manual Invoice Generation**:
   - Go to **WooCommerce → Orders**
   - Click on an order
   - Find "B2Brouter Invoice" meta box
   - Click "Generate Invoice"

2. **Automatic Invoice Generation**:
   - Set invoice mode to "automatic" in B2Brouter settings
   - Create new order
   - Change order status to "Completed"
   - Invoice should be generated automatically

3. **Bulk Invoice Generation**:
   - Go to **WooCommerce → Orders**
   - Select multiple orders (checkbox)
   - Bulk Actions → "Generate B2Brouter Invoices"
   - Click "Apply"

### Running Plugin Tests

```bash
cd ~/projects/ingent/b2b-woocommerce

# Run all tests
./vendor/bin/phpunit

# Run with coverage
./vendor/bin/phpunit --coverage-html tests/coverage

# View coverage report
# Open tests/coverage/index.html in browser
```

---

## Troubleshooting

### PHP Built-in Server Issues

**Problem**: Cannot access http://localhost:8000

**Solutions**:
```bash
# Check if server is running
ps aux | grep php

# Check if port is in use
sudo netstat -tlnp | grep 8000

# Try different port
php -S localhost:8080
```

**Problem**: Database connection error

**Solutions**:
```bash
# Check MariaDB is running
sudo systemctl status mariadb

# Test database connection
mysql -u wpuser -pwppass123 wordpress_local

# Check wp-config.php database credentials
```

### Nginx Issues

**Problem**: 502 Bad Gateway

**Solutions**:
```bash
# Check PHP-FPM is running
sudo systemctl status php8.2-fpm

# Check PHP-FPM socket
ls -la /var/run/php/

# Check nginx error log
sudo tail -f /var/log/nginx/wordpress-error.log
```

**Problem**: 403 Forbidden

**Solutions**:
```bash
# Check file permissions
ls -la ~/local-wordpress/wordpress

# Fix permissions
sudo chown -R $USER:www-data ~/local-wordpress/wordpress
sudo chmod -R 755 ~/local-wordpress/wordpress
```

### WooCommerce Issues

**Problem**: WooCommerce setup wizard not appearing

**Solutions**:
1. Go to **WooCommerce → Settings**
2. Complete setup manually
3. Or reinstall WooCommerce plugin

### B2Brouter Plugin Issues

**Problem**: Plugin not showing in admin

**Solutions**:
```bash
# Check plugin directory
ls -la ~/local-wordpress/wordpress/wp-content/plugins/

# Check symbolic link is valid
ls -la ~/local-wordpress/wordpress/wp-content/plugins/b2brouter-woocommerce

# Check plugin main file exists
cat ~/local-wordpress/wordpress/wp-content/plugins/b2brouter-woocommerce/b2brouter-woocommerce.php
```

**Problem**: Fatal error when activating plugin

**Solutions**:
```bash
# Install Composer dependencies
cd ~/projects/ingent/b2b-woocommerce
composer install

# Verify SDK is installed
composer show b2brouter/b2brouter-php
# Should show: versions : * 0.9.0

# Check PHP version compatibility
php -v  # Should be 7.4+

# Verify required PHP extensions
php -m | grep -E '(curl|json|mbstring)'

# Check WordPress debug log
tail -f ~/local-wordpress/wordpress/wp-content/debug.log
```

**Problem**: SDK not found or class not found errors

**Solutions**:
```bash
# The plugin now uses the official SDK from Packagist
# Remove old vendor directory and reinstall
cd ~/projects/ingent/b2b-woocommerce
rm -rf vendor/
composer clear-cache
composer install

# Verify autoloader is working
php -r "require 'vendor/autoload.php'; echo 'Autoloader OK\n';"
```

## Recommended Development Workflow

### Option 1 (PHP Server) - Best for Quick Testing

1. **Start development server**:
   ```bash
   cd ~/local-wordpress/wordpress
   php -S localhost:8000
   ```

2. **Make changes to plugin**:
   ```bash
   cd ~/projects/ingent/b2b-woocommerce/includes
   vim Settings.php  # Edit files
   ```

3. **Test changes**:
   - Refresh browser (http://localhost:8000)
   - Check functionality
   - Check debug.log for errors

4. **Run tests**:
   ```bash
   cd ~/projects/ingent/b2b-woocommerce
   ./vendor/bin/phpunit
   ```

### Option 2 (Nginx) - Best for Production-like Testing

Same workflow as Option 1, but nginx runs as a service (always on).

---

## Quick Reference

### Start/Stop Commands

**PHP Built-in Server**:
```bash
# Start
cd ~/local-wordpress/wordpress && php -S localhost:8000

# Stop: Ctrl+C
```

**Nginx**:
```bash
sudo systemctl start nginx
sudo systemctl stop nginx
sudo systemctl restart nginx
```

**Docker**:
```bash
cd ~/wordpress-docker
docker-compose up -d     # Start
docker-compose down      # Stop
docker-compose restart   # Restart
```

### URLs

- **WordPress**: http://localhost:8000
- **Admin**: http://localhost:8000/wp-admin
- **B2Brouter Settings**: http://localhost:8000/wp-admin/admin.php?page=b2brouter

### Database Access

**MariaDB (native)**:
```bash
mysql -u wpuser -pwppass123 wordpress_local
```

**MariaDB (Docker)**:
```bash
docker exec -it wordpress_db mysql -u wpuser -pwppass123 wordpress
```

---

## Security Notes

⚠️ **These setups are for LOCAL DEVELOPMENT ONLY**

- Simple passwords are used for convenience
- Services bound to localhost (127.0.0.1) only
- Do NOT expose these to the internet
- Do NOT use in production

---

## Next Steps

1. ✅ Set up local environment (choose one option above)
2. ✅ Install WordPress, WooCommerce, B2Brouter plugin
3. ✅ Create test products and orders
4. ✅ Test invoice generation
5. 🔧 Make plugin changes and test
6. ✅ Run unit tests
7. 🚀 Deploy to production when ready

---

## SDK Version Management

The plugin uses the official B2Brouter PHP SDK from Packagist.

### Current SDK Version

```bash
# Check installed version
cd ~/projects/ingent/b2b-woocommerce
composer show b2brouter/b2brouter-php
```

Current configuration in `composer.json`:
```json
{
    "require": {
        "b2brouter/b2brouter-php": "^0.9.0"
    }
}
```

This means it will accept any version `>=0.9.0` and `<1.0.0`.

### Updating the SDK

```bash
# Update to latest compatible version
cd ~/projects/ingent/b2b-woocommerce
composer update b2brouter/b2brouter-php

# Check for available updates
composer outdated b2brouter/b2brouter-php
```

### SDK Resources

- **Packagist Package**: https://packagist.org/packages/b2brouter/b2brouter-php
- **GitHub Repository**: https://github.com/B2Brouter/b2brouter-php
- **SDK Documentation**: Check the GitHub repository README

---

## Additional Resources

- **WordPress Codex**: https://codex.wordpress.org/
- **WooCommerce Docs**: https://woocommerce.com/documentation/
- **B2Brouter API**: https://developer.b2brouter.net
- **B2Brouter PHP SDK**: https://github.com/B2Brouter/b2brouter-php

---

**Need Help?**

Check the Troubleshooting section or refer to:
- WordPress debug log: `wp-content/debug.log`
- Nginx error log: `/var/log/nginx/wordpress-error.log`
- PHP error log: `/var/log/php8.2-fpm.log`
