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
        $peers_file = '/etc/bird/dynamic/peers.conf';

        // Update Blackholes
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

        // Update Peers
        $peers_content = "";
        $results = $db->query("SELECT * FROM peers");
        $i = 1;
        while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
            $peers_content .= "protocol bgp peer" . $i . " from rr_clients {\n";
            $peers_content .= "    neighbor " . $row['ip_address'] . " as " . $row['as_number'] . ";\n";
            $peers_content .= "    description \"" . addslashes($row['description']) . "\";\n";
            $peers_content .= "}\n\n";
            $i++;
        }
        file_put_contents($peers_file, $peers_content);

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
            // Deduplication
            $stmt = $db->prepare("SELECT id FROM blocks WHERE ip_address = :ip AND (expires_at > DATETIME('now') OR expires_at IS NULL)");
            $stmt->bindValue(':ip', $ip, SQLITE3_TEXT);
            $check = $stmt->execute()->fetchArray();

            if ($check) {
                $message = "IP address is already blackholed.";
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
            $stmt = $db->prepare("SELECT id FROM whitelist WHERE ip_address = :ip");
            $stmt->bindValue(':ip', $ip, SQLITE3_TEXT);
            if ($stmt->execute()->fetchArray()) {
                $message = "IP already in whitelist.";
            } else {
                $stmt = $db->prepare("INSERT INTO whitelist (ip_address, type, description) VALUES (:ip, :type, :desc)");
                $stmt->bindValue(':ip', $ip, SQLITE3_TEXT);
                $stmt->bindValue(':type', $type, SQLITE3_TEXT);
                $stmt->bindValue(':desc', $desc, SQLITE3_TEXT);
                $stmt->execute();
                logAction("Add Whitelist", $ip, $desc);
                $message = "Added to whitelist.";
            }
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
    } elseif (isset($_POST['add_peer'])) {
        $ip = trim($_POST['ip_address']);
        $as = (int)$_POST['as_number'];
        $desc = trim($_POST['description']);
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            $stmt = $db->prepare("INSERT INTO peers (ip_address, as_number, description) VALUES (:ip, :as, :desc)");
            $stmt->bindValue(':ip', $ip, SQLITE3_TEXT);
            $stmt->bindValue(':as', $as, SQLITE3_INTEGER);
            $stmt->bindValue(':desc', $desc, SQLITE3_TEXT);
            $stmt->execute();
            logAction("Add Peer", $ip, "AS: $as, $desc");
            updateBird();
            $message = "Peer added.";
        } else {
            $message = "Invalid Peer IP.";
        }
    } elseif (isset($_POST['remove_peer'])) {
        $id = (int)$_POST['peer_id'];
        $stmt = $db->prepare("SELECT ip_address FROM peers WHERE id = :id");
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
        if ($row) {
            $stmt = $db->prepare("DELETE FROM peers WHERE id = :id");
            $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
            $stmt->execute();
            logAction("Remove Peer", $row['ip_address']);
            updateBird();
            $message = "Peer removed.";
        }
    } elseif (isset($_POST['update_peer'])) {
        $id = (int)$_POST['peer_id'];
        $as = (int)$_POST['as_number'];
        $desc = trim($_POST['description']);
        $stmt = $db->prepare("UPDATE peers SET as_number = :as, description = :desc WHERE id = :id");
        $stmt->bindValue(':as', $as, SQLITE3_INTEGER);
        $stmt->bindValue(':desc', $desc, SQLITE3_TEXT);
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        $stmt->execute();
        logAction("Update Peer", null, "ID: $id, AS: $as, $desc");
        updateBird();
        $message = "Peer updated.";
    }
}

