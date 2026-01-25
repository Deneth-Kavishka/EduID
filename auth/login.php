<?php
require_once '../config/config.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username']);
    $password = $_POST['password'];
    
    if (empty($username) || empty($password)) {
        $error = 'Please fill in all fields';
    } else {
        $db = new Database();
        $conn = $db->getConnection();
        
        $query = "SELECT u.*, 
                  s.student_id, s.first_name as student_fname, s.last_name as student_lname,
                  t.teacher_id, t.first_name as teacher_fname, t.last_name as teacher_lname,
                  p.parent_id, p.first_name as parent_fname, p.last_name as parent_lname
                  FROM users u
                  LEFT JOIN students s ON u.user_id = s.user_id
                  LEFT JOIN teachers t ON u.user_id = t.user_id
                  LEFT JOIN parents p ON u.user_id = p.user_id
                  WHERE (u.username = :username OR u.email = :username) AND u.status = 'active'";
        
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':username', $username);
        $stmt->execute();
        
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($password, $user['password_hash'])) {
            // Set session variables
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_role'] = $user['user_role'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['profile_picture'] = $user['profile_picture'];
            
            // Set role-specific session data
            switch ($user['user_role']) {
                case 'student':
                    $_SESSION['student_id'] = $user['student_id'];
                    $_SESSION['full_name'] = $user['student_fname'] . ' ' . $user['student_lname'];
                    break;
                case 'teacher':
                    $_SESSION['teacher_id'] = $user['teacher_id'];
                    $_SESSION['full_name'] = $user['teacher_fname'] . ' ' . $user['teacher_lname'];
                    break;
                case 'parent':
                    $_SESSION['parent_id'] = $user['parent_id'];
                    $_SESSION['full_name'] = $user['parent_fname'] . ' ' . $user['parent_lname'];
                    break;
                case 'admin':
                    $_SESSION['full_name'] = 'Administrator';
                    break;
            }
            
            // Update last login
            $updateQuery = "UPDATE users SET last_login = NOW() WHERE user_id = :user_id";
            $updateStmt = $conn->prepare($updateQuery);
            $updateStmt->bindParam(':user_id', $user['user_id']);
            $updateStmt->execute();
            
            // Log access
            $logQuery = "INSERT INTO access_logs (user_id, access_type, ip_address, user_agent, status) 
                         VALUES (:user_id, 'login', :ip, :user_agent, 'success')";
            $logStmt = $conn->prepare($logQuery);
            $logStmt->bindParam(':user_id', $user['user_id']);
            $ip = $_SERVER['REMOTE_ADDR'];
            $user_agent = $_SERVER['HTTP_USER_AGENT'];
            $logStmt->bindParam(':ip', $ip);
            $logStmt->bindParam(':user_agent', $user_agent);
            $logStmt->execute();
            
            // Redirect based on role
            switch ($user['user_role']) {
                case 'admin':
                    redirect('../dashboards/admin/index.php');
                    break;
                case 'student':
                    redirect('../dashboards/student/index.php');
                    break;
                case 'teacher':
                    redirect('../dashboards/teacher/index.php');
                    break;
                case 'parent':
                    redirect('../dashboards/parent/index.php');
                    break;
            }
        } else {
            $error = 'Invalid username or password';
            
            // Log failed attempt if user exists
            if ($user) {
                $logQuery = "INSERT INTO access_logs (user_id, access_type, ip_address, user_agent, status, remarks) 
                             VALUES (:user_id, 'login', :ip, :user_agent, 'failed', 'Invalid password')";
                $logStmt = $conn->prepare($logQuery);
                $logStmt->bindParam(':user_id', $user['user_id']);
                $ip = $_SERVER['REMOTE_ADDR'];
                $user_agent = $_SERVER['HTTP_USER_AGENT'];
                $logStmt->bindParam(':ip', $ip);
                $logStmt->bindParam(':user_agent', $user_agent);
                $logStmt->execute();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In - EduID</title>
    <link rel="icon" type="image/svg+xml" href="../assets/images/logo.svg">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>
    <div class="auth-page">
        <div class="auth-container">
            <div class="auth-header">
                <img src="../assets/images/logo.svg" alt="EduID Logo" class="auth-logo">
                <h2>Welcome Back</h2>
                <p>Sign in to your EduID account</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="username">
                        <i class="fas fa-user"></i> Username or Email
                    </label>
                    <input 
                        type="text" 
                        id="username" 
                        name="username" 
                        class="form-control" 
                        placeholder="Enter your username or email"
                        required
                        autofocus
                    >
                </div>
                
                <div class="form-group">
                    <label for="password">
                        <i class="fas fa-lock"></i> Password
                    </label>
                    <div class="password-group">
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            class="form-control" 
                            placeholder="Enter your password"
                            required
                        >
                        <button type="button" class="toggle-password" onclick="togglePassword()">
                            <i class="fas fa-eye" id="toggleIcon"></i>
                        </button>
                    </div>
                </div>
                
                <button type="submit" class="btn-auth">
                    <i class="fas fa-sign-in-alt"></i> Sign In
                </button>
            </form>
            
            <div style="margin-top: 1.5rem; text-align: center;">
                <button class="theme-toggle" id="themeToggle" style="margin: 0 auto;">
                    <i class="fas fa-moon"></i>
                </button>
            </div>
            
            <div style="margin-top: 1rem; text-align: center; color: var(--text-secondary); font-size: 0.875rem;">
                <p>Demo Credentials:</p>
                <p><strong>Admin:</strong> admin / Admin@123</p>
            </div>
            
            <div style="margin-top: 1.5rem; text-align: center;">
                <a href="../index.html" style="color: var(--text-secondary); font-size: 0.875rem;">
                    <i class="fas fa-arrow-left"></i> Back to Home
                </a>
            </div>
        </div>
    </div>
    
    <script src="../assets/js/theme.js"></script>
    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('toggleIcon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }
    </script>
</body>
</html>
