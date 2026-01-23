<?php
require_once __DIR__ . '/../../app/bootstrap.php';
require_role(['student']);
require_once __DIR__ . '/../../app/views/partials/head.php';
$user = current_user();
?>

<div class="portal-layout">
    <?php require_once __DIR__ . '/../../app/views/partials/sidebar-student.php'; ?>
    <div class="main-content">
        <h2>Hello, <?= htmlspecialchars($user['name']) ?></h2>
        <p class="hero-subtitle">Your EduID digital card and attendance overview.</p>
        <div class="grid">
            <div class="card">
                <h4>Your QR Code</h4>
                <div id="studentQr" class="qr-box"></div>
                <p style="color:var(--muted);">Present this QR for exam hall entry.</p>
            </div>
            <div class="card">
                <h4>Upcoming Exams</h4>
                <p>Mathematics - 09:00 AM</p>
                <p>Science - 01:30 PM</p>
            </div>
            <div class="card">
                <h4>Attendance</h4>
                <p>92% overall attendance.</p>
            </div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
    new QRCode(document.getElementById('studentQr'), {
        text: 'EDUID-<?= $user['id'] ?>-<?= htmlspecialchars($user['email']) ?>',
        width: 160,
        height: 160,
        colorDark: '#1a1a17',
        colorLight: '#f7f7f4'
    });
</script>

<?php require_once __DIR__ . '/../../app/views/partials/footer.php'; ?>
