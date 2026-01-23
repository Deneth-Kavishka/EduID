<?php
require_once __DIR__ . '/../../app/bootstrap.php';
require_role(['teacher']);
require_once __DIR__ . '/../../app/views/partials/head.php';
$user = current_user();
?>

<div class="portal-layout">
    <?php require_once __DIR__ . '/../../app/views/partials/sidebar-teacher.php'; ?>
    <div class="main-content">
        <h2>Teacher Dashboard</h2>
        <p class="hero-subtitle">Welcome, <?= htmlspecialchars($user['name']) ?>. Monitor class attendance and exam access.</p>
        <div class="grid">
            <div class="card">
                <h4>Class Attendance</h4>
                <p>Today: 28/30 students present.</p>
            </div>
            <div class="card">
                <h4>Exam Sessions</h4>
                <p>Hall B - Physics</p>
            </div>
            <div class="card">
                <h4>Quick Verification</h4>
                <a class="btn btn-primary" href="<?= base_url('verify/qr.php') ?>">Open QR Scanner</a>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../app/views/partials/footer.php'; ?>
