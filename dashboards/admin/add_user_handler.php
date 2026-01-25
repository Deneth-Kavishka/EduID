<?php
require_once '../../config/config.php';
checkRole(['admin']);

$db = new Database();
$conn = $db->getConnection();

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        $conn->beginTransaction();
        
        switch ($action) {
            case 'add_student':
                $response = addStudent($conn);
                break;
            case 'add_teacher':
                $response = addTeacher($conn);
                break;
            case 'add_parent':
                $response = addParent($conn);
                break;
            case 'add_admin':
                $response = addAdmin($conn);
                break;
            case 'edit_student':
                $response = editStudent($conn);
                break;
            case 'edit_teacher':
                $response = editTeacher($conn);
                break;
            case 'edit_parent':
                $response = editParent($conn);
                break;
            case 'edit_admin':
                $response = editAdmin($conn);
                break;
            default:
                $response['message'] = 'Invalid action';
        }
        
        if ($response['success']) {
            $conn->commit();
        } else {
            $conn->rollBack();
        }
    } catch (Exception $e) {
        $conn->rollBack();
        $response['message'] = 'Error: ' . $e->getMessage();
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

function addStudent($conn) {
    // Validate required fields
    $required = ['username', 'email', 'password', 'first_name', 'last_name', 'date_of_birth', 'gender', 'grade', 'enrollment_date'];
    foreach ($required as $field) {
        if (empty($_POST[$field])) {
            return ['success' => false, 'message' => ucfirst(str_replace('_', ' ', $field)) . ' is required'];
        }
    }
    
    // Check if username or email already exists
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = :username OR email = :email");
    $stmt->execute([':username' => $_POST['username'], ':email' => $_POST['email']]);
    if ($stmt->fetch()) {
        return ['success' => false, 'message' => 'Username or email already exists'];
    }
    
    // Generate student number
    $student_number = 'STD' . date('Y') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
    
    // Insert into users table
    $password_hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO users (username, email, password_hash, user_role, status, created_by) VALUES (:username, :email, :password, 'student', 'active', :created_by)");
    $stmt->execute([
        ':username' => $_POST['username'],
        ':email' => $_POST['email'],
        ':password' => $password_hash,
        ':created_by' => $_SESSION['user_id']
    ]);
    
    $user_id = $conn->lastInsertId();
    
    // Insert into students table
    $stmt = $conn->prepare("INSERT INTO students (user_id, student_number, first_name, last_name, date_of_birth, gender, phone, address, grade, class_section, enrollment_date, emergency_contact, blood_group, parent_id) 
                           VALUES (:user_id, :student_number, :first_name, :last_name, :dob, :gender, :phone, :address, :grade, :class_section, :enrollment_date, :emergency_contact, :blood_group, :parent_id)");
    
    $stmt->execute([
        ':user_id' => $user_id,
        ':student_number' => $student_number,
        ':first_name' => $_POST['first_name'],
        ':last_name' => $_POST['last_name'],
        ':dob' => $_POST['date_of_birth'],
        ':gender' => $_POST['gender'],
        ':phone' => $_POST['phone'] ?? null,
        ':address' => $_POST['address'] ?? null,
        ':grade' => $_POST['grade'],
        ':class_section' => $_POST['class_section'] ?? null,
        ':enrollment_date' => $_POST['enrollment_date'],
        ':emergency_contact' => $_POST['emergency_contact'] ?? null,
        ':blood_group' => $_POST['blood_group'] ?? null,
        ':parent_id' => !empty($_POST['parent_id']) ? $_POST['parent_id'] : null
    ]);
    
    $student_id = $conn->lastInsertId();
    
    // Handle face data if provided
    if (!empty($_POST['face_descriptor'])) {
        $stmt = $conn->prepare("INSERT INTO face_recognition_data (user_id, face_descriptor, registered_at) VALUES (:user_id, :descriptor, NOW())");
        $stmt->execute([
            ':user_id' => $user_id,
            ':descriptor' => $_POST['face_descriptor']
        ]);
    }
    
    return ['success' => true, 'message' => 'Student added successfully! Student Number: ' . $student_number, 'user_id' => $user_id, 'student_id' => $student_id];
}

function addTeacher($conn) {
    // Validate required fields
    $required = ['username', 'email', 'password', 'first_name', 'last_name', 'date_of_birth', 'gender', 'phone', 'joining_date'];
    foreach ($required as $field) {
        if (empty($_POST[$field])) {
            return ['success' => false, 'message' => ucfirst(str_replace('_', ' ', $field)) . ' is required'];
        }
    }
    
    // Check if username or email already exists
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = :username OR email = :email");
    $stmt->execute([':username' => $_POST['username'], ':email' => $_POST['email']]);
    if ($stmt->fetch()) {
        return ['success' => false, 'message' => 'Username or email already exists'];
    }
    
    // Generate employee number
    $employee_number = 'EMP' . date('Y') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
    
    // Insert into users table
    $password_hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO users (username, email, password_hash, user_role, status, created_by) VALUES (:username, :email, :password, 'teacher', 'active', :created_by)");
    $stmt->execute([
        ':username' => $_POST['username'],
        ':email' => $_POST['email'],
        ':password' => $password_hash,
        ':created_by' => $_SESSION['user_id']
    ]);
    
    $user_id = $conn->lastInsertId();
    
    // Insert into teachers table
    $stmt = $conn->prepare("INSERT INTO teachers (user_id, employee_number, first_name, last_name, date_of_birth, gender, phone, address, department, subject, qualification, joining_date) 
                           VALUES (:user_id, :employee_number, :first_name, :last_name, :dob, :gender, :phone, :address, :department, :subject, :qualification, :joining_date)");
    
    $stmt->execute([
        ':user_id' => $user_id,
        ':employee_number' => $employee_number,
        ':first_name' => $_POST['first_name'],
        ':last_name' => $_POST['last_name'],
        ':dob' => $_POST['date_of_birth'],
        ':gender' => $_POST['gender'],
        ':phone' => $_POST['phone'],
        ':address' => $_POST['address'] ?? null,
        ':department' => $_POST['department'] ?? null,
        ':subject' => $_POST['subject'] ?? null,
        ':qualification' => $_POST['qualification'] ?? null,
        ':joining_date' => $_POST['joining_date']
    ]);
    
    return ['success' => true, 'message' => 'Teacher added successfully! Employee Number: ' . $employee_number];
}

function addParent($conn) {
    // Validate required fields
    $required = ['username', 'email', 'password', 'first_name', 'last_name', 'phone', 'relationship'];
    foreach ($required as $field) {
        if (empty($_POST[$field])) {
            return ['success' => false, 'message' => ucfirst(str_replace('_', ' ', $field)) . ' is required'];
        }
    }
    
    // Check if username or email already exists
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = :username OR email = :email");
    $stmt->execute([':username' => $_POST['username'], ':email' => $_POST['email']]);
    if ($stmt->fetch()) {
        return ['success' => false, 'message' => 'Username or email already exists'];
    }
    
    // Insert into users table
    $password_hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO users (username, email, password_hash, user_role, status, created_by) VALUES (:username, :email, :password, 'parent', 'active', :created_by)");
    $stmt->execute([
        ':username' => $_POST['username'],
        ':email' => $_POST['email'],
        ':password' => $password_hash,
        ':created_by' => $_SESSION['user_id']
    ]);
    
    $user_id = $conn->lastInsertId();
    
    // Check if NIC column exists, if not add it dynamically
    try {
        $stmt = $conn->prepare("SHOW COLUMNS FROM parents LIKE 'nic'");
        $stmt->execute();
        $nicExists = $stmt->fetch();
        
        if (!$nicExists) {
            $conn->exec("ALTER TABLE parents ADD COLUMN nic VARCHAR(20) UNIQUE AFTER phone");
        }
    } catch (Exception $e) {
        // Column might already exist or other error
    }
    
    // Insert into parents table
    $stmt = $conn->prepare("INSERT INTO parents (user_id, first_name, last_name, phone, nic, alternative_phone, email, address, occupation, relationship) 
                           VALUES (:user_id, :first_name, :last_name, :phone, :nic, :alt_phone, :email, :address, :occupation, :relationship)");
    
    $stmt->execute([
        ':user_id' => $user_id,
        ':first_name' => $_POST['first_name'],
        ':last_name' => $_POST['last_name'],
        ':phone' => $_POST['phone'],
        ':nic' => $_POST['nic'] ?? null,
        ':alt_phone' => $_POST['alternative_phone'] ?? null,
        ':email' => $_POST['email'],
        ':address' => $_POST['address'] ?? null,
        ':occupation' => $_POST['occupation'] ?? null,
        ':relationship' => $_POST['relationship']
    ]);
    
    return ['success' => true, 'message' => 'Parent added successfully!'];
}

function addAdmin($conn) {
    // Validate required fields
    $required = ['username', 'email', 'password'];
    foreach ($required as $field) {
        if (empty($_POST[$field])) {
            return ['success' => false, 'message' => ucfirst(str_replace('_', ' ', $field)) . ' is required'];
        }
    }
    
    // Check if username or email already exists
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = :username OR email = :email");
    $stmt->execute([':username' => $_POST['username'], ':email' => $_POST['email']]);
    if ($stmt->fetch()) {
        return ['success' => false, 'message' => 'Username or email already exists'];
    }
    
    // Insert into users table
    $password_hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO users (username, email, password_hash, user_role, status, created_by) VALUES (:username, :email, :password, 'admin', 'active', :created_by)");
    $stmt->execute([
        ':username' => $_POST['username'],
        ':email' => $_POST['email'],
        ':password' => $password_hash,
        ':created_by' => $_SESSION['user_id']
    ]);
    
    return ['success' => true, 'message' => 'Administrator added successfully!'];
}

// Get all parents for student form dropdown
if (isset($_GET['get_parents'])) {
    try {
        $stmt = $conn->prepare("SHOW COLUMNS FROM parents LIKE 'nic'");
        $stmt->execute();
        $nicExists = $stmt->fetch();
        
        if ($nicExists) {
            $stmt = $conn->prepare("SELECT p.parent_id, p.first_name, p.last_name, p.phone, p.nic FROM parents p JOIN users u ON p.user_id = u.user_id WHERE u.status = 'active'");
        } else {
            $stmt = $conn->prepare("SELECT p.parent_id, p.first_name, p.last_name, p.phone, NULL as nic FROM parents p JOIN users u ON p.user_id = u.user_id WHERE u.status = 'active'");
        }
    } catch (Exception $e) {
        $stmt = $conn->prepare("SELECT p.parent_id, p.first_name, p.last_name, p.phone, NULL as nic FROM parents p JOIN users u ON p.user_id = u.user_id WHERE u.status = 'active'");
    }
    $stmt->execute();
    $parents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    header('Content-Type: application/json');
    echo json_encode($parents);
    exit;
}

// Toggle user status (activate/deactivate)
if (isset($_GET['toggle_status'])) {
    $user_id = $_GET['user_id'] ?? 0;
    $stmt = $conn->prepare("SELECT status FROM users WHERE user_id = :user_id");
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        $newStatus = $user['status'] === 'active' ? 'inactive' : 'active';
        $stmt = $conn->prepare("UPDATE users SET status = :status WHERE user_id = :user_id");
        $stmt->execute([':status' => $newStatus, ':user_id' => $user_id]);
        
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'User status updated', 'new_status' => $newStatus]);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'User not found']);
    }
    exit;
}

