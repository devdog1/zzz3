# BGP Blackhole & Peer Management System

A PHP/Apache2/BIRD-based system for managing BGP blackholes with automated expiry, whitelist support, and BGP Peer (Route Reflector) management.

## Features

- **Dynamic Blackholing**: Add IPv4 (/32) or IPv6 (/128) addresses to be blackholed via BGP.
- **Automated Expiry**: Blocks are set to 30 minutes by default.
- **Whitelist (Never Block)**: Protect critical infrastructure from accidental blackholing.
- **Peer Management**: Add, remove, and update BGP Route Reflectors via the web interface.
- **BGP Community Support**: Routes are tagged with BGP community `65000:666`.
- **Action Logging**: All user actions and automated events are logged to an SQLite database.
- **Safety**: BIRD is configured to import nothing from the kernel and only export specific blackhole prefixes.

## Prerequisites

- Ubuntu 22.04+ (or compatible Linux distribution)
- Apache2 with `libapache2-mod-php`
- PHP 8.x with `php-sqlite3`
- BIRD2
- Sudo access for the web user (`www-data`) to reload BIRD.

## Installation

### 1. Install Dependencies

```bash
sudo apt-get update
sudo apt-get install -y apache2 php libapache2-mod-php php-sqlite3 bird2 sqlite3
```

### 2. Set Up Files and Permissions

Clone this repository and run the setup script:

```bash
# Set up directories and permissions
sudo bash setup/setup_permissions.sh

# Initialize the database
sudo php setup/init_db.php

# Deploy web files
sudo cp var/www/html/index.php /var/www/html/index.php
sudo chown www-data:www-data /var/www/html/index.php

# Deploy BIRD configuration
sudo cp etc/bird/bird.conf /etc/bird/bird.conf
sudo service bird restart
```

### 3. Configure Sudoers

The web application needs to run `birdc configure` to apply changes. A sudoers file is provided in `etc/sudoers.d/www-data-birdc`.

```bash
sudo cp etc/sudoers.d/www-data-birdc /etc/sudoers.d/www-data-birdc
sudo chmod 440 /etc/sudoers.d/www-data-birdc
```

### 4. Enable Automated Cleanup

A background loop handles the removal of expired blocks. You can run it as a background process:

```bash
sudo cp var/www/cleanup.php /var/www/cleanup.php
sudo cp var/www/cleanup_loop.sh /var/www/cleanup_loop.sh
sudo chmod +x /var/www/cleanup_loop.sh
# Run /var/www/cleanup_loop.sh in background
```

Alternatively, you can set up a cron job for `www-data`:
`* * * * * /usr/bin/php /var/www/cleanup.php >> /var/log/blackhole_cleanup.log 2>&1`

## Usage

1.  Navigate to `http://your-server-ip/index.php`.
2.  **Add Peer**: First, add your Route Reflectors in the "BGP Peers" section.
3.  **Blackhole IP**: Enter an IP address in the "Add New Blackhole" section.
4.  **Manage**: Use the "Active Blackholes" table to extend blocks by another 30 minutes or remove them manually.
5.  **Whitelist**: Add critical IPs to the "Whitelist" to prevent them from being blackholed.

## Configuration

- **BIRD Config**: Located at `/etc/bird/bird.conf`. Dynamic files are in `/etc/bird/dynamic/`.
- **Database**: SQLite3 database at `/var/www/db/blackhole.sq3`.
- **Logs**: Action logs are viewable at the bottom of the management page.

## Security Considerations

- It is highly recommended to protect the `/var/www/html/index.php` page with **Basic Auth** or limit access to specific management IPs via Apache configuration.
- Ensure the BGP community `65000:666` (configurable in `bird.conf`) is correctly handled by your Route Reflectors to trigger blackholing in your network.
