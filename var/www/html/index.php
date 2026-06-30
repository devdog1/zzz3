<?php
require_once 'includes/Database.php';
require_once 'includes/BirdManager.php';

$db = new Database();
$bird = new BirdManager($db);
$message = "";
$message_type = "info";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_block'])) {
        $ip = trim($_POST['ip_address']);
        $type = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ? "IPv4" : (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ? "IPv6" : "");

        if (!$type) {
            $message = "Invalid IP address.";
            $message_type = "danger";
        } else {
            $stmt = $db->query("SELECT id FROM whitelist WHERE ip_address = :ip", [':ip' => $ip]);
            if ($stmt->fetchArray()) {
                $message = "IP address is whitelisted.";
                $message_type = "warning";
            } else {
                $stmt = $db->query("SELECT id FROM blocks WHERE ip_address = :ip AND (expires_at > DATETIME('now') OR expires_at IS NULL)", [':ip' => $ip]);
                if ($stmt->fetchArray()) {
                    $message = "IP already blackholed.";
                    $message_type = "warning";
                } else {
                    $expires_at = gmdate('Y-m-d H:i:s', strtotime('+30 minutes'));
                    $db->execute("INSERT INTO blocks (ip_address, type, expires_at) VALUES (:ip, :type, :expires)", [
                        ':ip' => $ip, ':type' => $type, ':expires' => $expires_at
                    ]);
                    $db->logAction("Add Block", $ip, "Expires at: $expires_at (UTC)");
                    $bird->updateConfig();
                    $message = "Block added successfully.";
                    $message_type = "success";
                }
            }
        }
    } elseif (isset($_POST['remove_block'])) {
        $id = (int)$_POST['block_id'];
        $row = $db->fetchOne("SELECT ip_address FROM blocks WHERE id = :id", [':id' => $id]);
        if ($row) {
            $db->execute("DELETE FROM blocks WHERE id = :id", [':id' => $id]);
            $db->logAction("Remove Block", $row['ip_address']);
            $bird->updateConfig();
            $message = "Block removed.";
            $message_type = "success";
        }
    } elseif (isset($_POST['extend_block'])) {
        $id = (int)$_POST['block_id'];
        $row = $db->fetchOne("SELECT ip_address, expires_at FROM blocks WHERE id = :id", [':id' => $id]);
        if ($row) {
            $new_expiry = gmdate('Y-m-d H:i:s', strtotime($row['expires_at'] . ' +30 minutes'));
            $db->execute("UPDATE blocks SET expires_at = :expires WHERE id = :id", [':expires' => $new_expiry, ':id' => $id]);
            $db->logAction("Extend Block", $row['ip_address'], "New expiry: $new_expiry (UTC)");
            $message = "Block extended.";
            $message_type = "success";
        }
    }
}

$active_blocks = $db->fetchAll("SELECT * FROM blocks WHERE expires_at > DATETIME('now') OR expires_at IS NULL ORDER BY created_at DESC");

include 'templates/header.php';
?>

<div class="row">
    <div class="col-md-12">
        <h2>Active Blackholes</h2>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <div class="card mb-4">
            <div class="card-header">Add New Blackhole (30 mins)</div>
            <div class="card-body">
                <form method="POST" class="row g-3">
                    <div class="col-auto">
                        <input type="text" name="ip_address" class="form-control" placeholder="IPv4 or IPv6 Address" required>
                    </div>
                    <div class="col-auto">
                        <button type="submit" name="add_block" class="btn btn-primary">Blackhole IP</button>
                    </div>
                </form>
            </div>
        </div>

        <table class="table table-striped table-hover">
            <thead class="table-dark">
                <tr>
                    <th>IP Address</th>
                    <th>Type</th>
                    <th>Expires At (UTC)</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($active_blocks as $row): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($row['ip_address']); ?></strong></td>
                    <td><span class="badge bg-secondary"><?php echo $row['type']; ?></span></td>
                    <td><?php echo $row['expires_at']; ?></td>
                    <td>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="block_id" value="<?php echo $row['id']; ?>">
                            <button type="submit" name="extend_block" class="btn btn-sm btn-success">Extend 30m</button>
                            <button type="submit" name="remove_block" class="btn btn-sm btn-danger">Remove</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($active_blocks)): ?>
                    <tr><td colspan="4" class="text-center">No active blackholes.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'templates/footer.php'; ?>