// Get user details for view/edit
if (isset($_GET['get_user'])) {
    $user_id = $_GET['user_id'] ?? 0;
    
    $query = "SELECT u.*, 
              s.student_id, s.student_number, s.first_name as student_fname, s.last_name as student_lname, 
              s.date_of_birth as student_dob, s.gender as student_gender, s.phone as student_phone, 
              s.address as student_address, s.grade, s.class_section, s.enrollment_date, 
              s.emergency_contact, s.blood_group, s.parent_id,
              t.teacher_id, t.employee_number, t.first_name as teacher_fname, t.last_name as teacher_lname,
              t.date_of_birth as teacher_dob, t.gender as teacher_gender, t.phone as teacher_phone,
              t.address as teacher_address, t.department, t.subject, t.qualification, t.joining_date,
              p.parent_id as pid, p.first_name as parent_fname, p.last_name as parent_lname, 
              p.phone as parent_phone, p.alternative_phone, p.address as parent_address, 
              p.occupation, p.relationship
              FROM users u
              LEFT JOIN students s ON u.user_id = s.user_id
              LEFT JOIN teachers t ON u.user_id = t.user_id
              LEFT JOIN parents p ON u.user_id = p.user_id
              WHERE u.user_id = :user_id";
    
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Add NIC field if exists
    if ($user && $user['user_role'] === 'parent') {
        $nicQuery = "SELECT nic FROM parents WHERE parent_id = :parent_id";
        $nicStmt = $conn->prepare($nicQuery);
        $nicStmt->bindParam(':parent_id', $user['pid']);
        $nicStmt->execute();
        $nicData = $nicStmt->fetch(PDO::FETCH_ASSOC);
        if ($nicData) {
            $user['nic'] = $nicData['nic'];
        }
    }
    
    header('Content-Type: application/json');
    echo json_encode($user);
    exit;
}

