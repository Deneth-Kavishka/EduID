<?php
require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/services/AuthService.php';

logout();
redirect(base_url('index.php'));