$active_blocks = $db->query("SELECT * FROM blocks WHERE expires_at > DATETIME('now') OR expires_at IS NULL ORDER BY created_at DESC");
$whitelist = $db->query("SELECT * FROM whitelist ORDER BY created_at DESC");
$peers = $db->query("SELECT * FROM peers ORDER BY created_at DESC");
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
        body { font-family: sans-serif; margin: 20px; background-color: #f9f9f9; }
        .container { max-width: 1000px; margin: auto; background: white; padding: 20px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        table { border-collapse: collapse; width: 100%; margin-bottom: 20px; }
        th, td { border: 1px solid #eee; padding: 10px; text-align: left; }
        th { background-color: #f4f4f4; color: #333; }
        .message { padding: 15px; background-color: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; margin-bottom: 20px; border-radius: 4px; }
        h2 { border-bottom: 2px solid #007bff; padding-bottom: 10px; color: #007bff; }
        .form-group { margin-bottom: 15px; }
        input[type="text"], input[type="number"] { padding: 8px; border: 1px solid #ccc; border-radius: 4px; }
        button { padding: 8px 15px; background-color: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; }
        button:hover { background-color: #0056b3; }
        button.remove { background-color: #dc3545; }
        button.remove:hover { background-color: #c82333; }
        button.extend { background-color: #28a745; }
        button.extend:hover { background-color: #218838; }
        button.update { background-color: #ffc107; color: black; }
        button.update:hover { background-color: #e0a800; }
    </style>
</head>
<body>
    <div class="container">
        <h1>BGP Blackhole Manager</h1>

        <?php if ($message): ?>
            <div class="message"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <h2>Add New Blackhole (30 mins)</h2>
        <form method="POST" class="form-group">
            <input type="text" name="ip_address" placeholder="IPv4 or IPv6 Address" required size="40">
            <button type="submit" name="add_block">Blackhole IP</button>
        </form>

        <h2>Active Blackholes</h2>
        <table>
            <tr>
                <th>IP Address</th>
                <th>Type</th>
                <th>Expires At</th>
                <th>Actions</th>
            </tr>
            <?php while ($row = $active_blocks->fetchArray(SQLITE3_ASSOC)): ?>
            <tr>
                <td><strong><?php echo htmlspecialchars($row['ip_address']); ?></strong></td>
                <td><?php echo $row['type']; ?></td>
                <td><?php echo $row['expires_at']; ?></td>
                <td>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="block_id" value="<?php echo $row['id']; ?>">
                        <button type="submit" name="extend_block" class="extend">Extend 30m</button>
                        <button type="submit" name="remove_block" class="remove">Remove</button>
                    </form>
                </td>
            </tr>
            <?php endwhile; ?>
        </table>

        <h2>BGP Peers (Route Reflectors)</h2>
        <form method="POST" class="form-group">
            <input type="text" name="ip_address" placeholder="Peer IP" required>
            <input type="number" name="as_number" placeholder="AS Number" required>
            <input type="text" name="description" placeholder="Description">
            <button type="submit" name="add_peer">Add Peer</button>
        </form>
        <table>
            <tr>
                <th>IP Address</th>
                <th>AS Number</th>
                <th>Description</th>
                <th>Actions</th>
            </tr>
            <?php while ($row = $peers->fetchArray(SQLITE3_ASSOC)): ?>
            <tr>
                <form method="POST">
                    <td><?php echo htmlspecialchars($row['ip_address']); ?></td>
                    <td><input type="number" name="as_number" value="<?php echo $row['as_number']; ?>" style="width:80px;"></td>
                    <td><input type="text" name="description" value="<?php echo htmlspecialchars($row['description']); ?>"></td>
                    <td>
                        <input type="hidden" name="peer_id" value="<?php echo $row['id']; ?>">
                        <button type="submit" name="update_peer" class="update">Update</button>
                        <button type="submit" name="remove_peer" class="remove">Remove</button>
                    </td>
                </form>
            </tr>
            <?php endwhile; ?>
        </table>

        <h2>Whitelist (Never Block)</h2>
        <form method="POST" class="form-group">
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
                        <button type="submit" name="remove_whitelist" class="remove">Remove</button>
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
                <td><small><?php echo $row['timestamp']; ?></small></td>
                <td><?php echo htmlspecialchars($row['action']); ?></td>
                <td><?php echo htmlspecialchars($row['ip_address']); ?></td>
                <td><?php echo htmlspecialchars($row['details']); ?></td>
            </tr>
            <?php endwhile; ?>
        </table>
    </div>
</body>
</html>
