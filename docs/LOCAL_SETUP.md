# Local Development Setup Guide

This guide walks you through setting up your local environment for WordPress provisioning.

## Prerequisites

Before you begin,ensure you have the following installed on your Ubuntu/Debian system:

### 1. Install Nginx

```bash
sudo apt update
sudo apt install nginx -y
```

### 2. Install PHP 8.2 and Extensions

```bash
sudo apt install php8.2-fpm php8.2-cli php8.2-mysql php8.2-xml \
  php8.2-mbstring php8.2-curl php8.2-zip php8.2-gd php8.2-intl \
  php8.2-opcache -y
```

### 3. Install MySQL/MariaDB

```bash
sudo apt install mysql-server -y
sudo mysql_secure_installation
```

Remember the root password you set during installation!

### 4. Install WP-CLI

```bash
curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
chmod +x wp-cli.phar
sudo mv wp-cli.phar /usr/local/bin/wp

# Verify installation
wp --info
```

## Configuration

### 1. Configure Nginx

Create sites directories:

```bash
sudo mkdir -p /etc/nginx/sites-available
sudo mkdir -p /etc/nginx/sites-enabled
```

Edit `/etc/nginx/nginx.conf` and add this line inside the `http{}` block:

```nginx
include /etc/nginx/sites-enabled/*.conf;
```

Test and reload:

```bash
sudo nginx -t
sudo systemctl reload nginx
```

### 2. Configure PHP-FPM

Edit `/etc/php/8.2/fpm/pool.d/www.conf`:

```bash
sudo nano /etc/php/8.2/fpm/pool.d/www.conf
```

Ensure these settings:

```ini
user = www-data
group = www-data
listen = /var/run/php/php-fpm.sock
listen.owner = www-data
listen.group = www-data
```

Restart PHP-FPM:

```bash
sudo systemctl restart php8.2-fpm
```

### 3. Create WordPress Base Directory

```bash
sudo mkdir -p /var/www
sudo chown $USER:www-data /var/www
sudo chmod 755 /var/www
```

### 4. Configure MySQL

Set root password (if not already set):

```bash
sudo mysql
ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY 'your_password';
FLUSH PRIVILEGES;
EXIT;
```

### 5. Configure Laravel .env

Copy `.env.example` to `.env`:

```bash
cp .env.example .env
```

Update these settings in `.env`:

```env
PROVISIONING_MODE=local

LOCAL_NGINX_SITES_AVAILABLE=/etc/nginx/sites-available
LOCAL_NGINX_SITES_ENABLED=/etc/nginx/sites-enabled
LOCAL_WORDPRESS_BASE_PATH=/var/www
LOCAL_MYSQL_HOST=127.0.0.1
LOCAL_MYSQL_ROOT_USER=root
LOCAL_MYSQL_ROOT_PASSWORD=your_password
LOCAL_DOMAIN_SUFFIX=.test
LOCAL_PHP_FPM_SOCKET=/var/run/php/php-fpm.sock
```

## Start Services

### 1. Run Migrations

```bash
php artisan migrate
```

### 2. Start Queue Worker

In a separate terminal:

```bash
php artisan queue:work --tries=3
```

### 3. Start Development Server

In another terminal:

```bash
npm run dev
```

### 4. Access Application

Visit: http://localhost:8000

## Create Your First Local Site

1. Click "WordPress Sites" in the sidebar
2. Click "Provision New Site"
3. Enter a domain name (e.g., `mysite`)
   - The `.test` suffix will be added automatically
4. Fill in WordPress admin credentials
5. Click "Provision Site"

The site will be created at `/var/www/mysite.test/` and accessible at `http://mysite.test`

## Troubleshooting

### sudo Password Prompts

The system needs sudo access to:
- Write Nginx configs to `/etc/nginx/`
- Add domains to `/etc/hosts`
- Reload Nginx

Make sure your user has sudo privileges:

```bash
sudo visudo
# Add: your_username ALL=(ALL) NOPASSWD: ALL
```

### Permission Issues

If you get permission errors:

```bash
# Fix /var/www ownership
sudo chown -R $USER:www-data /var/www

# Fix PHP-FPM socket
sudo chown www-data:www-data /var/run/php/php-fpm.sock
```

### Site Not Accessible

Check /etc/hosts:

```bash
cat /etc/hosts | grep ".test"
```

You should see entries like:
```
127.0.0.1	mysite.test
```

### Nginx Errors

Check Nginx logs:

```bash
sudo tail -f /var/log/nginx/error.log
```

Test config:

```bash
sudo nginx -t
```

### MySQL Connection Issues

Test MySQL connection:

```bash
mysql -u root -p -e "SHOW DATABASES;"
```

### PHP-FPM Not Running

```bash
sudo systemctl status php8.2-fpm
sudo systemctl restart php8.2-fpm
```

## Cleanup/Testing

### Destroy a Site

Use the "Destroy Site" button in the dashboard. This will:
- Remove Nginx config
- Delete WordPress files
- Drop MySQL database
- Remove /etc/hosts entry

### Manual Cleanup (if needed)

```bash
# Remove Nginx config
sudo rm /etc/nginx/sites-enabled/mysite.test.conf
sudo rm /etc/nginx/sites-available/mysite.test.conf
sudo systemctl reload nginx

# Remove files
sudo rm -rf /var/www/mysite.test

# Drop database
mysql -u root -p -e "DROP DATABASE wp_mysite_test;"

# Remove from hosts
sudo sed -i '/mysite.test/d' /etc/hosts
```

## Tips

- **Fast Testing:** Local mode is much faster than AWS (no SSH, no DNS wait)
- **Multiple Sites:** Create as many test sites as you want
- **Offline:** Works without internet connection
- **Debug:** Direct access to files and logs in `/var/www/`

## Next Steps

Once local testing is complete, switch to AWS mode:

1. Set up EC2 instance (see `docs/EC2_SETUP.md`)
2. Change `.env`: `PROVISIONING_MODE=aws`
3. Configure AWS credentials
4. Test provisioning on AWS

---

**Happy local WordPress development!** 🚀