// Edit functions
function editStudent($conn) {
    $user_id = $_POST['user_id'] ?? 0;
    $required = ['username', 'email', 'first_name', 'last_name', 'date_of_birth', 'gender', 'phone', 'grade', 'class_section'];
    
    foreach ($required as $field) {
        if (empty($_POST[$field])) {
            return ['success' => false, 'message' => ucfirst(str_replace('_', ' ', $field)) . ' is required'];
        }
    }
    
    // Update users table
    $password = $_POST['password'] ?? '';
    if (!empty($password)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $userQuery = "UPDATE users SET username = :username, email = :email, password = :password WHERE user_id = :user_id";
        $stmt = $conn->prepare($userQuery);
        $stmt->bindParam(':password', $hashed_password);
    } else {
        $userQuery = "UPDATE users SET username = :username, email = :email WHERE user_id = :user_id";
        $stmt = $conn->prepare($userQuery);
    }
    
    $stmt->bindParam(':username', $_POST['username']);
    $stmt->bindParam(':email', $_POST['email']);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    
    // Update students table
    $studentQuery = "UPDATE students SET 
                    first_name = :first_name,
                    last_name = :last_name,
                    date_of_birth = :date_of_birth,
                    gender = :gender,
                    phone = :phone,
                    grade = :grade,
                    class_section = :class_section,
                    blood_group = :blood_group,
                    parent_id = :parent_id,
                    address = :address
                    WHERE user_id = :user_id";
    
    $stmt = $conn->prepare($studentQuery);
    $stmt->bindParam(':first_name', $_POST['first_name']);
    $stmt->bindParam(':last_name', $_POST['last_name']);
    $stmt->bindParam(':date_of_birth', $_POST['date_of_birth']);
    $stmt->bindParam(':gender', $_POST['gender']);
    $stmt->bindParam(':phone', $_POST['phone']);
    $stmt->bindParam(':grade', $_POST['grade']);
    $stmt->bindParam(':class_section', $_POST['class_section']);
    $blood_group = $_POST['blood_group'] ?? null;
    $stmt->bindParam(':blood_group', $blood_group);
    $parent_id = !empty($_POST['parent_id']) ? $_POST['parent_id'] : null;
    $stmt->bindParam(':parent_id', $parent_id);
    $address = $_POST['address'] ?? '';
    $stmt->bindParam(':address', $address);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    
    return ['success' => true, 'message' => 'Student updated successfully'];
}

