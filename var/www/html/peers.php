<?php
require_once 'includes/Database.php';
require_once 'includes/BirdManager.php';

$db = new Database();
$bird = new BirdManager($db);
$message = "";
$message_type = "info";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_peer'])) {
        $ip = trim($_POST['ip_address']);
        $as = (int)$_POST['as_number'];
        $desc = trim($_POST['description']);
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            $db->execute("INSERT INTO peers (ip_address, as_number, description) VALUES (:ip, :as, :desc)", [
                ':ip' => $ip, ':as' => $as, ':desc' => $desc
            ]);
            $db->logAction("Add Peer", $ip, "AS: $as, $desc");
            $bird->updateConfig();
            $message = "Peer added.";
            $message_type = "success";
        }
    } elseif (isset($_POST['remove_peer'])) {
        $id = (int)$_POST['peer_id'];
        $row = $db->fetchOne("SELECT ip_address FROM peers WHERE id = :id", [':id' => $id]);
        if ($row) {
            $db->execute("DELETE FROM peers WHERE id = :id", [':id' => $id]);
            $db->logAction("Remove Peer", $row['ip_address']);
            $bird->updateConfig();
            $message = "Peer removed.";
            $message_type = "success";
        }
    } elseif (isset($_POST['update_peer'])) {
        $id = (int)$_POST['peer_id'];
        $as = (int)$_POST['as_number'];
        $desc = trim($_POST['description']);
        $db->execute("UPDATE peers SET as_number = :as, description = :desc WHERE id = :id", [
            ':as' => $as, ':desc' => $desc, ':id' => $id
        ]);
        $db->logAction("Update Peer", null, "ID: $id, AS: $as");
        $bird->updateConfig();
        $message = "Peer updated.";
        $message_type = "success";
    }
}

$peers = $db->fetchAll("SELECT * FROM peers ORDER BY created_at DESC");

include 'templates/header.php';
?>

<h2>BGP Peers (Route Reflectors)</h2>
<?php if ($message): ?>
    <div class="alert alert-<?php echo $message_type; ?>"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-header">Add New Peer</div>
    <div class="card-body">
        <form method="POST" class="row g-3">
            <div class="col-md-3">
                <input type="text" name="ip_address" class="form-control" placeholder="Peer IP" required>
            </div>
            <div class="col-md-2">
                <input type="number" name="as_number" class="form-control" placeholder="AS Number" required>
            </div>
            <div class="col-md-4">
                <input type="text" name="description" class="form-control" placeholder="Description">
            </div>
            <div class="col-md-3">
                <button type="submit" name="add_peer" class="btn btn-primary w-100">Add Peer</button>
            </div>
        </form>
    </div>
</div>

<table class="table table-striped">
    <thead class="table-dark">
        <tr>
            <th>IP Address</th>
            <th>AS Number</th>
            <th>Description</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($peers as $row): ?>
        <tr>
            <form method="POST">
                <td><?php echo htmlspecialchars($row['ip_address']); ?></td>
                <td><input type="number" name="as_number" value="<?php echo $row['as_number']; ?>" class="form-control form-control-sm"></td>
                <td><input type="text" name="description" value="<?php echo htmlspecialchars($row['description']); ?>" class="form-control form-control-sm"></td>
                <td>
                    <input type="hidden" name="peer_id" value="<?php echo $row['id']; ?>">
                    <button type="submit" name="update_peer" class="btn btn-sm btn-warning">Update</button>
                    <button type="submit" name="remove_peer" class="btn btn-sm btn-danger">Remove</button>
                </td>
            </form>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php include 'templates/footer.php'; ?>
