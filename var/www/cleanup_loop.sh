#!/bin/bash
while true; do
    /usr/bin/php /var/www/cleanup.php >> /var/log/blackhole_cleanup.log 2>&1
    sleep 60
done
