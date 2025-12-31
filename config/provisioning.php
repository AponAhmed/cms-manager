<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Provisioning Mode
    |--------------------------------------------------------------------------
    |
    | Determines which infrastructure to use for WordPress provisioning.
    | Options: 'local' (for development) or 'aws' (for production)
    |
    */
    'mode' => env('PROVISIONING_MODE', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Local Development Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for provisioning WordPress sites on your local machine
    |
    */
    'local' => [
        'enabled' => env('PROVISIONING_MODE', 'local') === 'local',
        
        'nginx' => [
            'sites_available' => env('LOCAL_NGINX_SITES_AVAILABLE', '/etc/nginx/sites-available'),
            'sites_enabled' => env('LOCAL_NGINX_SITES_ENABLED', '/etc/nginx/sites-enabled'),
        ],
        
        'wordpress_base' => env('LOCAL_WORDPRESS_BASE_PATH', '/var/www'),
        
        'mysql' => [
            'host' => env('LOCAL_MYSQL_HOST', '127.0.0.1'),
            'root_user' => env('LOCAL_MYSQL_ROOT_USER', 'root'),
            'root_password' => env('LOCAL_MYSQL_ROOT_PASSWORD', 'root'),
        ],
        
        'domain_suffix' => env('LOCAL_DOMAIN_SUFFIX', '.test'),
        'php_fpm_socket' => env('LOCAL_PHP_FPM_SOCKET', '/var/run/php/php-fpm.sock'),
    ],

    /*
    |--------------------------------------------------------------------------
    | AWS Configuration
    |--------------------------------------------------------------------------
    |
    | AWS mode settings - detailed EC2/SSH config is in config/aws.php
    |
    */
    'aws' => [
        'enabled' => env('PROVISIONING_MODE') === 'aws',
        
        // Skip DNS when APP_ENV is local (use EC2 public IP instead)
        'skip_dns' => env('APP_ENV', 'local') === 'local',
    ],
];
