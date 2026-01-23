<?php
require_once __DIR__ . '/../bootstrap.php';

function authenticate(string $email, string $password): bool
{
    $stmt = db()->prepare('SELECT * FROM users WHERE email = :email AND status = "active" LIMIT 1');
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        return false;
    }

    $_SESSION['user'] = [
        'id' => $user['id'],
        'name' => $user['full_name'],
        'email' => $user['email'],
        'role' => $user['role']
    ];

    return true;
}

function logout(): void
{
    unset($_SESSION['user']);
    session_destroy();
}
