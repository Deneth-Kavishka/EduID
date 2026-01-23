<?php
require_once __DIR__ . '/../../app/bootstrap.php';
require_role(['admin']);
require_once __DIR__ . '/../../app/views/partials/head.php';

$logs = db()->query('SELECT * FROM access_logs ORDER BY created_at DESC LIMIT 50')->fetchAll();
?>

<div class="portal-layout">
    <?php require_once __DIR__ . '/../../app/views/partials/sidebar-admin.php'; ?>
    <div class="main-content">
        <h2>Verification Logs</h2>
        <div class="card">
            <table class="table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Method</th>
                        <th>Result</th>
                        <th>Location</th>
                        <th>Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?= htmlspecialchars($log['user_name']) ?></td>
                            <td><?= htmlspecialchars($log['method']) ?></td>
                            <td><?= htmlspecialchars($log['result']) ?></td>
                            <td><?= htmlspecialchars($log['location']) ?></td>
                            <td><?= htmlspecialchars($log['created_at']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../app/views/partials/footer.php'; ?>
