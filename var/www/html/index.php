<?php
$db_path = '/var/www/db/blackhole.sq3';
$db = new SQLite3($db_path);

if (!function_exists('logAction')) {
    function logAction($action, $ip = null, $details = null) {
        global $db;
        $stmt = $db->prepare("INSERT INTO logs (action, ip_address, details) VALUES (:action, :ip, :details)");
        $stmt->bindValue(':action', $action, SQLITE3_TEXT);
        $stmt->bindValue(':ip', $ip, SQLITE3_TEXT);
        $stmt->bindValue(':details', $details, SQLITE3_TEXT);
        $stmt->execute();
    }
}

if (!function_exists('updateBird')) {
    function updateBird() {
        global $db;
        $v4_file = '/etc/bird/dynamic/blackholes_v4.conf';
        $v6_file = '/etc/bird/dynamic/blackholes_v6.conf';

        $v4_content = "";
        $v6_content = "";

        $results = $db->query("SELECT ip_address, type FROM blocks WHERE expires_at > DATETIME('now') OR expires_at IS NULL");
        while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
            if ($row['type'] === 'IPv4') {
                $v4_content .= "route " . $row['ip_address'] . "/32 blackhole;\n";
            } else {
                $v6_content .= "route " . $row['ip_address'] . "/128 blackhole;\n";
            }
        }

        file_put_contents($v4_file, $v4_content);
        file_put_contents($v6_file, $v6_content);

        exec('sudo /usr/sbin/birdc configure', $output, $return_var);
        return $return_var === 0;
    }
}

if (!function_exists('isWhitelisted')) {
    function isWhitelisted($ip) {
        global $db;
        $stmt = $db->prepare("SELECT id FROM whitelist WHERE ip_address = :ip");
        $stmt->bindValue(':ip', $ip, SQLITE3_TEXT);
        $result = $stmt->execute();
        return $result->fetchArray() !== false;
    }
}

$message = "";

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_block'])) {
        $ip = trim($_POST['ip_address']);
        $type = "";
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $type = "IPv4";
        } elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $type = "IPv6";
        }

        if (!$type) {
            $message = "Invalid IP address.";
        } elseif (isWhitelisted($ip)) {
            $message = "IP address is whitelisted and cannot be blocked.";
        } else {
            $expires_at = date('Y-m-d H:i:s', strtotime('+30 minutes'));
            $stmt = $db->prepare("INSERT INTO blocks (ip_address, type, expires_at) VALUES (:ip, :type, :expires)");
            $stmt->bindValue(':ip', $ip, SQLITE3_TEXT);
            $stmt->bindValue(':type', $type, SQLITE3_TEXT);
            $stmt->bindValue(':expires', $expires_at, SQLITE3_TEXT);
            $stmt->execute();

            logAction("Add Block", $ip, "Expires at: $expires_at");
            if (updateBird()) {
                $message = "Block added successfully for $ip.";
            } else {
                $message = "Block added to DB but failed to update BIRD.";
            }
        }
    } elseif (isset($_POST['remove_block'])) {
        $id = (int)$_POST['block_id'];
        $stmt = $db->prepare("SELECT ip_address FROM blocks WHERE id = :id");
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

        if ($row) {
            $stmt = $db->prepare("DELETE FROM blocks WHERE id = :id");
            $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
            $stmt->execute();
            logAction("Remove Block", $row['ip_address']);
            updateBird();
            $message = "Block removed.";
        }
    } elseif (isset($_POST['extend_block'])) {
        $id = (int)$_POST['block_id'];
        $stmt = $db->prepare("SELECT ip_address, expires_at FROM blocks WHERE id = :id");
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

        if ($row) {
            $new_expiry = date('Y-m-d H:i:s', strtotime($row['expires_at'] . ' +30 minutes'));
            $stmt = $db->prepare("UPDATE blocks SET expires_at = :expires WHERE id = :id");
            $stmt->bindValue(':expires', $new_expiry, SQLITE3_TEXT);
            $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
            $stmt->execute();
            logAction("Extend Block", $row['ip_address'], "New expiry: $new_expiry");
            $message = "Block extended.";
        }
    } elseif (isset($_POST['add_whitelist'])) {
        $ip = trim($_POST['ip_address']);
        $desc = trim($_POST['description']);
        $type = "";
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $type = "IPv4";
        } elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $type = "IPv6";
        }

        if ($type) {
            $stmt = $db->prepare("INSERT INTO whitelist (ip_address, type, description) VALUES (:ip, :type, :desc)");
            $stmt->bindValue(':ip', $ip, SQLITE3_TEXT);
            $stmt->bindValue(':type', $type, SQLITE3_TEXT);
            $stmt->bindValue(':desc', $desc, SQLITE3_TEXT);
            $stmt->execute();
            logAction("Add Whitelist", $ip, $desc);
            $message = "Added to whitelist.";
        } else {
            $message = "Invalid IP for whitelist.";
        }
    } elseif (isset($_POST['remove_whitelist'])) {
        $id = (int)$_POST['whitelist_id'];
        $stmt = $db->prepare("SELECT ip_address FROM whitelist WHERE id = :id");
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
        if ($row) {
            $stmt = $db->prepare("DELETE FROM whitelist WHERE id = :id");
            $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
            $stmt->execute();
            logAction("Remove Whitelist", $row['ip_address']);
            $message = "Removed from whitelist.";
        }
    }
}

