<?php

return [
    /*
    |--------------------------------------------------------------------------
    | EC2 Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the EC2 instance that hosts WordPress sites
    |
    */
    'ec2' => [
        'ip' => env('EC2_PUBLIC_IP'),
        'ssh_key' => env('EC2_SSH_KEY_PATH', '/home/apon/.ssh/ec2_wordpress'),
        'ssh_user' => env('EC2_SSH_USER', 'ec2-user'),
    ],

    /*
    |--------------------------------------------------------------------------
    | MySQL Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for MySQL database on EC2
    |
    */
    'mysql' => [
        'root_password' => env('MYSQL_ROOT_PASSWORD'),
        'host' => 'localhost',
    ],

    /*
    |--------------------------------------------------------------------------
    | Server Paths
    |--------------------------------------------------------------------------
    |
    | Filesystem paths on the EC2 instance
    |
    */
    'paths' => [
        'base' => '/var/www',
        'nginx_available' => '/etc/nginx/sites-available',
        'nginx_enabled' => '/etc/nginx/sites-enabled',
        'php_fpm_socket' => '/var/run/php/php-fpm.sock',
    ],

    /*
    |--------------------------------------------------------------------------
    | WordPress Defaults
    |--------------------------------------------------------------------------
    |
    | Default settings for new WordPress installations
    |
    */
    'defaults' => [
        'wp_admin_email' => env('WP_ADMIN_EMAIL', 'admin@example.com'),
        'wp_version' => 'latest',
        'theme' => 'twentytwentyfour',
        'plugins_to_remove' => ['akismet', 'hello'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Settings
    |--------------------------------------------------------------------------
    |
    | Security-related configuration
    |
    */
    'security' => [
        'min_password_length' => 32,
        'disable_xmlrpc' => true,
        'disable_file_edit' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | DNS Settings
    |--------------------------------------------------------------------------
    |
    | DNS propagation and timeout settings
    |
    */
    'dns' => [
        'ttl' => 300,
        'propagation_timeout' => 300, // seconds
        'max_wait_attempts' => 30,
    ],
];
