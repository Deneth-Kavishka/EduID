<?php
require_once __DIR__ . '/../../app/bootstrap.php';
require_role(['parent']);
require_once __DIR__ . '/../../app/views/partials/head.php';
$user = current_user();
?>

<div class="portal-layout">
    <?php require_once __DIR__ . '/../../app/views/partials/sidebar-parent.php'; ?>
    <div class="main-content">
        <h2>Parent Portal</h2>
        <p class="hero-subtitle">Welcome, <?= htmlspecialchars($user['name']) ?>. Review student verification updates.</p>
        <div class="grid">
            <div class="card">
                <h4>Latest Verification</h4>
                <p>Student entry confirmed at Exam Hall A.</p>
            </div>
            <div class="card">
                <h4>Attendance Summary</h4>
                <p>Current month: 94% attendance.</p>
            </div>
            <div class="card">
                <h4>Notifications</h4>
                <p>No pending alerts.</p>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../app/views/partials/footer.php'; ?>
