<?php
require_once 'includes/Database.php';
require_once 'includes/BirdManager.php';

$db = new Database();
$bird = new BirdManager($db);
$message = "";
$message_type = "info";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_settings'])) {
        $router_id = trim($_POST['router_id']);
        $local_as = (int)$_POST['local_as'];
        $db->execute("INSERT OR REPLACE INTO settings (key, value) VALUES ('router_id', :router_id)", [':router_id' => $router_id]);
        $db->execute("INSERT OR REPLACE INTO settings (key, value) VALUES ('local_as', :local_as)", [':local_as' => $local_as]);
        $db->logAction("Update Settings", null, "Router ID: $router_id, Local AS: $local_as");
        if ($bird->updateConfig()) {
            $message = "Settings updated and BIRD reloaded.";
            $message_type = "success";
        } else {
            $message = "Settings saved, but BIRD reload failed.";
            $message_type = "danger";
        }
    }
}

$router_id = $db->getSetting('router_id', '1.1.1.1');
$local_as = $db->getSetting('local_as', '65000');

include 'templates/header.php';
?>

<h2>Global BIRD Settings</h2>
<?php if ($message): ?>
    <div class="alert alert-<?php echo $message_type; ?>"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>

<div class="card col-md-6">
    <div class="card-body">
        <form method="POST">
            <div class="mb-3">
                <label class="form-label">Router ID:</label>
                <input type="text" name="router_id" class="form-control" value="<?php echo htmlspecialchars($router_id); ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Local AS:</label>
                <input type="number" name="local_as" class="form-control" value="<?php echo htmlspecialchars($local_as); ?>" required>
            </div>
            <button type="submit" name="update_settings" class="btn btn-primary">Save Settings & Reload BIRD</button>
        </form>
    </div>
</div>

<?php include 'templates/footer.php'; ?>