function editTeacher($conn) {
    $user_id = $_POST['user_id'] ?? 0;
    $required = ['username', 'email', 'first_name', 'last_name', 'date_of_birth', 'gender', 'phone'];
    
    foreach ($required as $field) {
        if (empty($_POST[$field])) {
            return ['success' => false, 'message' => ucfirst(str_replace('_', ' ', $field)) . ' is required'];
        }
    }
    
    // Update users table
    $password = $_POST['password'] ?? '';
    if (!empty($password)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $userQuery = "UPDATE users SET username = :username, email = :email, password = :password WHERE user_id = :user_id";
        $stmt = $conn->prepare($userQuery);
        $stmt->bindParam(':password', $hashed_password);
    } else {
        $userQuery = "UPDATE users SET username = :username, email = :email WHERE user_id = :user_id";
        $stmt = $conn->prepare($userQuery);
    }
    
    $stmt->bindParam(':username', $_POST['username']);
    $stmt->bindParam(':email', $_POST['email']);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    
    // Update teachers table
    $teacherQuery = "UPDATE teachers SET 
                    first_name = :first_name,
                    last_name = :last_name,
                    date_of_birth = :date_of_birth,
                    gender = :gender,
                    phone = :phone,
                    department = :department,
                    subject = :subject,
                    qualification = :qualification,
                    address = :address
                    WHERE user_id = :user_id";
    
    $stmt = $conn->prepare($teacherQuery);
    $stmt->bindParam(':first_name', $_POST['first_name']);
    $stmt->bindParam(':last_name', $_POST['last_name']);
    $stmt->bindParam(':date_of_birth', $_POST['date_of_birth']);
    $stmt->bindParam(':gender', $_POST['gender']);
    $stmt->bindParam(':phone', $_POST['phone']);
    $department = $_POST['department'] ?? '';
    $stmt->bindParam(':department', $department);
    $subject = $_POST['subject'] ?? '';
    $stmt->bindParam(':subject', $subject);
    $qualification = $_POST['qualification'] ?? '';
    $stmt->bindParam(':qualification', $qualification);
    $address = $_POST['address'] ?? '';
    $stmt->bindParam(':address', $address);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    
    return ['success' => true, 'message' => 'Teacher updated successfully'];
}

