<?php
require_once '../../config/config.php';
checkRole(['admin']);

$db = new Database();
$conn = $db->getConnection();

// Get all exam halls
$query = "SELECT h.*, 
          (SELECT COUNT(*) FROM exam_seat_assignments esa 
           JOIN exams e ON esa.exam_id = e.exam_id 
           WHERE esa.hall_id = h.hall_id AND e.exam_date >= CURDATE()) as upcoming_exams
          FROM exam_halls h ORDER BY h.hall_name";
$stmt = $conn->prepare($query);
$stmt->execute();
$halls = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Statistics
$stats = [
    'total_halls' => count($halls),
    'active_halls' => count(array_filter($halls, fn($h) => $h['status'] === 'active')),
    'total_capacity' => array_sum(array_column($halls, 'capacity')),
    'cctv_enabled' => count(array_filter($halls, fn($h) => $h['has_cctv']))
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exam Halls - EduID</title>
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
                    <a href="students.php" class="nav-item">
                        <i class="fas fa-user-graduate"></i>
                        <span>Students</span>
                    </a>
                    <a href="teachers.php" class="nav-item">
                        <i class="fas fa-chalkboard-teacher"></i>
                        <span>Teachers</span>
                    </a>
                    <a href="parents.php" class="nav-item">
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
                    <a href="exam_halls.php" class="nav-item active">
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
                    <h1>Exam Halls</h1>
                    <div class="breadcrumb">
                        <span>Home</span>
                        <i class="fas fa-chevron-right"></i>
                        <span>Examinations</span>
                        <i class="fas fa-chevron-right"></i>
                        <span>Exam Halls</span>
                    </div>
                </div>
                
                <div class="header-right">
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
                <!-- Stats Overview -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon primary">
                            <i class="fas fa-door-open"></i>
                        </div>
                        <div class="stat-details">
                            <h3><?php echo $stats['total_halls']; ?></h3>
                            <p>Total Halls</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon success">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-details">
                            <h3><?php echo $stats['active_halls']; ?></h3>
                            <p>Active Halls</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon info">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-details">
                            <h3><?php echo $stats['total_capacity']; ?></h3>
                            <p>Total Capacity</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon warning">
                            <i class="fas fa-video"></i>
                        </div>
                        <div class="stat-details">
                            <h3><?php echo $stats['cctv_enabled']; ?></h3>
                            <p>CCTV Enabled</p>
                        </div>
                    </div>
                </div>
                
                <!-- Exam Halls List -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-door-open"></i> Exam Halls Management</h3>
                        <button class="btn btn-primary" onclick="showAddHallModal()">
                            <i class="fas fa-plus"></i> Add Hall
                        </button>
                    </div>
                    <div class="table-container">
                        <table style="width: 100%; table-layout: fixed;">
                            <colgroup>
                                <col style="width: 12%;">
                                <col style="width: 22%;">
                                <col style="width: 20%;">
                                <col style="width: 10%;">
                                <col style="width: 12%;">
                                <col style="width: 10%;">
                                <col style="width: 14%;">
                            </colgroup>
                            <thead>
                                <tr>
                                    <th>Code</th>
                                    <th>Hall Name</th>
                                    <th>Location</th>
                                    <th>Capacity</th>
                                    <th>Features</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($halls) > 0): ?>
                                    <?php foreach ($halls as $hall): ?>
                                        <tr>
                                            <td>
                                                <strong style="color: var(--primary-color);"><?php echo htmlspecialchars($hall['hall_code']); ?></strong>
                                            </td>
                                            <td>
                                                <div style="display: flex; align-items: center; gap: 0.75rem;">
                                                    <div style="width: 40px; height: 40px; border-radius: 8px; background: rgba(37, 99, 235, 0.1); display: flex; align-items: center; justify-content: center; color: var(--primary-color);">
                                                        <i class="fas fa-door-open"></i>
                                                    </div>
                                                    <div style="overflow: hidden;">
                                                        <div style="font-weight: 600; color: var(--text-primary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                                            <?php echo htmlspecialchars($hall['hall_name']); ?>
                                                        </div>
                                                        <?php if ($hall['upcoming_exams'] > 0): ?>
                                                        <div style="font-size: 0.75rem; color: var(--warning-color);">
                                                            <i class="fas fa-calendar"></i> <?php echo $hall['upcoming_exams']; ?> upcoming exam(s)
                                                        </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span style="color: var(--text-secondary); font-size: 0.875rem;">
                                                    <?php echo htmlspecialchars($hall['location'] ?: '-'); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span style="font-weight: 600; color: var(--text-primary);">
                                                    <?php echo $hall['capacity']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div style="display: flex; gap: 0.5rem;">
                                                    <?php if ($hall['has_cctv']): ?>
                                                    <span title="CCTV" style="color: var(--success-color);"><i class="fas fa-video"></i></span>
                                                    <?php endif; ?>
                                                    <?php if ($hall['has_ac']): ?>
                                                    <span title="Air Conditioned" style="color: var(--info-color);"><i class="fas fa-snowflake"></i></span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <?php
                                                $statusColors = [
                                                    'active' => 'var(--success-color)',
                                                    'inactive' => 'var(--danger-color)',
                                                    'maintenance' => 'var(--warning-color)'
                                                ];
                                                $statusColor = $statusColors[$hall['status']] ?? 'var(--text-secondary)';
                                                ?>
                                                <span style="padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; background: <?php echo $statusColor; ?>22; color: <?php echo $statusColor; ?>;">
                                                    <?php echo ucfirst($hall['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div style="display: flex; gap: 0.3rem; justify-content: center;">
                                                    <button class="btn btn-sm btn-view" onclick="viewHall(<?php echo $hall['hall_id']; ?>)" title="View">
                                                        <i class="fas fa-eye" style="font-size: 0.75rem;"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-edit" onclick="editHall(<?php echo $hall['hall_id']; ?>)" title="Edit">
                                                        <i class="fas fa-edit" style="font-size: 0.75rem;"></i>
                                                    </button>
                                                    <button class="btn btn-sm" style="background: rgba(239, 68, 68, 0.1); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.2); padding: 0.4rem 0.6rem;" onclick="deleteHall(<?php echo $hall['hall_id']; ?>)" title="Delete">
                                                        <i class="fas fa-trash" style="font-size: 0.75rem;"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" style="text-align: center; padding: 3rem; color: var(--text-secondary);">
                                            <i class="fas fa-door-open" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.3;"></i>
                                            <p>No exam halls found. Click "Add Hall" to create one.</p>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Add/Edit Hall Modal -->
    <div id="hallModal" class="modal">
        <div class="modal-content" style="max-width: 600px;">
            <div class="modal-header">
                <h2 id="hallModalTitle"><i class="fas fa-door-open"></i> Add Exam Hall</h2>
                <span class="close" onclick="closeHallModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form id="hallForm">
                    <input type="hidden" id="hall_id" name="hall_id">
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                        <div>
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: var(--text-secondary);">Hall Code *</label>
                            <input type="text" id="hall_code" name="hall_code" required style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: 6px; background: var(--bg-primary);" placeholder="e.g., HALL-A">
                        </div>
                        <div>
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: var(--text-secondary);">Hall Name *</label>
                            <input type="text" id="hall_name" name="hall_name" required style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: 6px; background: var(--bg-primary);" placeholder="Examination Hall A">
                        </div>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                        <div>
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: var(--text-secondary);">Location</label>
                            <input type="text" id="location" name="location" style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: 6px; background: var(--bg-primary);" placeholder="Building/Floor">
                        </div>
                        <div>
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: var(--text-secondary);">Capacity *</label>
                            <input type="number" id="capacity" name="capacity" required min="1" style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: 6px; background: var(--bg-primary);" placeholder="30">
                        </div>
                    </div>
                    
                    <div style="margin-bottom: 1rem;">
                        <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: var(--text-secondary);">Description</label>
                        <textarea id="description" name="description" rows="3" style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: 6px; background: var(--bg-primary); resize: vertical;" placeholder="Additional details about the hall..."></textarea>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; margin-bottom: 1rem;">
                        <div>
                            <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                                <input type="checkbox" id="has_cctv" name="has_cctv" style="width: 18px; height: 18px;">
                                <span style="color: var(--text-primary);"><i class="fas fa-video"></i> CCTV</span>
                            </label>
                        </div>
                        <div>
                            <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                                <input type="checkbox" id="has_ac" name="has_ac" style="width: 18px; height: 18px;">
                                <span style="color: var(--text-primary);"><i class="fas fa-snowflake"></i> Air Conditioned</span>
                            </label>
                        </div>
                        <div>
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: var(--text-secondary);">Status</label>
                            <select id="status" name="status" style="width: 100%; padding: 0.5rem; border: 1px solid var(--border-color); border-radius: 6px; background: var(--bg-primary);">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="maintenance">Maintenance</option>
                            </select>
                        </div>
                    </div>
                    
                    <div style="display: flex; gap: 0.75rem; justify-content: flex-end; margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid var(--border-color);">
                        <button type="button" class="btn btn-secondary" onclick="closeHallModal()">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Hall
                        </button>
                    </div>
                </form>
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
            font-size: 1.25rem;
        }
        
        .modal-body {
            padding: 1.5rem;
        }
        
        .close {
            color: var(--text-secondary);
            font-size: 2rem;
            font-weight: bold;
            cursor: pointer;
        }
        
        .btn-view {
            background: rgba(34, 197, 94, 0.1);
            color: #22c55e;
            border: 1px solid rgba(34, 197, 94, 0.2);
            padding: 0.4rem 0.6rem;
        }
        .btn-edit {
            background: rgba(168, 85, 247, 0.1);
            color: #a855f7;
            border: 1px solid rgba(168, 85, 247, 0.2);
            padding: 0.4rem 0.6rem;
        }
    </style>
    
    <script src="../../assets/js/theme.js"></script>
    <script>
        function showAddHallModal() {
            document.getElementById('hallModalTitle').innerHTML = '<i class="fas fa-door-open"></i> Add Exam Hall';
            document.getElementById('hallForm').reset();
            document.getElementById('hall_id').value = '';
            document.getElementById('hallModal').style.display = 'block';
        }
        
        function closeHallModal() {
            document.getElementById('hallModal').style.display = 'none';
        }
        
        async function editHall(hallId) {
            try {
                const response = await fetch(`exam_handler.php?action=get_hall&id=${hallId}`);
                const data = await response.json();
                
                if (data.success) {
                    const hall = data.hall;
                    document.getElementById('hallModalTitle').innerHTML = '<i class="fas fa-edit"></i> Edit Exam Hall';
                    document.getElementById('hall_id').value = hall.hall_id;
                    document.getElementById('hall_code').value = hall.hall_code;
                    document.getElementById('hall_name').value = hall.hall_name;
                    document.getElementById('location').value = hall.location || '';
                    document.getElementById('capacity').value = hall.capacity;
                    document.getElementById('description').value = hall.description || '';
                    document.getElementById('has_cctv').checked = hall.has_cctv == 1;
                    document.getElementById('has_ac').checked = hall.has_ac == 1;
                    document.getElementById('status').value = hall.status;
                    document.getElementById('hallModal').style.display = 'block';
                }
            } catch (error) {
                alert('Error loading hall data');
            }
        }
        
        async function deleteHall(hallId) {
            if (!confirm('Are you sure you want to delete this exam hall?')) return;
            
            try {
                const response = await fetch('exam_handler.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'delete_hall', hall_id: hallId })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    location.reload();
                } else {
                    alert('Error: ' + result.message);
                }
            } catch (error) {
                alert('Error deleting hall');
            }
        }
        
        document.getElementById('hallForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = {
                action: document.getElementById('hall_id').value ? 'update_hall' : 'add_hall',
                hall_id: document.getElementById('hall_id').value,
                hall_code: document.getElementById('hall_code').value,
                hall_name: document.getElementById('hall_name').value,
                location: document.getElementById('location').value,
                capacity: document.getElementById('capacity').value,
                description: document.getElementById('description').value,
                has_cctv: document.getElementById('has_cctv').checked,
                has_ac: document.getElementById('has_ac').checked,
                status: document.getElementById('status').value
            };
            
            try {
                const response = await fetch('exam_handler.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(formData)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    location.reload();
                } else {
                    alert('Error: ' + result.message);
                }
            } catch (error) {
                alert('Error saving hall');
            }
        });
    </script>
</body>
</html>
