<?php
$db_path = '/var/www/db/blackhole.sq3';
if (!is_dir(dirname($db_path))) {
    mkdir(dirname($db_path), 0775, true);
    chown(dirname($db_path), 'www-data');
}
$db = new SQLite3($db_path);
$db->exec("CREATE TABLE IF NOT EXISTS blocks (id INTEGER PRIMARY KEY AUTOINCREMENT, ip_address TEXT NOT NULL, type TEXT NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, expires_at DATETIME)");
$db->exec("CREATE TABLE IF NOT EXISTS whitelist (id INTEGER PRIMARY KEY AUTOINCREMENT, ip_address TEXT NOT NULL, type TEXT NOT NULL, description TEXT, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
$db->exec("CREATE TABLE IF NOT EXISTS logs (id INTEGER PRIMARY KEY AUTOINCREMENT, timestamp DATETIME DEFAULT CURRENT_TIMESTAMP, action TEXT NOT NULL, ip_address TEXT, details TEXT)");
$db->exec("CREATE TABLE IF NOT EXISTS peers (id INTEGER PRIMARY KEY AUTOINCREMENT, ip_address TEXT NOT NULL, as_number INTEGER NOT NULL, description TEXT, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
chown($db_path, 'www-data');
echo "Database initialized at $db_path\n";
?>
