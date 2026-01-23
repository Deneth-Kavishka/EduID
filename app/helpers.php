<?php

function app_config(): array
{
    return require __DIR__ . '/../config/config.php';
}

function db_config(): array
{
    return require __DIR__ . '/../config/database.php';
}

function base_path(string $path = ''): string
{
    $base = realpath(__DIR__ . '/..');
    return $path ? $base . DIRECTORY_SEPARATOR . $path : $base;
}

function base_url(string $path = ''): string
{
    $config = app_config();
    $base = rtrim($config['base_url'], '/');
    $path = ltrim($path, '/');
    return $path ? $base . '/' . $path : $base;
}

function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function is_logged_in(): bool
{
    return isset($_SESSION['user']);
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function require_login(): void
{
    if (!is_logged_in()) {
        redirect(base_url('auth/login.php'));
    }
}

function require_role(array $roles): void
{
    require_login();
    $user = current_user();
    if (!$user || !in_array($user['role'], $roles, true)) {
        http_response_code(403);
        echo 'Access denied.';
        exit;
    }
}

function flash(string $key, ?string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION['flash'][$key] = $message;
        return null;
    }

    if (isset($_SESSION['flash'][$key])) {
        $msg = $_SESSION['flash'][$key];
        unset($_SESSION['flash'][$key]);
        return $msg;
    }

    return null;
}
