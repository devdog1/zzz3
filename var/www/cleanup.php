<?php
/**
 * Background cleanup script to expire blackholes
 */

require_once '/var/www/html/includes/Database.php';
require_once '/var/www/html/includes/BirdManager.php';

$db = new Database();
$bird = new BirdManager($db);

echo "Starting cleanup at " . date('Y-m-d H:i:s') . " UTC\n";

$res = $db->query("SELECT * FROM blocks WHERE expires_at < DATETIME('now') AND expires_at IS NOT NULL");
$expired = [];
while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
    $expired[] = $row;
}

if (!empty($expired)) {
    foreach ($expired as $block) {
        $db->execute("DELETE FROM blocks WHERE id = :id", [':id' => $block['id']]);
        $db->logAction('Expired Block', $block['ip_address'], 'Automatic cleanup');
        echo "Expired: " . $block['ip_address'] . "\n";
    }
    if ($bird->updateConfig()) {
        echo "BIRD reloaded successfully.\n";
    } else {
        echo "BIRD reload failed.\n";
    }
} else {
    echo "No expired blocks found.\n";
}
