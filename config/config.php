<?php
/**
 * System Configuration
 * EduID - Educational Identity Verification System
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// System Settings
define('SITE_NAME', 'EduID');
define('SITE_DESCRIPTION', 'Educational Identity Verification System');
define('BASE_URL', 'http://localhost/eduid/');

// File Upload Settings
define('UPLOAD_PATH', __DIR__ . '/../uploads/');
define('PROFILE_PICS_PATH', UPLOAD_PATH . 'profiles/');
define('FACE_DATA_PATH', UPLOAD_PATH . 'face_data/');
define('QR_CODES_PATH', UPLOAD_PATH . 'qr_codes/');

// Create upload directories if they don't exist
$directories = [UPLOAD_PATH, PROFILE_PICS_PATH, FACE_DATA_PATH, QR_CODES_PATH];
foreach ($directories as $dir) {
    if (!file_exists($dir)) {
        mkdir($dir, 0777, true);
    }
}

// Timezone
date_default_timezone_set('Asia/Colombo');

// Error Reporting (Disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database
require_once __DIR__ . '/database.php';

// Helper Functions
function redirect($url) {
    header("Location: " . $url);
    exit();
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function getUserRole() {
    return $_SESSION['user_role'] ?? null;
}

function checkRole($allowed_roles) {
    if (!isLoggedIn()) {
        redirect('../auth/login.php');
    }
    
    if (!in_array(getUserRole(), $allowed_roles)) {
        redirect('../index.php');
    }
}

function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function generateQRData($user_id, $user_type) {
    return base64_encode(json_encode([
        'id' => $user_id,
        'type' => $user_type,
        'timestamp' => time()
    ]));
}
?>
