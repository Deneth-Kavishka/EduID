<?php
require_once __DIR__ . '/../../app/bootstrap.php';
require_role(['admin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role = $_POST['role'] ?? 'student';
    $password = $_POST['password'] ?? '';

    if ($fullName && $email && $password) {
        $stmt = db()->prepare('INSERT INTO users (full_name, email, role, password_hash, status) VALUES (:name, :email, :role, :hash, "active")');
        $stmt->execute([
            'name' => $fullName,
            'email' => $email,
            'role' => $role,
            'hash' => password_hash($password, PASSWORD_BCRYPT)
        ]);
        flash('success', 'User created successfully.');
        redirect(base_url('admin/users.php'));
    }
    flash('error', 'Please fill in all required fields.');
}

$users = db()->query('SELECT id, full_name, email, role, status FROM users ORDER BY id DESC')->fetchAll();

require_once __DIR__ . '/../../app/views/partials/head.php';
?>

<div class="portal-layout">
    <?php require_once __DIR__ . '/../../app/views/partials/sidebar-admin.php'; ?>
    <div class="main-content">
        <h2>User Management</h2>
        <?php if ($msg = flash('success')): ?>
            <div class="alert"><?= htmlspecialchars($msg) ?></div>
        <?php endif; ?>
        <?php if ($msg = flash('error')): ?>
            <div class="alert" style="background: rgba(180, 79, 63, 0.15);"><?= htmlspecialchars($msg) ?></div>
        <?php endif; ?>
        <div class="card" style="margin-bottom:24px;">
            <h4>Create new user</h4>
            <form method="POST">
                <div class="grid">
                    <div class="form-group">
                        <label>Full name</label>
                        <input type="text" name="full_name" required>
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" required>
                    </div>
                    <div class="form-group">
                        <label>Role</label>
                        <select name="role">
                            <option value="student">Student</option>
                            <option value="teacher">Teacher</option>
                            <option value="parent">Parent</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Temporary password</label>
                        <input type="password" name="password" required>
                    </div>
                </div>
                <button class="btn btn-primary" type="submit">Create user</button>
            </form>
        </div>
        <div class="card">
            <h4>Current users</h4>
            <table class="table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td><?= htmlspecialchars($u['full_name']) ?></td>
                            <td><?= htmlspecialchars($u['email']) ?></td>
                            <td><?= htmlspecialchars(ucfirst($u['role'])) ?></td>
                            <td><?= htmlspecialchars(ucfirst($u['status'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../app/views/partials/footer.php'; ?>
