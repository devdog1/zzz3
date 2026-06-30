#!/bin/bash
# Set up directories and permissions
mkdir -p /etc/bird/dynamic
chown www-data:www-data /etc/bird/dynamic
chmod 755 /etc/bird/dynamic
touch /etc/bird/dynamic/blackholes_v4.conf
touch /etc/bird/dynamic/blackholes_v6.conf
chown www-data:www-data /etc/bird/dynamic/blackholes_v4.conf /etc/bird/dynamic/blackholes_v6.conf
chmod 644 /etc/bird/dynamic/blackholes_v4.conf /etc/bird/dynamic/blackholes_v6.conf

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
