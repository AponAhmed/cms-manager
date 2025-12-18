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
    | Route53 Configuration
    |--------------------------------------------------------------------------
    */
    'route53_hosted_zone_id' => env('AWS_ROUTE53_HOSTED_ZONE_ID'),
];
