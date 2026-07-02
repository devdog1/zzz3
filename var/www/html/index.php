<?php
require_once 'includes/Database.php';
require_once 'includes/BirdManager.php';
require_once 'includes/Utils.php';

$db = new Database();
$bird = new BirdManager($db);
$message = "";
$message_type = "info";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_block'])) {
        $ip = trim($_POST['ip_address']);
        $reason = trim($_POST['reason']);
        $duration = $_POST['duration'] ?? '+30 minutes';
        $type = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ? 'IPv6' : 'IPv4';

        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            // Check whitelist with CIDR support
            $whitelist = $db->fetchAll("SELECT ip_address FROM whitelist");
            $is_whitelisted = false;
            foreach ($whitelist as $item) {
                if (Utils::ipInRange($ip, $item['ip_address'])) {
                    $is_whitelisted = true;
                    break;
                }
            }

            if ($is_whitelisted) {
                $message = "IP address is whitelisted and cannot be blocked.";
                $message_type = "danger";
            } else {
                $db->execute("INSERT INTO blocks (ip_address, type, reason, expires_at) VALUES (:ip, :type, :reason, DATETIME('now', :duration))", [
                    ':ip' => $ip,
                    ':type' => $type,
                    ':reason' => $reason,
                    ':duration' => $duration
                ]);
                $db->logAction("Add Block", $ip, "Reason: $reason. Duration: $duration.");
                if ($bird->updateConfig()) {
                    $message = "IP address blackholed successfully.";
                    $message_type = "success";
                } else {
                    $message = "IP added to DB, but BIRD configuration failed.";
                    $message_type = "warning";
                }
            }
        } else {
            $message = "Invalid IP address provided.";
            $message_type = "danger";
        }
    } elseif (isset($_POST['remove_block'])) {
        $id = (int)$_POST['id'];
        $ip = $_POST['ip_address'];
        $db->execute("DELETE FROM blocks WHERE id = :id", [':id' => $id]);
        $db->logAction("Remove Block", $ip, "Manual removal.");
        $bird->updateConfig();
        $message = "IP address block removed.";
        $message_type = "success";
    } elseif (isset($_POST['extend_block'])) {
        $id = (int)$_POST['id'];
        $ip = $_POST['ip_address'];
        $db->execute("UPDATE blocks SET expires_at = DATETIME(expires_at, '+30 minutes') WHERE id = :id", [':id' => $id]);
        $db->logAction("Extend Block", $ip, "Extended by 30 minutes.");
        $message = "IP address block extended.";
        $message_type = "success";
    }
}

$active_blocks = $db->fetchAll("SELECT *, datetime(expires_at, 'localtime') as expires_local FROM blocks WHERE expires_at > DATETIME('now') OR expires_at IS NULL ORDER BY created_at DESC");

include 'templates/header.php';
?>

<h2>Active Blackholes</h2>
<?php if ($message): ?>
    <div class="alert alert-<?php echo $message_type; ?>"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-header">Add New Blackhole</div>
    <div class="card-body">
        <form method="POST" class="row g-3">
            <div class="col-md-3">
                <input type="text" name="ip_address" class="form-control" placeholder="IPv4 or IPv6 Address" required>
            </div>
            <div class="col-md-3">
                <input type="text" name="reason" class="form-control" placeholder="Reason for blackholing">
            </div>
            <div class="col-md-3">
                <select name="duration" class="form-select">
                    <option value="+30 minutes">30 Minutes</option>
                    <option value="+1 hour">1 Hour</option>
                    <option value="+6 hours">6 Hours</option>
                    <option value="+12 hours">12 Hours</option>
                    <option value="+1 day">1 Day</option>
                    <option value="+7 days">7 Days</option>
                    <option value="+30 days">30 Days</option>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" name="add_block" class="btn btn-danger w-100">Blackhole IP</button>
            </div>
        </form>
    </div>
</div>

<table class="table table-striped table-hover">
    <thead class="table-dark">
        <tr>
            <th>IP Address</th>
            <th>Type</th>
            <th>Reason</th>
            <th>Expires (Local)</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($active_blocks)): ?>
            <tr><td colspan="5" class="text-center">No active blackholes.</td></tr>
        <?php else: ?>
            <?php foreach ($active_blocks as $block): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($block['ip_address']); ?></strong></td>
                    <td><?php echo $block['type']; ?></td>
                    <td><?php echo htmlspecialchars($block['reason']); ?></td>
                    <td><?php echo $block['expires_local'] ?: 'Never'; ?></td>
                    <td>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="id" value="<?php echo $block['id']; ?>">
                            <input type="hidden" name="ip_address" value="<?php echo $block['ip_address']; ?>">
                            <button type="submit" name="extend_block" class="btn btn-sm btn-warning">Extend 30m</button>
                            <button type="submit" name="remove_block" class="btn btn-sm btn-outline-danger">Remove</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>

<?php include 'templates/footer.php'; ?>
