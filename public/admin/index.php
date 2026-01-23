<?php
require_once __DIR__ . '/../../app/bootstrap.php';
require_role(['admin']);
require_once __DIR__ . '/../../app/views/partials/head.php';
$user = current_user();
?>

<div class="portal-layout">
    <?php require_once __DIR__ . '/../../app/views/partials/sidebar-admin.php'; ?>
    <div class="main-content">
        <h2>Welcome, <?= htmlspecialchars($user['name']) ?></h2>
        <p class="hero-subtitle">Admin Control Center for EduID</p>
        <div class="grid">
            <div class="card">
                <h4>System Status</h4>
                <p>All verification services are online.</p>
            </div>
            <div class="card">
                <h4>Pending Registrations</h4>
                <p>Review and approve new portal accounts.</p>
            </div>
            <div class="card">
                <h4>Today's Events</h4>
                <p>Exam Hall A, Event Hall C</p>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../app/views/partials/footer.php'; ?>
