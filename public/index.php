<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/views/partials/head.php';
?>

<nav class="navbar">
    <div class="container nav-inner">
        <div class="brand">
            <img src="<?= base_url('assets/logos/eduid-mark.svg') ?>" alt="EduID logo">
            <span>EduID</span>
        </div>
        <div class="nav-links">
            <a href="#features">Features</a>
            <a href="#portals">Portals</a>
            <a href="#verification">Verification</a>
            <a href="<?= base_url('auth/login.php') ?>" class="btn btn-outline">Sign In</a>
            <button class="theme-toggle" data-theme-toggle>Light / Dark</button>
        </div>
    </div>
</nav>

<section class="container hero">
    <div class="hero-card">
        <span class="badge">Secure Student Identity</span>
        <h1 class="hero-title">EduID delivers trusted, automated verification for exams, events, and access.</h1>
        <p class="hero-subtitle">A modern identity platform combining QR entry control, face verification, and role-specific portals for admins, teachers, students, and parents.</p>
        <div style="display:flex; gap:12px; flex-wrap:wrap;">
            <a href="<?= base_url('auth/login.php') ?>" class="btn btn-primary">Launch System</a>
            <a href="#setup" class="btn btn-outline">Local Setup</a>
        </div>
    </div>
    <div class="hero-media">
        <div class="video-frame">
            <video data-hero-video autoplay muted loop poster="<?= base_url('assets/images/hero-illustration.svg') ?>">
                <source src="<?= base_url('assets/videos/campus.mp4') ?>" type="video/mp4">
            </video>
            <img class="video-fallback" src="<?= base_url('assets/images/hero-illustration.svg') ?>" alt="EduID illustration" style="display:none;">
        </div>
    </div>
</section>

<section id="features" class="section">
    <div class="container">
        <h2 class="section-title">Key Capabilities</h2>
        <div class="grid">
            <div class="card">
                <h4>QR Entry Validation</h4>
                <p>Scan student or staff QR codes at exam halls and events for instant eligibility checks.</p>
            </div>
            <div class="card">
                <h4>Face Detection (Local)</h4>
                <p>Validate live camera feeds using on-device face detection for higher security.</p>
            </div>
            <div class="card">
                <h4>Role-Based Portals</h4>
                <p>Dedicated dashboards for Admin, Student, Teacher, and Parent with contextual actions.</p>
            </div>
            <div class="card">
                <h4>Audit & Logs</h4>
                <p>Track access activity, exam attendance, and verification logs in real time.</p>
            </div>
        </div>
    </div>
</section>

<section id="portals" class="section">
    <div class="container hero">
        <div>
            <h2 class="section-title">Purpose-built portals</h2>
            <p class="hero-subtitle">Admins manage registrations and data. Teachers monitor classes and events. Students access IDs and attendance. Parents receive updates and confirmations.</p>
            <div class="grid">
                <div class="card">Admin management & approvals</div>
                <div class="card">Teacher attendance tools</div>
                <div class="card">Student QR & timetable</div>
                <div class="card">Parent notifications</div>
            </div>
        </div>
        <img src="<?= base_url('assets/images/portal-illustration.svg') ?>" alt="Portal preview" style="width:100%; border-radius:24px; box-shadow: var(--shadow);">
    </div>
</section>

<section id="verification" class="section">
    <div class="container">
        <h2 class="section-title">Verification workflow</h2>
        <div class="grid">
            <div class="card">
                <h4>Step 1 - QR Scan</h4>
                <p>Scan the ID QR code with the web scanner or mobile device.</p>
            </div>
            <div class="card">
                <h4>Step 2 - Face Check</h4>
                <p>Compare a live camera frame with stored identity data for confirmation.</p>
            </div>
            <div class="card">
                <h4>Step 3 - Log & Notify</h4>
                <p>Automatically record the entry and notify relevant staff or guardians.</p>
            </div>
        </div>
    </div>
</section>

<section id="setup" class="section">
    <div class="container">
        <h2 class="section-title">Local Demonstration Setup</h2>
        <div class="card">
            <ol>
                <li>Import the database schema from <strong>database/schema.sql</strong> using MySQL Workbench.</li>
                <li>Update credentials in <strong>config/database.php</strong>.</li>
                <li>Download face-api.js models into <strong>public/assets/models</strong> (see docs/setup.md).</li>
                <li>Serve the project locally (e.g., PHP built-in server).</li>
            </ol>
        </div>
    </div>
</section>

<footer>
    <div class="container">
        <div style="display:flex; align-items:center; gap:12px;">
            <img src="<?= base_url('assets/logos/eduid-mark.svg') ?>" alt="EduID mark" style="width:32px; height:32px;">
            <span>EduID • Secure Identity Verification</span>
        </div>
    </div>
</footer>

<script src="<?= base_url('assets/js/landing.js') ?>"></script>
<?php require_once __DIR__ . '/../app/views/partials/footer.php'; ?>
