server {
    listen 80;
    server_name {{ $domain }};
    
    root {{ $root_path }};
    index index.php index.html;
    
    # Access and error logs
    access_log {{ $logs_path }}/access.log;
    error_log {{ $logs_path }}/error.log;
    
    # WordPress permalink structure
    location / {
        try_files $uri $uri/ /index.php?$args;
    }
    
    # PHP-FPM configuration
    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:{{ $php_fpm_socket }};
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_param PATH_INFO $fastcgi_path_info;
        fastcgi_buffers 16 16k;
        fastcgi_buffer_size 32k;
    }
    
    # Deny access to sensitive files
    location ~ /\.ht {
        deny all;
    }
    
    location ~ /\.git {
        deny all;
    }
    
    location = /xmlrpc.php {
        deny all;
    }
    
    location ~ /wp-config\.php {
        deny all;
    }
    
    # Cache static files
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|webp|woff|woff2|ttf|eot)$ {
        expires max;
        log_not_found off;
        access_log off;
    }
    
    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header X-Content-Type-Options "nosniff" always;
    
    # Increase upload size limit
    client_max_body_size 64M;
}
