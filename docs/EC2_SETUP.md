# EC2 Setup Guide for WordPress Provisioning

This guide will walk you through setting up an AWS EC2 instance to host multiple WordPress sites.

## Prerequisites

- AWS Free Tier account
- Domain name (configured in Route53)
- Basic understanding of Linux commands

## Step 1: Launch EC2 Instance

1. **Log in to AWS Console** and navigate to EC2
2. **Click "Launch Instance"**
3. **Configure instance:**
   - **Name:** wordpress-host
   - **AMI:** Amazon Linux 2023
   - **Instance type:** t2.micro (Free Tier eligible)
   - **Key pair:** Create new or use existing (download .pem file)
   - **Network settings:**
     - Allow SSH from your IP
     - Allow HTTP (port 80) from anywhere
     - Allow HTTPS (port 443) from anywhere
   - **Storage:** 8 GB gp3 (Free Tier default)

4. **Launch the instance**

## Step 2: Allocate Elastic IP

1. Go to **Elastic IPs** in the EC2 console
2. Click **Allocate Elastic IP address**
3. **Associate** the IP with your EC2 instance
4. **Note the public IP** for later use

## Step 3: Connect to EC2

```bash
# Set correct permissions for your key
chmod 400 /path/to/your-key.pem

# Connect to EC2
ssh -i /path/to/your-key.pem ec2-user@YOUR_ELASTIC_IP
```

## Step 4: Update System

```bash
sudo dnf update -y
```

## Step 5: Install Nginx

```bash
# Install Nginx
sudo dnf install nginx -y

# Start and enable Nginx
sudo systemctl start nginx
sudo systemctl enable nginx

# Verify installation
sudo systemctl status nginx
```

## Step 6: Install PHP 8.2

```bash
# Install PHP and required extensions
sudo dnf install php8.2 php8.2-fpm php8.2-mysqlnd php8.2-cli \
  php8.2-xml php8.2-mbstring php8.2-curl php8.2-zip \
  php8.2-gd php8.2-intl php8.2-opcache -y

# Start and enable PHP-FPM
sudo systemctl start php-fpm
sudo systemctl enable php-fpm

# Verify installation
php -v
```

## Step 7: Configure PHP-FPM

```bash
# Edit PHP-FPM www.conf
sudo nano /etc/php-fpm.d/www.conf
```

Update the following lines:

```ini
user = www-data
group = www-data
listen = /var/run/php/php-fpm.sock
listen.owner = www-data
listen.group = www-data
```

Create www-data user:

```bash
sudo groupadd www-data
sudo useradd -g www-data -s /sbin/nologin www-data
```

Create socket directory and restart:

```bash
sudo mkdir -p /var/run/php
sudo chown www-data:www-data /var/run/php
sudo systemctl restart php-fpm
```

## Step 8: Install MariaDB

```bash
# Install MariaDB
sudo dnf install mariadb105-server -y

# Start and enable MariaDB
sudo systemctl start mariadb
sudo systemctl enable mariadb

# Secure installation
sudo mysql_secure_installation
```

When prompted:
- Set root password (SAVE THIS!)
- Remove anonymous users: Yes
- Disallow root login remotely: Yes
- Remove test database: Yes
- Reload privilege tables: Yes

## Step 9: Configure Nginx

```bash
# Create sites-available and sites-enabled directories
sudo mkdir -p /etc/nginx/sites-available
sudo mkdir -p /etc/nginx/sites-enabled

# Edit nginx.conf
sudo nano /etc/nginx/nginx.conf
```

Add this line inside the `http` block (before the closing `}`):

```nginx
include /etc/nginx/sites-enabled/*.conf;
```

Test and reload:

```bash
sudo nginx -t
sudo systemctl reload nginx
```

## Step 10: Install WP-CLI

```bash
# Download WP-CLI
curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar

# Make executable
chmod +x wp-cli.phar

# Move to bin directory
sudo mv wp-cli.phar /usr/local/bin/wp

# Verify installation
wp --info
```

## Step 11: Create Base Directory

```bash
# Create directory for WordPress sites
sudo mkdir -p /var/www
sudo chown www-data:www-data /var/www
sudo chmod 755 /var/www
```

## Step 12: Configure SSH Access for Laravel

On your **Laravel server** (not EC2):

```bash
# Generate SSH key
ssh-keygen -t rsa -b 4096 -f ~/.ssh/ec2_wordpress

# Copy public key to EC2
ssh-copy-id -i ~/.ssh/ec2_wordpress.pub ec2-user@YOUR_ELASTIC_IP

# Test connection
ssh -i ~/.ssh/ec2_wordpress ec2-user@YOUR_ELASTIC_IP
```

## Step 13: Configure Route53

1. Go to **Route53** in AWS Console
2. **Create a hosted zone** for your domain (e.g., example.com)
3. **Note the Hosted Zone ID** (looks like: Z1234567890ABC)
4. **Update nameservers** at your domain registrar with the NS records from Route53

## Step 14: Configure Laravel Application

Update your `.env` file:

```env
AWS_ACCESS_KEY_ID=your_aws_access_key
AWS_SECRET_ACCESS_KEY=your_aws_secret_key
AWS_DEFAULT_REGION=us-east-1
AWS_ROUTE53_HOSTED_ZONE_ID=your_hosted_zone_id

EC2_PUBLIC_IP=your_elastic_ip
EC2_SSH_KEY_PATH=/home/apon/.ssh/ec2_wordpress
EC2_SSH_USER=ec2-user

MYSQL_ROOT_PASSWORD=your_mysql_root_password
WP_ADMIN_EMAIL=admin@example.com

QUEUE_CONNECTION=database
```

## Step 15: Run Laravel Migrations

```bash
cd /home/apon/cms-manager
php artisan migrate
```

## Step 16: Start Queue Worker

```bash
# Install supervisor (recommended for production)
sudo apt install supervisor -y

# Or run queue worker manually (for testing)
php artisan queue:work --tries=3 --timeout=300
```

## Step 17: Test Provisioning

1. Access your Laravel app
2. Create a new WordPress site
3. Monitor the provision logs
4. Verify the site is accessible

## Security Checklist

- ✅ SSH key-based authentication only
- ✅ Disable password authentication
- ✅ Firewall configured (Security Groups)
- ✅ Regular updates enabled
- ✅ Strong MySQL root password
- ✅ Limited AWS IAM permissions
- ✅ SSL certificates (future step)

## Troubleshooting

### Nginx not starting
```bash
sudo nginx -t  # Check configuration
sudo tail -f /var/log/nginx/error.log
```

### PHP-FPM socket issues
```bash
sudo systemctl status php-fpm
ls -la /var/run/php/
```

### MySQL connection issues
```bash
sudo systemctl status mariadb
sudo mysql -u root -p
```

### Laravel queue not processing
```bash
php artisan queue:failed  # Check failed jobs
php artisan queue:restart  # Restart workers
```

## Next Steps

- Set up SSL certificates with Let's Encrypt
- Configure automatic backups
- Set up monitoring and alerts
- Implement caching (Redis/Memcached)

---

**Cost Monitoring:** Regularly check AWS Billing Dashboard to ensure you stay within Free Tier limits.