$active_blocks = $db->query("SELECT * FROM blocks WHERE expires_at > DATETIME('now') OR expires_at IS NULL ORDER BY created_at DESC");
$whitelist = $db->query("SELECT * FROM whitelist ORDER BY created_at DESC");
$logs = $db->query("SELECT * FROM logs ORDER BY timestamp DESC LIMIT 50");

if (defined('CLI_CLEANUP') && CLI_CLEANUP) {
    return;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>BGP Blackhole Manager</title>
    <style>
        body { font-family: sans-serif; margin: 20px; }
        table { border-collapse: collapse; width: 100%; margin-bottom: 20px; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
        th { background-color: #f4f4f4; }
        .message { padding: 10px; background-color: #e7f3fe; border: 1px solid #b6d4fe; margin-bottom: 20px; }
        h2 { border-bottom: 2px solid #333; padding-bottom: 5px; }
    </style>
</head>
<body>
    <h1>BGP Blackhole Manager</h1>

    <?php if ($message): ?>
        <div class="message"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <h2>Add New Block (30 mins)</h2>
    <form method="POST">
        <input type="text" name="ip_address" placeholder="IPv4 or IPv6 Address" required>
        <button type="submit" name="add_block">Blackhole IP</button>
    </form>

    <h2>Active Blocks</h2>
    <table>
        <tr>
            <th>IP Address</th>
            <th>Type</th>
            <th>Added At</th>
            <th>Expires At</th>
            <th>Actions</th>
        </tr>
        <?php while ($row = $active_blocks->fetchArray(SQLITE3_ASSOC)): ?>
        <tr>
            <td><?php echo htmlspecialchars($row['ip_address']); ?></td>
            <td><?php echo $row['type']; ?></td>
            <td><?php echo $row['created_at']; ?></td>
            <td><?php echo $row['expires_at']; ?></td>
            <td>
                <form method="POST" style="display:inline;">
                    <input type="hidden" name="block_id" value="<?php echo $row['id']; ?>">
                    <button type="submit" name="extend_block">Extend 30m</button>
                    <button type="submit" name="remove_block">Remove</button>
                </form>
            </td>
        </tr>
        <?php endwhile; ?>
    </table>

    <h2>Whitelist (Never Block)</h2>
    <form method="POST" style="margin-bottom: 10px;">
        <input type="text" name="ip_address" placeholder="IP Address" required>
        <input type="text" name="description" placeholder="Description">
        <button type="submit" name="add_whitelist">Add to Whitelist</button>
    </form>
    <table>
        <tr>
            <th>IP Address</th>
            <th>Type</th>
            <th>Description</th>
            <th>Actions</th>
        </tr>
        <?php while ($row = $whitelist->fetchArray(SQLITE3_ASSOC)): ?>
        <tr>
            <td><?php echo htmlspecialchars($row['ip_address']); ?></td>
            <td><?php echo $row['type']; ?></td>
            <td><?php echo htmlspecialchars($row['description']); ?></td>
            <td>
                <form method="POST">
                    <input type="hidden" name="whitelist_id" value="<?php echo $row['id']; ?>">
                    <button type="submit" name="remove_whitelist">Remove</button>
                </form>
            </td>
        </tr>
        <?php endwhile; ?>
    </table>

    <h2>Recent Logs</h2>
    <table>
        <tr>
            <th>Timestamp</th>
            <th>Action</th>
            <th>IP Address</th>
            <th>Details</th>
        </tr>
        <?php while ($row = $logs->fetchArray(SQLITE3_ASSOC)): ?>
        <tr>
            <td><?php echo $row['timestamp']; ?></td>
            <td><?php echo htmlspecialchars($row['action']); ?></td>
            <td><?php echo htmlspecialchars($row['ip_address']); ?></td>
            <td><?php echo htmlspecialchars($row['details']); ?></td>
        </tr>
        <?php endwhile; ?>
    </table>
</body>
</html>
