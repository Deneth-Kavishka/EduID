<?php $user = current_user(); ?>
<div class="sidebar">
    <div class="brand" style="margin-bottom:24px;">
        <img src="<?= base_url('assets/logos/eduid-mark.svg') ?>" alt="EduID logo">
        <span>EduID Parent</span>
    </div>
    <a class="active" href="<?= base_url('parent/index.php') ?>">Dashboard</a>
    <a href="<?= base_url('auth/logout.php') ?>">Sign Out</a>
    <div style="margin-top:24px;">
        <button class="theme-toggle" data-theme-toggle>Light / Dark</button>
    </div>
    <p style="margin-top:24px; color:var(--muted);">Signed in as <?= htmlspecialchars($user['name']) ?></p>
</div>
