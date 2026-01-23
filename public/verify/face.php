<?php
require_once __DIR__ . '/../../app/bootstrap.php';
require_role(['admin', 'teacher']);
require_once __DIR__ . '/../../app/views/partials/head.php';
?>

<div class="container" style="padding:48px 24px;">
    <h2>Face Verification</h2>
    <p class="hero-subtitle">Use live camera detection for additional identity confirmation.</p>
    <div class="grid">
        <div class="card face-box">
            <video id="faceVideo" width="360" height="280" autoplay muted></video>
            <div id="faceCanvas" style="margin-top:12px;"></div>
        </div>
        <div class="card">
            <h4>Status</h4>
            <p id="faceStatus">Initializing...</p>
            <p style="color:var(--muted);">Tip: Ensure good lighting and face visible.</p>
        </div>
    </div>
</div>

<script defer src="https://unpkg.com/face-api.js@0.22.2/dist/face-api.min.js"></script>
<script src="<?= base_url('assets/js/verification.js') ?>"></script>
<script>
    window.addEventListener('load', initFaceVerification);
</script>

<?php require_once __DIR__ . '/../../app/views/partials/footer.php'; ?>
