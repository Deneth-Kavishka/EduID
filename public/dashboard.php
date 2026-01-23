<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_login();

$user = current_user();
if ($user['role'] === 'admin') {
    redirect(base_url('admin/index.php'));
}
if ($user['role'] === 'teacher') {
    redirect(base_url('teacher/index.php'));
}
if ($user['role'] === 'parent') {
    redirect(base_url('parent/index.php'));
}

redirect(base_url('student/index.php'));
