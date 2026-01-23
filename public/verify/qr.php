<?php
require_once __DIR__ . '/../../app/bootstrap.php';
require_role(['admin', 'teacher']);
require_once __DIR__ . '/../../app/views/partials/head.php';
?>

<div class="container" style="padding:48px 24px;">
    <h2>QR Verification</h2>
    <p class="hero-subtitle">Scan a QR code to verify student access in real time.</p>
    <div class="grid">
        <div class="card qr-box">
            <div id="qr-reader" style="width:100%; min-height:320px;"></div>
        </div>
        <div class="card">
            <h4>Result</h4>
            <p id="qrResult">Waiting for scan...</p>
            <a class="btn btn-outline" href="<?= base_url('verify/face.php') ?>">Open Face Verification</a>
        </div>
    </div>
</div>

<script src="https://unpkg.com/html5-qrcode"></script>
<script src="<?= base_url('assets/js/verification.js') ?>"></script>
<script>initQrScanner();</script>

<?php require_once __DIR__ . '/../../app/views/partials/footer.php'; ?>
