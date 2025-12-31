<?php

return [
    /*
    |--------------------------------------------------------------------------
    | AWS SDK Configuration
    |--------------------------------------------------------------------------
    */
    'access_key_id' => env('AWS_ACCESS_KEY_ID'),
    'secret_access_key' => env('AWS_SECRET_ACCESS_KEY'),
    'default_region' => env('AWS_DEFAULT_REGION', 'us-east-1'),

    /*
    |--------------------------------------------------------------------------
    | EC2 Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for per-site EC2 instance provisioning
    |
    */
    'ec2' => [
        // Amazon Linux 2 AMI (update for your region if needed)
        'ami_id' => env('AWS_EC2_AMI_ID', 'ami-0c02fb55956c7d316'),
        'instance_type' => env('AWS_EC2_INSTANCE_TYPE', 't3.micro'),
        'key_name_prefix' => 'cms-manager-',
        'security_group_name' => env('AWS_EC2_SECURITY_GROUP_NAME', 'cms-manager-wordpress'),
    ],

    /*
    |--------------------------------------------------------------------------
    | SSH Configuration
    |--------------------------------------------------------------------------
    |
    | SSH settings for connecting to EC2 instances
    |
    */
    'ssh' => [
        'key_storage_path' => storage_path('app/ssh-keys'),
        'user' => 'ec2-user',
        'connection_timeout' => 30,
        'max_retries' => 10,
        'retry_delay' => 30, // seconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Route53 Configuration
    |--------------------------------------------------------------------------
    |
    | DNS management settings (optional - leave empty to skip DNS)
    |
    */
    'route53' => [
        'hosted_zone_id' => env('AWS_ROUTE53_HOSTED_ZONE_ID'),
        'ttl' => 300,
        'propagation_timeout' => 300,
        'max_wait_attempts' => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | WordPress Configuration
    |--------------------------------------------------------------------------
    |
    | Default WordPress installation settings
    |
    */
    'wordpress' => [
        'admin_email' => env('WP_ADMIN_EMAIL', 'admin@example.com'),
        'version' => 'latest',
        'theme' => 'twentytwentyfour',
        'plugins_to_remove' => ['akismet', 'hello'],
        'paths' => [
            'base' => '/var/www',
            'nginx_available' => '/etc/nginx/sites-available',
            'nginx_enabled' => '/etc/nginx/sites-enabled',
            'php_fpm_socket' => '/var/run/php/php-fpm.sock',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Settings
    |--------------------------------------------------------------------------
    */
    'security' => [
        'min_password_length' => 32,
        'disable_xmlrpc' => true,
        'disable_file_edit' => true,
        'mysql_root_password_length' => 24,
    ],
];
