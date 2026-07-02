<?php
require_once 'includes/Database.php';
$db = new Database();
$logs = $db->fetchAll("SELECT *, datetime(timestamp, 'localtime') as local_time FROM logs ORDER BY timestamp DESC LIMIT 100");

include 'templates/header.php';
?>

<h2>Action Logs</h2>
<table class="table table-sm table-bordered">
    <thead class="table-secondary">
        <tr>
            <th>Timestamp (Local)</th>
            <th>Action</th>
            <th>IP Address</th>
            <th>Details</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($logs as $log): ?>
            <tr>
                <td><?php echo $log['local_time']; ?></td>
                <td><span class="badge bg-secondary"><?php echo htmlspecialchars($log['action']); ?></span></td>
                <td><?php echo htmlspecialchars($log['ip_address']); ?></td>
                <td><?php echo htmlspecialchars($log['details']); ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php include 'templates/footer.php'; ?>
