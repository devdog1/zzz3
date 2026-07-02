#!/bin/bash
# setup_permissions.sh - Run as root

REPO_DIR="/opt/blackhole"

# Ensure repo exists
if [ ! -d "$REPO_DIR" ]; then
    echo "Error: $REPO_DIR does not exist. Run migration first."
    exit 1
fi

# Create dynamic bird directories if missing
mkdir -p "$REPO_DIR/etc/bird/dynamic"

# Create DB directory if missing
mkdir -p "$REPO_DIR/var/www/db"

# Create dummy config files for bird if missing
touch "$REPO_DIR/etc/bird/dynamic/peers.conf"
touch "$REPO_DIR/etc/bird/dynamic/global.conf"
touch "$REPO_DIR/etc/bird/bird_static.conf"
touch "$REPO_DIR/etc/bird/bird_static_v6.conf"

# Set ownership
# Web user needs write access to bird dynamic configs and DB
chown -R www-data:www-data "$REPO_DIR/etc/bird/dynamic"
chown www-data:www-data "$REPO_DIR/etc/bird/bird_static.conf"
chown www-data:www-data "$REPO_DIR/etc/bird/bird_static_v6.conf"
chown -R www-data:www-data "$REPO_DIR/var/www/db"
chown -R www-data:www-data "$REPO_DIR/var/www/html"

# Permissions
chmod -R 775 "$REPO_DIR/etc/bird/dynamic"
chmod 664 "$REPO_DIR/etc/bird/bird_static.conf"
chmod 664 "$REPO_DIR/etc/bird/bird_static_v6.conf"
chmod -R 775 "$REPO_DIR/var/www/db"
chmod -R 775 "$REPO_DIR/var/www/html"

# Set up symbolic links
echo "Setting up symbolic links..."

# Apache link
if [ -d "/var/www/html" ] && [ ! -L "/var/www/html" ]; then
    mv /var/www/html /var/www/html.bak
fi
ln -sfn "$REPO_DIR/var/www/html" /var/www/html

# BIRD link
if [ -d "/etc/bird" ] && [ ! -L "/etc/bird" ]; then
    mv /etc/bird /etc/bird.bak
fi
ln -sfn "$REPO_DIR/etc/bird" /etc/bird

# Sudoers
cp "$REPO_DIR/etc/sudoers.d/www-data-birdc" /etc/sudoers.d/www-data-birdc
chmod 440 /etc/sudoers.d/www-data-birdc

echo "Permissions and links set up successfully."
