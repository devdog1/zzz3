<?php
require_once 'includes/Database.php';

$db = new Database();
$logs = $db->fetchAll("SELECT * FROM logs ORDER BY timestamp DESC LIMIT 100");

include 'templates/header.php';
?>

<h2>Action Logs (Last 100)</h2>

<table class="table table-sm table-striped">
    <thead class="table-dark">
        <tr>
            <th>Timestamp (UTC)</th>
            <th>Action</th>
            <th>IP Address</th>
            <th>Details</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($logs as $row): ?>
        <tr>
            <td><small><?php echo $row['timestamp']; ?></small></td>
            <td><?php echo htmlspecialchars($row['action']); ?></td>
            <td><?php echo htmlspecialchars($row['ip_address']); ?></td>
            <td><small><?php echo htmlspecialchars($row['details']); ?></small></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php include 'templates/footer.php'; ?>
