#!/bin/bash
# Set up directories and permissions
mkdir -p /etc/bird/dynamic
chown www-data:www-data /etc/bird/dynamic
chmod 775 /etc/bird/dynamic

# Shared config files
touch /etc/bird/dynamic/peers.conf
touch /etc/bird/dynamic/global.conf
touch /etc/bird_static.conf
touch /etc/bird_static_v6.conf

chown www-data:www-data /etc/bird/dynamic/peers.conf /etc/bird/dynamic/global.conf /etc/bird_static.conf /etc/bird_static_v6.conf
chmod 664 /etc/bird/dynamic/peers.conf /etc/bird/dynamic/global.conf /etc/bird_static.conf /etc/bird_static_v6.conf

mkdir -p /var/www/db
chown www-data:www-data /var/www/db
chmod 775 /var/www/db

# Bird config permissions
chown bird:bird /etc/bird/bird.conf
chmod 644 /etc/bird/bird.conf
chmod 755 /etc/bird/

# Sudoers
cp etc/sudoers.d/www-data-birdc /etc/sudoers.d/www-data-birdc
chmod 440 /etc/sudoers.d/www-data-birdc

echo "Permissions and directories set up."
