<?php
$db_path = '/opt/blackhole/var/www/db/blackhole.sq3';
if (!is_dir(dirname($db_path))) {
    mkdir(dirname($db_path), 0775, true);
}
$db = new SQLite3($db_path);
$db->exec("CREATE TABLE IF NOT EXISTS blocks (id INTEGER PRIMARY KEY AUTOINCREMENT, ip_address TEXT NOT NULL, type TEXT NOT NULL, reason TEXT, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, expires_at DATETIME)");
$db->exec("CREATE TABLE IF NOT EXISTS whitelist (id INTEGER PRIMARY KEY AUTOINCREMENT, ip_address TEXT NOT NULL, type TEXT NOT NULL, description TEXT, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
$db->exec("CREATE TABLE IF NOT EXISTS logs (id INTEGER PRIMARY KEY AUTOINCREMENT, timestamp DATETIME DEFAULT CURRENT_TIMESTAMP, action TEXT NOT NULL, ip_address TEXT, details TEXT)");
$db->exec("CREATE TABLE IF NOT EXISTS peers (id INTEGER PRIMARY KEY AUTOINCREMENT, ip_address TEXT NOT NULL, as_number INTEGER NOT NULL, description TEXT, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
$db->exec("CREATE TABLE IF NOT EXISTS settings (key TEXT PRIMARY KEY, value TEXT NOT NULL)");
$db->exec("INSERT OR IGNORE INTO settings (key, value) VALUES ('router_id', '1.1.1.1'), ('local_as', '65000')");

// Migration for existing databases to add 'reason' column if it doesn't exist
$res = $db->query("PRAGMA table_info(blocks)");
$has_reason = false;
while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
    if ($row['name'] === 'reason') {
        $has_reason = true;
        break;
    }
}
if (!$has_reason) {
    $db->exec("ALTER TABLE blocks ADD COLUMN reason TEXT");
}

echo "Database initialized at $db_path\n";

// Trigger initial BIRD config generation
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/BirdManager.php';
$dbClass = new Database($db_path);
$bird = new BirdManager($dbClass);
if ($bird->updateConfig()) {
    echo "BIRD configured successfully.\n";
} else {
    echo "BIRD configuration failed. This is expected if BIRD is not yet installed or sudoers not set up.\n";
}
?>
