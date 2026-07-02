<?php
require_once 'includes/Database.php';

$db = new Database();
$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_whitelist'])) {
        $ip = trim($_POST['ip_address']);
        $desc = trim($_POST['description']);

        // Basic check for IP or CIDR
        if (filter_var(explode('/', $ip)[0], FILTER_VALIDATE_IP)) {
            $type = filter_var(explode('/', $ip)[0], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ? 'IPv6' : 'IPv4';
            $db->execute("INSERT INTO whitelist (ip_address, type, description) VALUES (:ip, :type, :desc)", [
                ':ip' => $ip,
                ':type' => $type,
                ':desc' => $desc
            ]);
            $db->logAction("Add Whitelist", $ip, $desc);
            $message = "IP/Range added to whitelist.";
        } else {
            $message = "Invalid IP/Range provided.";
        }
    } elseif (isset($_POST['remove_whitelist'])) {
        $id = (int)$_POST['id'];
        $ip = $_POST['ip_address'];
        $db->execute("DELETE FROM whitelist WHERE id = :id", [':id' => $id]);
        $db->logAction("Remove Whitelist", $ip, "Manual removal.");
        $message = "Whitelist entry removed.";
    }
}

$whitelist = $db->fetchAll("SELECT * FROM whitelist ORDER BY created_at DESC");

include 'templates/header.php';
?>

<h2>Whitelist (Never Block)</h2>
<?php if ($message): ?>
    <div class="alert alert-info"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-header">Add IP or Range (CIDR)</div>
    <div class="card-body">
        <form method="POST" class="row g-3">
            <div class="col-md-4">
                <input type="text" name="ip_address" class="form-control" placeholder="IP (e.g. 1.1.1.1) or CIDR (e.g. 1.1.0.0/24)" required>
            </div>
            <div class="col-md-5">
                <input type="text" name="description" class="form-control" placeholder="Description">
            </div>
            <div class="col-md-3">
                <button type="submit" name="add_whitelist" class="btn btn-success w-100">Add to Whitelist</button>
            </div>
        </form>
    </div>
</div>

<table class="table table-striped">
    <thead class="table-dark">
        <tr>
            <th>IP / Range</th>
            <th>Type</th>
            <th>Description</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($whitelist as $item): ?>
            <tr>
                <td><?php echo htmlspecialchars($item['ip_address']); ?></td>
                <td><?php echo $item['type']; ?></td>
                <td><?php echo htmlspecialchars($item['description']); ?></td>
                <td>
                    <form method="POST">
                        <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                        <input type="hidden" name="ip_address" value="<?php echo $item['ip_address']; ?>">
                        <button type="submit" name="remove_whitelist" class="btn btn-sm btn-danger">Remove</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php include 'templates/footer.php'; ?>
