<?php
// To avoid outputting HTML when included
define('CLI_CLEANUP', true);
include '/var/www/html/index.php';

echo "Starting cleanup at " . date('Y-m-d H:i:s') . "\n";

$results = $db->query("SELECT ip_address FROM blocks WHERE expires_at <= DATETIME('now') AND expires_at IS NOT NULL");
$expired_count = 0;
while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
    $ip = $row['ip_address'];
    logAction("Expired Block", $ip, "Automatic cleanup");
    echo "Expiring $ip\n";
    $expired_count++;
}

if ($expired_count > 0) {
    $db->exec("DELETE FROM blocks WHERE expires_at <= DATETIME('now') AND expires_at IS NOT NULL");
    if (updateBird()) {
        echo "Successfully updated BIRD after removing $expired_count expired blocks.\n";
    } else {
        echo "Failed to update BIRD after cleanup.\n";
    }
} else {
    echo "No expired blocks found.\n";
}
