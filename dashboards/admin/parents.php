<?php
require_once '../../config/config.php';
checkRole(['admin']);

$db = new Database();
$conn = $db->getConnection();

$success = '';
$error = '';

// Get all parents with their details
$query = "SELECT p.*, u.username, u.email, u.status, u.created_at,
          (SELECT COUNT(*) FROM students WHERE parent_id = p.parent_id) as children_count,
          (SELECT COUNT(*) FROM face_recognition_data WHERE user_id = p.user_id) as has_face_data
          FROM parents p
          JOIN users u ON p.user_id = u.user_id
          ORDER BY p.parent_id DESC";
$stmt = $conn->prepare($query);
$stmt->execute();
$parents = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stats = [];

// Total parents
$stats['total'] = count($parents);

// Active parents
$query = "SELECT COUNT(*) as count FROM parents p JOIN users u ON p.user_id = u.user_id WHERE u.status = 'active'";
$stmt = $conn->prepare($query);
$stmt->execute();
$stats['active'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Parents with multiple children
$query = "SELECT COUNT(*) as count FROM (SELECT parent_id FROM students WHERE parent_id IS NOT NULL GROUP BY parent_id HAVING COUNT(*) > 1) as multi_children";
$stmt = $conn->prepare($query);
$stmt->execute();
$stats['multi_children'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Total children
$query = "SELECT COUNT(*) as count FROM students WHERE parent_id IS NOT NULL";
$stmt = $conn->prepare($query);
$stmt->execute();
$stats['total_children'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parents Management - EduID</title>
    <link rel="icon" type="image/svg+xml" href="../../assets/images/logo.svg">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>
    <div class="dashboard">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-logo">
                    <img src="../../assets/images/logo.svg" alt="EduID">
                    <span>EduID</span>
                </div>
            </div>
            
            <nav class="sidebar-nav">
                <div class="nav-section">
                    <div class="nav-section-title">Main</div>
                    <a href="index.php" class="nav-item">
                        <i class="fas fa-chart-line"></i>
                        <span>Dashboard</span>
                    </a>
                    <a href="users.php" class="nav-item">
                        <i class="fas fa-users"></i>
                        <span>User Management</span>
                    </a>
                    <a href="students.php" class="nav-item">
                        <i class="fas fa-user-graduate"></i>
                        <span>Students</span>
                    </a>
                    <a href="teachers.php" class="nav-item">
                        <i class="fas fa-chalkboard-teacher"></i>
                        <span>Teachers</span>
                    </a>
                    <a href="parents.php" class="nav-item active">
                        <i class="fas fa-users-between-lines"></i>
                        <span>Parents</span>
                    </a>
                </div>
                
                <div class="nav-section">
                    <div class="nav-section-title">Attendance</div>
                    <a href="attendance.php" class="nav-item">
                        <i class="fas fa-calendar-check"></i>
                        <span>Class Attendance</span>
                    </a>
                    <a href="face_attendance.php" class="nav-item">
                        <i class="fas fa-camera"></i>
                        <span>Face Recognition</span>
                    </a>
                </div>
                
                <div class="nav-section">
                    <div class="nav-section-title">Examinations</div>
                    <a href="exams.php" class="nav-item">
                        <i class="fas fa-clipboard-list"></i>
                        <span>Exam Management</span>
                    </a>
                    <a href="exam_halls.php" class="nav-item">
                        <i class="fas fa-door-open"></i>
                        <span>Exam Halls</span>
                    </a>
                    <a href="exam_attendance.php" class="nav-item">
                        <i class="fas fa-user-check"></i>
                        <span>Exam Attendance</span>
                    </a>
                    <a href="exam_eligibility.php" class="nav-item">
                        <i class="fas fa-check-double"></i>
                        <span>Eligibility Check</span>
                    </a>
                </div>
                
                <div class="nav-section">
                    <div class="nav-section-title">Reports & Settings</div>
                    <a href="reports.php" class="nav-item">
                        <i class="fas fa-chart-bar"></i>
                        <span>Reports</span>
                    </a>
                    <a href="logs.php" class="nav-item">
                        <i class="fas fa-list"></i>
                        <span>Access Logs</span>
                    </a>
                    <a href="settings.php" class="nav-item">
                        <i class="fas fa-cog"></i>
                        <span>System Settings</span>
                    </a>
                </div>
                
                <div class="nav-section">
                    <div class="nav-section-title">Account</div>
                    <a href="profile.php" class="nav-item">
                        <i class="fas fa-user"></i>
                        <span>My Profile</span>
                    </a>
                    <a href="../../auth/logout.php" class="nav-item">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Logout</span>
                    </a>
                </div>
            </nav>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <header class="top-header">
                <div class="header-left">
                    <h1>Parents Management</h1>
                    <div class="breadcrumb">
                        <span>Home</span>
                        <i class="fas fa-chevron-right"></i>
                        <span>Parents</span>
                    </div>
                </div>
                
                <div class="header-right">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" placeholder="Search parents..." id="searchInput">
                    </div>
                    
                    <button class="theme-toggle" id="themeToggleTop" title="Toggle Theme">
                        <i class="fas fa-moon"></i>
                    </button>
                    
                    <div class="notification-icon">
                        <i class="fas fa-bell"></i>
                        <span class="badge">3</span>
                    </div>
                    
                    <div class="user-menu">
                        <img src="../../assets/images/default-avatar.png" alt="Admin" class="user-avatar" onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 24 24%22><circle cx=%2212%22 cy=%228%22 r=%224%22 fill=%22%23cbd5e1%22/><path d=%22M12 14c-4 0-7 2-7 4v2h14v-2c0-2-3-4-7-4z%22 fill=%22%23cbd5e1%22/></svg>'">
                    </div>
                </div>
            </header>
            
            <!-- Content Area -->
            <div class="content-area">
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <!-- Stats Overview -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon primary">
                            <i class="fas fa-users-between-lines"></i>
                        </div>
                        <div class="stat-details">
                            <h3><?php echo $stats['total']; ?></h3>
                            <p>Total Parents</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon success">
                            <i class="fas fa-user-check"></i>
                        </div>
                        <div class="stat-details">
                            <h3><?php echo $stats['active']; ?></h3>
                            <p>Active Parents</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon warning">
                            <i class="fas fa-children"></i>
                        </div>
                        <div class="stat-details">
                            <h3><?php echo $stats['multi_children']; ?></h3>
                            <p>Multiple Children</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon info">
                            <i class="fas fa-user-graduate"></i>
                        </div>
                        <div class="stat-details">
                            <h3><?php echo $stats['total_children']; ?></h3>
                            <p>Total Children</p>
                        </div>
                    </div>
                </div>
                
                <!-- Parents Table -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">All Parents</h3>
                        <div style="display: flex; gap: 0.5rem;">
                            <select id="statusFilter" style="padding: 0.5rem 1rem; border: 1px solid var(--border-color); border-radius: 6px; background: var(--bg-primary);">
                                <option value="">All Status</option>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                            <select id="childrenFilter" style="padding: 0.5rem 1rem; border: 1px solid var(--border-color); border-radius: 6px; background: var(--bg-primary);">
                                <option value="">All Parents</option>
                                <option value="with-children">With Children</option>
                                <option value="no-children">No Children</option>
                            </select>
                            <a href="users.php?action=add_parent" class="btn btn-outline" style="border: 2px solid var(--primary-color); color: var(--primary-color); font-weight: 600; padding: 0.5rem 1rem; font-size: 0.875rem;">
                                <i class="fas fa-plus"></i> Add Parent
                            </a>
                        </div>
                    </div>
                    <div class="table-container" style="overflow-x: auto;">
                        <table id="parentsTable" style="width: 100%; table-layout: fixed;">
                            <colgroup>
                                <col style="width: 8%;">
                                <col style="width: 25%;">
                                <col style="width: 12%;">
                                <col style="width: 13%;">
                                <col style="width: 10%;">
                                <col style="width: 10%;">
                                <col style="width: 8%;">
                                <col style="width: 14%;">
                            </colgroup>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Relationship</th>
                                    <th>Contact</th>
                                    <th>Children</th>
                                    <th>Face ID</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($parents as $parent): ?>
                                    <tr data-status="<?php echo $parent['status']; ?>" data-children="<?php echo $parent['children_count'] > 0 ? 'with-children' : 'no-children'; ?>">
                                        <td>
                                            <strong style="color: var(--primary-color); font-size: 0.875rem;">P-<?php echo str_pad($parent['parent_id'], 4, '0', STR_PAD_LEFT); ?></strong>
                                        </td>
                                        <td>
                                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                                <div style="width: 32px; height: 32px; border-radius: 50%; background: rgba(37, 99, 235, 0.1); display: flex; align-items: center; justify-content: center; color: var(--primary-color); font-weight: 600; font-size: 0.75rem; flex-shrink: 0;">
                                                    <?php echo strtoupper(substr($parent['first_name'], 0, 1)); ?>
                                                </div>
                                                <div style="overflow: hidden;">
                                                    <div style="color: var(--text-primary); font-weight: 600; font-size: 0.875rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?php echo htmlspecialchars($parent['first_name'] . ' ' . $parent['last_name']); ?></div>
                                                    <div style="color: var(--text-secondary); font-size: 0.75rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?php echo htmlspecialchars($parent['email']); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div style="color: var(--text-primary); font-size: 0.875rem; font-weight: 500;">
                                                <?php echo htmlspecialchars(ucfirst($parent['relationship'] ?? 'N/A')); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($parent['phone']): ?>
                                                <div style="font-size: 0.875rem;">
                                                    <i class="fas fa-phone" style="color: var(--success-color); margin-right: 0.25rem; font-size: 0.75rem;"></i>
                                                    <span><?php echo htmlspecialchars($parent['phone']); ?></span>
                                                </div>
                                            <?php else: ?>
                                                <span style="color: var(--text-tertiary); font-size: 0.875rem;">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($parent['children_count'] > 0): ?>
                                                <span style="color: var(--primary-color); font-size: 0.875rem; font-weight: 600;">
                                                    <i class="fas fa-child"></i> <?php echo $parent['children_count']; ?>
                                                </span>
                                            <?php else: ?>
                                                <span style="color: var(--text-tertiary); font-size: 0.875rem;">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($parent['has_face_data'] > 0): ?>
                                                <span style="color: var(--success-color); font-size: 0.875rem;"><i class="fas fa-check-circle"></i> Yes</span>
                                            <?php else: ?>
                                                <span style="color: var(--danger-color); font-size: 0.875rem;"><i class="fas fa-times-circle"></i> No</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($parent['status'] === 'active'): ?>
                                                <span style="color: var(--success-color); font-size: 0.875rem;"><i class="fas fa-circle" style="font-size: 0.5rem;"></i> Active</span>
                                            <?php else: ?>
                                                <span style="color: var(--danger-color); font-size: 0.875rem;"><i class="fas fa-circle" style="font-size: 0.5rem;"></i> Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div style="display: flex; gap: 0.3rem; align-items: center; justify-content: center;">
                                                <button class="btn btn-sm btn-view" onclick="viewParent(<?php echo $parent['parent_id']; ?>)" title="View Details" style="padding: 0.4rem 0.6rem;">
                                                    <i class="fas fa-eye" style="font-size: 0.75rem;"></i>
                                                </button>
                                                <button class="btn btn-sm btn-edit" onclick="editParent(<?php echo $parent['user_id']; ?>)" title="Edit Parent" style="padding: 0.4rem 0.6rem;">
                                                    <i class="fas fa-edit" style="font-size: 0.75rem;"></i>
                                                </button>
                                                <?php if ($parent['children_count'] > 0): ?>
                                                <button class="btn btn-sm" style="background: rgba(16, 185, 129, 0.1); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.2); padding: 0.4rem 0.6rem;" onclick="viewChildren(<?php echo $parent['parent_id']; ?>)" title="View Children">
                                                    <i class="fas fa-child" style="font-size: 0.75rem;"></i>
                                                </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <!-- View Parent Modal -->
    <div id="viewParentModal" class="modal">
        <div class="modal-content" style="max-width: 800px;">
            <div class="modal-header">
                <h2><i class="fas fa-users-between-lines"></i> Parent Details</h2>
                <span class="close" onclick="closeViewParentModal()">&times;</span>
            </div>
            <div class="modal-body" id="viewParentContent">
                <div style="text-align: center; padding: 2rem;">
                    <i class="fas fa-spinner fa-spin" style="font-size: 2rem; color: var(--primary-color);"></i>
                    <p>Loading parent details...</p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- View Children Modal -->
    <div id="viewChildrenModal" class="modal">
        <div class="modal-content" style="max-width: 700px;">
            <div class="modal-header">
                <h2><i class="fas fa-child"></i> Children List</h2>
                <span class="close" onclick="closeViewChildrenModal()">&times;</span>
            </div>
            <div class="modal-body" id="viewChildrenContent">
                <div style="text-align: center; padding: 2rem;">
                    <i class="fas fa-spinner fa-spin" style="font-size: 2rem; color: var(--primary-color);"></i>
                    <p>Loading children...</p>
                </div>
            </div>
        </div>
    </div>
    
    <style>
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            overflow-y: auto;
        }
        
        .modal-content {
            background-color: var(--bg-primary);
            margin: 2% auto;
            padding: 0;
            border-radius: var(--border-radius-lg);
            max-width: 600px;
            box-shadow: var(--shadow-lg);
        }
        
        .modal-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header h2 {
            margin: 0;
            color: var(--text-primary);
        }
        
        .modal-body {
            padding: 1.5rem;
            max-height: calc(90vh - 150px);
            overflow-y: auto;
        }
        
        .close {
            color: var(--text-secondary);
            font-size: 2rem;
            font-weight: bold;
            cursor: pointer;
            transition: color 0.3s;
        }
        
        .close:hover {
            color: var(--text-primary);
        }
        
        .btn-view {
            background: rgba(59, 130, 246, 0.1);
            color: #3b82f6;
            border: 1px solid rgba(59, 130, 246, 0.2);
        }
        .btn-view:hover {
            background: rgba(59, 130, 246, 0.2);
            border-color: #3b82f6;
        }
        .btn-edit {
            background: rgba(168, 85, 247, 0.1);
            color: #a855f7;
            border: 1px solid rgba(168, 85, 247, 0.2);
        }
        .btn-edit:hover {
            background: rgba(168, 85, 247, 0.2);
            border-color: #a855f7;
        }
        
        .detail-section {
            margin-bottom: 2rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid var(--border-color);
        }
        .detail-section:last-of-type {
            border-bottom: none;
        }
        .detail-section h3 {
            color: var(--text-primary);
            margin-bottom: 1rem;
            font-size: 1.1rem;
        }
        .detail-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }
        .detail-item {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }
        .detail-item label {
            font-weight: 600;
            color: var(--text-secondary);
            font-size: 0.875rem;
        }
        .detail-item span {
            color: var(--text-primary);
            font-size: 1rem;
        }
        .child-card {
            padding: 1rem;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            transition: all 0.3s;
        }
        .child-card:hover {
            background: var(--bg-secondary);
            border-color: var(--primary-color);
        }
    </style>
    
    <script src="../../assets/js/theme.js"></script>
    <script>
        // Search functionality
        document.getElementById('searchInput').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const rows = document.querySelectorAll('#parentsTable tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });
        
        // Status filter
        document.getElementById('statusFilter').addEventListener('change', function(e) {
            filterParents();
        });
        
        // Children filter
        document.getElementById('childrenFilter').addEventListener('change', function(e) {
            filterParents();
        });
        
        function filterParents() {
            const status = document.getElementById('statusFilter').value;
            const children = document.getElementById('childrenFilter').value;
            const rows = document.querySelectorAll('#parentsTable tbody tr');
            
            rows.forEach(row => {
                const rowStatus = row.dataset.status;
                const rowChildren = row.dataset.children;
                
                const statusMatch = !status || rowStatus === status;
                const childrenMatch = !children || rowChildren === children;
                
                row.style.display = (statusMatch && childrenMatch) ? '' : 'none';
            });
        }
        
        // View parent details
        async function viewParent(parentId) {
            document.getElementById('viewParentModal').style.display = 'block';
            
            try {
                const response = await fetch(`parent_handler.php?get_parent=1&parent_id=${parentId}`);
                const parent = await response.json();
                
                if (!parent || !parent.parent_id) {
                    document.getElementById('viewParentContent').innerHTML = '<div class="alert alert-error">Parent not found</div>';
                    return;
                }
                
                let html = '<div class="parent-details">';
                
                // Personal Information
                html += `<div class="detail-section">
                    <h3><i class="fas fa-user"></i> Personal Information</h3>
                    <div class="detail-grid">
                        <div class="detail-item">
                            <label>Parent ID:</label>
                            <span>P-${String(parent.parent_id).padStart(4, '0')}</span>
                        </div>
                        <div class="detail-item">
                            <label>Full Name:</label>
                            <span>${parent.first_name} ${parent.last_name}</span>
                        </div>
                        <div class="detail-item">
                            <label>Relationship:</label>
                            <span>${parent.relationship ? parent.relationship.charAt(0).toUpperCase() + parent.relationship.slice(1) : 'N/A'}</span>
                        </div>
                        <div class="detail-item">
                            <label>NIC:</label>
                            <span>${parent.nic || 'N/A'}</span>
                        </div>
                        <div class="detail-item">
                            <label>Phone:</label>
                            <span>${parent.phone || 'N/A'}</span>
                        </div>
                        <div class="detail-item">
                            <label>Address:</label>
                            <span>${parent.address || 'N/A'}</span>
                        </div>
                    </div>
                </div>`;
                
                // Account Information
                html += `<div class="detail-section">
                    <h3><i class="fas fa-key"></i> Account Information</h3>
                    <div class="detail-grid">
                        <div class="detail-item">
                            <label>Username:</label>
                            <span>${parent.username}</span>
                        </div>
                        <div class="detail-item">
                            <label>Email:</label>
                            <span>${parent.email}</span>
                        </div>
                        <div class="detail-item">
                            <label>Status:</label>
                            <span style="color: ${parent.status === 'active' ? 'var(--success-color)' : 'var(--danger-color)'}">
                                ${parent.status ? parent.status.toUpperCase() : 'N/A'}
                            </span>
                        </div>
                        <div class="detail-item">
                            <label>Face Recognition:</label>
                            <span style="color: ${parent.has_face_data ? 'var(--success-color)' : 'var(--danger-color)'}">
                                ${parent.has_face_data ? 'Registered' : 'Not Set'}
                            </span>
                        </div>
                    </div>
                </div>`;
                
                // Children Information
                if (parent.children_count > 0) {
                    html += `<div class="detail-section">
                        <h3><i class="fas fa-child"></i> Children</h3>
                        <p style="color: var(--text-secondary);">This parent has <strong>${parent.children_count}</strong> registered child${parent.children_count > 1 ? 'ren' : ''}.</p>
                        <button class="btn btn-primary" onclick="closeViewParentModal(); viewChildren(${parent.parent_id});" style="margin-top: 0.5rem;">
                            <i class="fas fa-child"></i> View Children Details
                        </button>
                    </div>`;
                }
                
                html += '</div>';
                html += `<div style="margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid var(--border-color); display: flex; gap: 0.75rem; justify-content: flex-end;">
                    <button class="btn btn-outline" onclick="closeViewParentModal()" style="padding: 0.75rem 1.5rem;">
                        <i class="fas fa-times"></i> Close
                    </button>
                    <button class="btn btn-primary" onclick="closeViewParentModal(); editParent(${parent.user_id});" style="padding: 0.75rem 1.5rem;">
                        <i class="fas fa-edit"></i> Edit Parent
                    </button>
                </div>`;
                
                document.getElementById('viewParentContent').innerHTML = html;
            } catch (error) {
                document.getElementById('viewParentContent').innerHTML = '<div class="alert alert-error">Error loading parent details</div>';
            }
        }
        
        function closeViewParentModal() {
            document.getElementById('viewParentModal').style.display = 'none';
        }
        
        // View children
        async function viewChildren(parentId) {
            document.getElementById('viewChildrenModal').style.display = 'block';
            
            try {
                const response = await fetch(`parent_handler.php?get_children=1&parent_id=${parentId}`);
                const children = await response.json();
                
                if (!children || children.length === 0) {
                    document.getElementById('viewChildrenContent').innerHTML = '<div class="alert alert-info">No children found for this parent</div>';
                    return;
                }
                
                let html = '<div class="children-list">';
                
                children.forEach(child => {
                    html += `<div class="child-card">
                        <div style="width: 48px; height: 48px; border-radius: 50%; background: rgba(37, 99, 235, 0.1); display: flex; align-items: center; justify-content: center; color: var(--primary-color); font-weight: 600; font-size: 1.25rem; flex-shrink: 0;">
                            ${child.first_name.charAt(0).toUpperCase()}
                        </div>
                        <div style="flex: 1;">
                            <div style="font-weight: 600; color: var(--text-primary); margin-bottom: 0.25rem;">
                                ${child.first_name} ${child.last_name}
                            </div>
                            <div style="color: var(--text-secondary); font-size: 0.875rem;">
                                <i class="fas fa-id-card"></i> ${child.student_number} | 
                                <i class="fas fa-graduation-cap"></i> Grade ${child.grade} - ${child.class_section}
                            </div>
                        </div>
                        <button class="btn btn-sm btn-view" onclick="closeViewChildrenModal(); window.location.href='students.php?view=${child.student_id}';" style="padding: 0.5rem 1rem;">
                            <i class="fas fa-eye"></i> View
                        </button>
                    </div>`;
                });
                
                html += '</div>';
                html += `<div style="margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end;">
                    <button class="btn btn-outline" onclick="closeViewChildrenModal()" style="padding: 0.75rem 1.5rem;">
                        <i class="fas fa-times"></i> Close
                    </button>
                </div>`;
                
                document.getElementById('viewChildrenContent').innerHTML = html;
            } catch (error) {
                document.getElementById('viewChildrenContent').innerHTML = '<div class="alert alert-error">Error loading children</div>';
            }
        }
        
        function closeViewChildrenModal() {
            document.getElementById('viewChildrenModal').style.display = 'none';
        }
        
        function editParent(userId) {
            window.location.href = `users.php?edit_parent=${userId}`;
        }
    </script>
</body>
</html>