function editParent($conn) {
    $user_id = $_POST['user_id'] ?? 0;
    $required = ['username', 'email', 'first_name', 'last_name', 'phone', 'relationship'];
    
    foreach ($required as $field) {
        if (empty($_POST[$field])) {
            return ['success' => false, 'message' => ucfirst(str_replace('_', ' ', $field)) . ' is required'];
        }
    }
    
    // Update users table
    $password = $_POST['password'] ?? '';
    if (!empty($password)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $userQuery = "UPDATE users SET username = :username, email = :email, password = :password WHERE user_id = :user_id";
        $stmt = $conn->prepare($userQuery);
        $stmt->bindParam(':password', $hashed_password);
    } else {
        $userQuery = "UPDATE users SET username = :username, email = :email WHERE user_id = :user_id";
        $stmt = $conn->prepare($userQuery);
    }
    
    $stmt->bindParam(':username', $_POST['username']);
    $stmt->bindParam(':email', $_POST['email']);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    
    // Check if NIC column exists and build query accordingly
    $columns = $conn->query("SHOW COLUMNS FROM parents LIKE 'nic'")->fetchAll();
    $hasNicColumn = count($columns) > 0;
    
    // Update parents table
    if ($hasNicColumn) {
        $parentQuery = "UPDATE parents SET 
                        first_name = :first_name,
                        last_name = :last_name,
                        phone = :phone,
                        nic = :nic,
                        alternative_phone = :alternative_phone,
                        relationship = :relationship,
                        occupation = :occupation,
                        address = :address
                        WHERE user_id = :user_id";
    } else {
        $parentQuery = "UPDATE parents SET 
                        first_name = :first_name,
                        last_name = :last_name,
                        phone = :phone,
                        alternative_phone = :alternative_phone,
                        relationship = :relationship,
                        occupation = :occupation,
                        address = :address
                        WHERE user_id = :user_id";
    }
    
    $stmt = $conn->prepare($parentQuery);
    $stmt->bindParam(':first_name', $_POST['first_name']);
    $stmt->bindParam(':last_name', $_POST['last_name']);
    $stmt->bindParam(':phone', $_POST['phone']);
    if ($hasNicColumn) {
        $nic = $_POST['nic'] ?? '';
        $stmt->bindParam(':nic', $nic);
    }
    $alternative_phone = $_POST['alternative_phone'] ?? '';
    $stmt->bindParam(':alternative_phone', $alternative_phone);
    $stmt->bindParam(':relationship', $_POST['relationship']);
    $occupation = $_POST['occupation'] ?? '';
    $stmt->bindParam(':occupation', $occupation);
    $address = $_POST['address'] ?? '';
    $stmt->bindParam(':address', $address);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    
    return ['success' => true, 'message' => 'Parent updated successfully'];
}

function editAdmin($conn) {
    $user_id = $_POST['user_id'] ?? 0;
    $required = ['username', 'email'];
    
    foreach ($required as $field) {
        if (empty($_POST[$field])) {
            return ['success' => false, 'message' => ucfirst(str_replace('_', ' ', $field)) . ' is required'];
        }
    }
    
    // Update users table
    $password = $_POST['password'] ?? '';
    if (!empty($password)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $userQuery = "UPDATE users SET username = :username, email = :email, password = :password WHERE user_id = :user_id";
        $stmt = $conn->prepare($userQuery);
        $stmt->bindParam(':password', $hashed_password);
    } else {
        $userQuery = "UPDATE users SET username = :username, email = :email WHERE user_id = :user_id";
        $stmt = $conn->prepare($userQuery);
    }
    
    $stmt->bindParam(':username', $_POST['username']);
    $stmt->bindParam(':email', $_POST['email']);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    
    return ['success' => true, 'message' => 'Admin updated successfully'];
}
?>
