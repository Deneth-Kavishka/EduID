<?php
require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/services/AuthService.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (authenticate($email, $password)) {
        redirect(base_url('dashboard.php'));
    }

    flash('error', 'Invalid credentials. Please try again.');
}

require_once __DIR__ . '/../../app/views/partials/head.php';
?>

<div class="container" style="padding:64px 24px; max-width:480px;">
    <div class="card">
        <div style="display:flex; align-items:center; gap:12px; margin-bottom:16px;">
            <img src="<?= base_url('assets/logos/eduid-mark.svg') ?>" alt="EduID logo" style="width:40px; height:40px;">
            <h2 style="margin:0;">EduID Sign In</h2>
        </div>
        <?php if ($msg = flash('error')): ?>
            <div class="alert" style="background: rgba(180, 79, 63, 0.15);"><?= htmlspecialchars($msg) ?></div>
        <?php endif; ?>
        <form method="POST">
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" required>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" required>
            </div>
            <button class="btn btn-primary" type="submit" style="width:100%;">Sign In</button>
        </form>
        <p style="margin-top:16px; color: var(--muted);">Access is provisioned by administrators only.</p>
    </div>
</div>

<?php require_once __DIR__ . '/../../app/views/partials/footer.php'; ?>
