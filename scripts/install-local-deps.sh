#!/bin/bash

#################################################
# Local WordPress Provisioning - Dependency Installer
# Installs all required packages for local mode
#################################################

set -e

echo "========================================="
echo "Installing Local WordPress Provisioning Dependencies"
echo "========================================="
echo ""

# Check if running as root
if [ "$EUID" -eq 0 ]; then 
    echo "⚠️  Please do not run this script as root"
    echo "Run: ./scripts/install-local-deps.sh"
    exit 1
fi

# Update package list
echo "📦 Updating package list..."
sudo apt update

# Install Nginx
echo ""
echo "🌐 Installing Nginx..."
if ! command -v nginx &> /dev/null; then
    sudo apt install nginx -y
    echo "✅ Nginx installed"
else
    echo "✅ Nginx already installed"
fi

# Detect available PHP version
echo ""
echo "🐘 Detecting available PHP version..."
if apt-cache show php8.3-fpm &> /dev/null; then
    PHP_VERSION="8.3"
    echo "✅ Found PHP 8.3 in repositories"
elif apt-cache show php8.2-fpm &> /dev/null; then
    PHP_VERSION="8.2"
    echo "✅ Found PHP 8.2 in repositories"
else
    echo "⚠️  PHP 8.2/8.3 not found. Adding Ondřej Surý PPA..."
    sudo apt install -y software-properties-common
    sudo add-apt-repository ppa:ondrej/php -y
    sudo apt update
    PHP_VERSION="8.3"
fi

# Install PHP and extensions
echo ""
echo "🐘 Installing PHP ${PHP_VERSION} and extensions..."
sudo apt install -y \
    php${PHP_VERSION}-fpm \
    php${PHP_VERSION}-cli \
    php${PHP_VERSION}-mysql \
    php${PHP_VERSION}-xml \
    php${PHP_VERSION}-mbstring \
    php${PHP_VERSION}-curl \
    php${PHP_VERSION}-zip \
    php${PHP_VERSION}-gd \
    php${PHP_VERSION}-intl \
    php${PHP_VERSION}-opcache

echo "✅ PHP ${PHP_VERSION} installed"

# Install MySQL
echo ""
echo "🗄️  Installing MySQL Server..."
if ! command -v mysql &> /dev/null; then
    sudo apt install mysql-server -y
    echo "✅ MySQL installed"
    echo "⚠️  IMPORTANT: Run 'sudo mysql_secure_installation' to set root password"
else
    echo "✅ MySQL already installed"
fi

# Install WP-CLI
echo ""
echo "🔧 Installing WP-CLI..."
if ! command -v wp &> /dev/null; then
    cd /tmp
    curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
    chmod +x wp-cli.phar
    sudo mv wp-cli.phar /usr/local/bin/wp
    echo "✅ WP-CLI installed"
    wp --info
else
    echo "✅ WP-CLI already installed"
    wp --version
fi

# Configure Nginx directories
echo ""
echo "📁 Configuring Nginx directories..."
sudo mkdir -p /etc/nginx/sites-available
sudo mkdir -p /etc/nginx/sites-enabled

# Add include directive if not exists
if ! grep -q "include /etc/nginx/sites-enabled" /etc/nginx/nginx.conf; then
    echo "⚙️  Adding sites-enabled include to nginx.conf..."
    sudo sed -i '/http {/a \    include /etc/nginx/sites-enabled/*.conf;' /etc/nginx/nginx.conf
    echo "✅ Nginx includes configured"
else
    echo "✅ Nginx includes already configured"
fi

# Create WordPress base directory
echo ""
echo "📂 Creating WordPress base directory..."
sudo mkdir -p /var/www
sudo chown $USER:www-data /var/www
sudo chmod 755 /var/www
echo "✅ /var/www directory ready"

# Detect PHP-FPM socket path
PHP_FPM_SOCK="/var/run/php/php-fpm.sock"
if [ -f "/etc/php/${PHP_VERSION}/fpm/pool.d/www.conf" ]; then
    PHP_FPM_SOCK="/var/run/php/php${PHP_VERSION}-fpm.sock"
fi

# Configure PHP-FPM
echo ""
echo "🔌 Configuring PHP-FPM..."
if [ -f /etc/php/${PHP_VERSION}/fpm/pool.d/www.conf ]; then
    sudo sed -i "s|^listen = .*|listen = ${PHP_FPM_SOCK}|" /etc/php/${PHP_VERSION}/fpm/pool.d/www.conf
    sudo sed -i 's/^listen.owner = .*/listen.owner = www-data/' /etc/php/${PHP_VERSION}/fpm/pool.d/www.conf
    sudo sed -i 's/^listen.group = .*/listen.group = www-data/' /etc/php/${PHP_VERSION}/fpm/pool.d/www.conf
    echo "✅ PHP-FPM configured"
fi

# Create PHP socket directory if it doesn't exist
sudo mkdir -p /var/run/php
sudo chown www-data:www-data /var/run/php

# Start and enable services
echo ""
echo "🚀 Starting services..."
sudo systemctl enable nginx
sudo systemctl start nginx
sudo systemctl enable php${PHP_VERSION}-fpm
sudo systemctl restart php${PHP_VERSION}-fpm
sudo systemctl enable mysql
sudo systemctl start mysql

# Test Nginx
echo ""
echo "🧪 Testing Nginx..."
if sudo nginx -t &> /dev/null; then
    echo "✅ Nginx configuration is valid"
    sudo systemctl reload nginx
else
    echo "⚠️  Nginx configuration has errors"
    sudo nginx -t
fi

echo ""
echo "========================================="
echo "✅ Installation Complete!"
echo "========================================="
echo ""
echo "Installed versions:"
echo "- Nginx: $(nginx -v 2>&1 | cut -d'/' -f2)"
echo "- PHP: $(php -v | head -n1 | cut -d' ' -f2)"
echo "- MySQL: $(mysql --version | cut -d' ' -f6 | cut -d',' -f1)"
echo "- WP-CLI: $(wp --version | cut -d' ' -f2)"
echo ""
echo "📝 Next steps:"
echo ""
echo "1️⃣  Secure MySQL:"
echo "   sudo mysql_secure_installation"
echo "   (Set a strong root password and remember it!)"
echo ""
echo "2️⃣  Update .env file:"
echo "   cp .env.example .env"
echo "   nano .env"
echo ""
echo "   Set these values:"
echo "   PROVISIONING_MODE=local"
echo "   LOCAL_MYSQL_ROOT_PASSWORD=your_password"
echo "   LOCAL_PHP_FPM_SOCKET=${PHP_FPM_SOCK}"
echo ""
echo "3️⃣  Run Laravel migrations:"
echo "   php artisan migrate"
echo ""
echo "4️⃣  Start queue worker (in separate terminal):"
echo "   php artisan queue:work"
echo ""
echo "5️⃣  Start dev server (in another terminal):"
echo "   npm run dev"
echo ""
echo "6️⃣  Visit: http://localhost:8000/sites"
echo ""
echo "🎉 You're ready to provision WordPress sites locally!"
echo ""
