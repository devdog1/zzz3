<?php
require_once 'includes/Database.php';
require_once 'includes/BirdManager.php';

$db = new Database();
$bird = new BirdManager($db);
$message = "";
$message_type = "info";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_whitelist'])) {
        $ip = trim($_POST['ip_address']);
        $desc = trim($_POST['description']);
        $type = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ? "IPv4" : (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ? "IPv6" : "");
        if ($type) {
            $db->execute("INSERT INTO whitelist (ip_address, type, description) VALUES (:ip, :type, :desc)", [
                ':ip' => $ip, ':type' => $type, ':desc' => $desc
            ]);
            $db->logAction("Add Whitelist", $ip, $desc);
            $message = "Added to whitelist.";
            $message_type = "success";
        }
    } elseif (isset($_POST['remove_whitelist'])) {
        $id = (int)$_POST['whitelist_id'];
        $row = $db->fetchOne("SELECT ip_address FROM whitelist WHERE id = :id", [':id' => $id]);
        if ($row) {
            $db->execute("DELETE FROM whitelist WHERE id = :id", [':id' => $id]);
            $db->logAction("Remove Whitelist", $row['ip_address']);
            $message = "Removed from whitelist.";
            $message_type = "success";
        }
    }
}

$whitelist = $db->fetchAll("SELECT * FROM whitelist ORDER BY created_at DESC");

include 'templates/header.php';
?>

<h2>Whitelist (Never Block)</h2>
<?php if ($message): ?>
    <div class="alert alert-<?php echo $message_type; ?>"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-header">Add to Whitelist</div>
    <div class="card-body">
        <form method="POST" class="row g-3">
            <div class="col-md-4">
                <input type="text" name="ip_address" class="form-control" placeholder="IP Address" required>
            </div>
            <div class="col-md-5">
                <input type="text" name="description" class="form-control" placeholder="Description">
            </div>
            <div class="col-md-3">
                <button type="submit" name="add_whitelist" class="btn btn-primary w-100">Add to Whitelist</button>
            </div>
        </form>
    </div>
</div>

<table class="table table-striped">
    <thead class="table-dark">
        <tr>
            <th>IP Address</th>
            <th>Type</th>
            <th>Description</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($whitelist as $row): ?>
        <tr>
            <td><?php echo htmlspecialchars($row['ip_address']); ?></td>
            <td><span class="badge bg-info text-dark"><?php echo $row['type']; ?></span></td>
            <td><?php echo htmlspecialchars($row['description']); ?></td>
            <td>
                <form method="POST">
                    <input type="hidden" name="whitelist_id" value="<?php echo $row['id']; ?>">
                    <button type="submit" name="remove_whitelist" class="btn btn-sm btn-danger">Remove</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php include 'templates/footer.php'; ?>
