#!/bin/bash

# Script to configure sudoers for passwordless local provisioning
# Run this once: sudo ./scripts/configure-sudoers.sh

if [ "$EUID" -ne 0 ]; then 
    echo "Please run as root (sudo)"
    exit 1
fi

USER_NAME=${SUDO_USER:-$USER}

echo "Configuring sudoers for user: $USER_NAME"

# Create a new sudoers file for the project
cat > /etc/sudoers.d/cms-manager-local << EOF
# Allow $USER_NAME to run specific commands without password for CMS Manager
$USER_NAME ALL=(ALL) NOPASSWD: /usr/bin/touch
$USER_NAME ALL=(ALL) NOPASSWD: /usr/bin/mv
$USER_NAME ALL=(ALL) NOPASSWD: /usr/bin/rm
$USER_NAME ALL=(ALL) NOPASSWD: /usr/bin/ln
$USER_NAME ALL=(ALL) NOPASSWD: /usr/bin/tee
$USER_NAME ALL=(ALL) NOPASSWD: /usr/bin/sed
$USER_NAME ALL=(ALL) NOPASSWD: /usr/sbin/nginx -t
$USER_NAME ALL=(ALL) NOPASSWD: /usr/bin/systemctl reload nginx
$USER_NAME ALL=(ALL) NOPASSWD: /usr/bin/mkdir
$USER_NAME ALL=(ALL) NOPASSWD: /usr/bin/chown
$USER_NAME ALL=(ALL) NOPASSWD: /usr/bin/chmod
$USER_NAME ALL=(ALL) NOPASSWD: /usr/bin/mysql
EOF

chmod 440 /etc/sudoers.d/cms-manager-local

echo "✅ Sudoers configured! You can now run the queue worker without password prompts."
echo "Restart your queue worker now:"
echo "php artisan queue:work"
