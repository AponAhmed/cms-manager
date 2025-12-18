# WordPress Provisioning System

A Laravel 12 application that automatically provisions WordPress sites on AWS EC2 using only Free Tier resources.

## Features

✅ **Automated WordPress Provisioning** - Full WordPress installation with WP-CLI  
✅ **AWS Free Tier Safe** - Single EC2 instance with local MySQL  
✅ **Multi-Site Support** - Host multiple WordPress sites on one EC2  
✅ **Route53 DNS Management** - Automatic A record creation  
✅ **Real-Time Logs** - Monitor provisioning progress live  
✅ **Secure by Default** - Encrypted credentials, SSH-only access  
✅ **Complete Cleanup** - Reversible operations with destroy functionality  

## Tech Stack

- **Backend:** Laravel 12
- **Frontend:** React + Inertia.js
- **UI:** shadcn/ui + Tailwind CSS
- **Infrastructure:** AWS EC2 + Route53
- **Automation:** WP-CLI + Shell Scripts

## Requirements

- PHP 8.2+
- Node.js 18+
- Composer
- AWS Account (Free Tier)
- Domain configured in Route53

## Installation

### 1. Clone and Install

```bash
cd /home/apon/cms-manager
composer install
npm install
cp .env.example .env
php artisan key:generate
```

### 2. Configure Environment

Edit `.env` and set:

```env
# AWS Credentials
AWS_ACCESS_KEY_ID=your_access_key
AWS_SECRET_ACCESS_KEY=your_secret_key
AWS_DEFAULT_REGION=us-east-1
AWS_ROUTE53_HOSTED_ZONE_ID=your_hosted_zone_id

# EC2 Configuration
EC2_PUBLIC_IP=your_elastic_ip
EC2_SSH_KEY_PATH=/home/apon/.ssh/ec2_wordpress
EC2_SSH_USER=ec2-user

# MySQL (on EC2)
MYSQL_ROOT_PASSWORD=your_mysql_root_password

# WordPress Defaults
WP_ADMIN_EMAIL=admin@example.com

# Queue
QUEUE_CONNECTION=database
```

### 3. Run Migrations

```bash
php artisan migrate
```

### 4. Build Frontend

```bash
npm run build
# or for development
npm run dev
```

### 5. Start Queue Worker

```bash
php artisan queue:work --tries=3 --timeout=300
```

## EC2 Setup

Follow the comprehensive [EC2 Setup Guide](docs/EC2_SETUP.md) to prepare your EC2 instance.

Quick checklist:
- ✅ Launch t2.micro EC2 instance
- ✅ Allocate Elastic IP
- ✅ Install Nginx, PHP-FPM, MariaDB
- ✅ Install WP-CLI
- ✅ Configure SSH key access
- ✅ Set up Route53 hosted zone

## Usage

### Provision a New WordPress Site

1. **Access Dashboard**
   ```
   http://your-laravel-app.com/sites
   ```

2. **Click "Provision New Site"**

3. **Fill in Details:**
   - Domain name (e.g., example.com)
   - WordPress admin username
   - WordPress admin email
   - WordPress admin password

4. **Submit** - Provisioning takes 3-5 minutes

5. **Monitor Logs** - View real-time progress

### Destroy a Site

1. Go to site details page
2. Click "Destroy Site"
3. Confirm deletion
4. System will:
   - Remove Nginx config
   - Delete WordPress files
   - Drop database and user
   - Delete DNS record

## Architecture

```
┌─────────────────┐
│  Laravel App    │
│  (React + UI)   │
└────────┬────────┘
         │
         │ SSH + AWS SDK
         │
         ▼
┌─────────────────┐      ┌──────────────┐
│   EC2 Instance  │◄────►│   Route53    │
│                 │      │  (DNS Mgmt)  │
│  - Nginx        │      └──────────────┘
│  - PHP-FPM      │
│  - MySQL        │
│  - WP Sites     │
└─────────────────┘
```

## Job Pipeline

Each site provisioning triggers 8 sequential jobs:

1. **ValidateDomainJob** - Check domain format and availability
2. **PrepareFilesystemJob** - Create directory structure
3. **CreateDatabaseJob** - Create MySQL database and user
4. **InstallWordPressJob** - Install WordPress via WP-CLI
5. **ConfigureNginxJob** - Generate and upload Nginx vhost
6. **ReloadNginxJob** - Reload Nginx to apply changes
7. **UpdateDnsJob** - Create Route53 A record
8. **VerifySiteJob** - Verify site is accessible

## Security Features

- 🔒 Encrypted database credentials (Laravel encryption)
- 🔒 SSH key-based authentication
- 🔒 WordPress security hardening:
  - XML-RPC disabled
  - File editing disabled
  - Default plugins removed
  - Strong password requirements
- 🔒 Nginx security headers
- 🔒 Minimal AWS IAM permissions

## AWS Free Tier Compliance

This system is designed to stay within AWS Free Tier limits:

- ✅ **Single t2.micro EC2** - 750 hours/month free
- ✅ **No RDS** - Uses local MySQL instead
- ✅ **No Load Balancers** - Direct EC2 access
- ✅ **No NAT Gateway** - Not required
- ✅ **Route53** - 50 records free, $0.50/hosted zone/month

⚠️ **Note:** Route53 hosted zones incur a small monthly charge ($0.50/zone).

## Development

```bash
# Run development server with queue worker
npm run dev & php artisan queue:work

# Run tests
php artisan test

# Format code
./vendor/bin/pint
```

## File Structure

```
├── app/
│   ├── Jobs/              # Provisioning jobs
│   ├── Models/            # Eloquent models
│   ├── Services/          # SSH and Route53 services
│   └── Http/Controllers/  # Site controller
├── config/
│   └── wordpress.php      # WordPress config
├── database/
│   └── migrations/        # Database migrations
├── resources/
│   ├── js/
│   │   └── pages/sites/   # React components
│   └── views/templates/   # Nginx templates
└── docs/
    └── EC2_SETUP.md       # EC2 setup guide
```

## Troubleshooting

### Queue not processing
```bash
php artisan queue:restart
php artisan queue:work --verbose
```

### SSH connection failed
```bash
# Test SSH manually
ssh -i ~/.ssh/ec2_wordpress ec2-user@YOUR_IP

# Check permissions
chmod 600 ~/.ssh/ec2_wordpress
```

### DNS not updating
```bash
# Check Route53 hosted zone ID
php artisan tinker
>>> Configuration::get('aws_route53_hosted_zone_id');
```

### Site not accessible
- Verify Nginx is running on EC2
- Check DNS propagation: `dig example.com`
- Check Nginx logs: `sudo tail /var/log/nginx/error.log`

## Contributing

This is a custom provisioning system. Modify as needed for your use case.

## License

MIT License - See LICENSE file

## Credits

Built with:
- [Laravel](https://laravel.com)
- [React](https://react.dev)
- [Inertia.js](https://inertiajs.com)
- [shadcn/ui](https://ui.shadcn.com)
- [WP-CLI](https://wp-cli.org)
- [AWS SDK](https://aws.amazon.com/sdk-for-php/)
- [phpseclib](https://phpseclib.com)
