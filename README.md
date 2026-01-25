# EduID - Educational Identity Verification System

## Complete Setup Guide

---

## 📋 Table of Contents

1. [System Requirements](#system-requirements)
2. [Installation Steps](#installation-steps)
3. [Database Setup](#database-setup)
4. [Configuration](#configuration)
5. [Face Recognition Setup](#face-recognition-setup)
6. [QR Code Library Installation](#qr-code-library-installation)
7. [Running the Application](#running-the-application)
8. [Default Credentials](#default-credentials)
9. [Features Overview](#features-overview)
10. [Troubleshooting](#troubleshooting)

---

## 🖥️ System Requirements

### Minimum Requirements:

- **PHP**: 7.4 or higher
- **MySQL**: 5.7 or higher / MariaDB 10.3+
- **Web Server**: Apache 2.4+ / Nginx 1.18+
- **Server Stack Options**: XAMPP, WAMP, MAMP, Laragon, or individual installations
- **Browser**: Modern browser with WebRTC support (Chrome, Firefox, Edge, Safari)
- **Webcam**: Required for face registration feature
- **RAM**: 4GB minimum
- **Storage**: 500MB free space

### Recommended:

- **PHP**: 8.0+
- **MySQL**: 8.0+
- **RAM**: 8GB
- **Modern multi-core processor**

---

## 📦 Installation Steps

### Step 1: Choose Your Server Stack

#### **Option A: XAMPP (Recommended for Beginners)**

1. Download [XAMPP](https://www.apachefriends.org/) for your OS
2. Install to default location (C:\xampp on Windows)
3. Open XAMPP Control Panel
4. Start Apache and MySQL services

#### **Option B: WAMP (Windows Alternative)**

1. Download [WAMP Server](https://www.wampserver.com/)
2. Install and run WampServer
3. Click system tray icon → Make sure services are green

#### **Option C: Laragon (Modern Choice)**

1. Download [Laragon](https://laragon.org/)
2. Install and auto-start services
3. Uses isolated environments per project

#### **Option D: Individual Installation**

1. **Install PHP**:
   - Download from [php.net](https://www.php.net/downloads)
   - Add to system PATH
2. **Install MySQL Server**:
   - Download [MySQL Community Server](https://dev.mysql.com/downloads/mysql/)
   - Set root password during installation
3. **Install Apache**:
   - Download [Apache HTTP Server](https://httpd.apache.org/download.cgi)
   - Configure to use PHP module
4. **MySQL Workbench** (Optional but recommended):
   - Download [MySQL Workbench](https://dev.mysql.com/downloads/workbench/)
   - Use for database management GUI

### Step 2: Clone/Download Project

```bash
# Navigate to htdocs folder (XAMPP)
cd C:\xampp\htdocs

# Create project folder
mkdir eduid
cd eduid

# Copy all project files to this directory
```

Alternatively, copy the `Dev` folder contents to `C:\xampp\htdocs\eduid\`

### Step 3: Install PHP QR Code Library

#### Option A: Using Composer (Recommended)

```bash
cd C:\xampp\htdocs\eduid
composer require phpqrcode/phpqrcode
```

#### Option B: Manual Installation

1. Download PHPQRCode from: https://github.com/t0k4rt/phpqrcode
2. Extract to `vendor/phpqrcode/` folder
3. Ensure the `qrlib.php` file is at `vendor/phpqrcode/qrlib.php`

### Step 4: Set Permissions

For Linux/Mac:

```bash
chmod -R 755 /path/to/eduid
chmod -R 777 /path/to/eduid/uploads
```

For Windows: Ensure the `uploads` folder has write permissions.

---

## 🗄️ Database Setup

### Step 1: Open MySQL Workbench

1. Launch MySQL Workbench
2. Connect to your local MySQL server
3. Default connection: `localhost:3306`
4. Username: `root`, Password: (usually empty on XAMPP)

### Step 2: Import Database Schema

1. In MySQL Workbench, click **File** → **Open SQL Script**
2. Navigate to `/database/schema.sql`
3. Click **Execute** (lightning bolt icon) to run the script
4. Verify that `eduid_system` database is created

### Step 3: Verify Database Creation

```sql
USE eduid_system;
SHOW TABLES;
```

You should see all tables created (users, students, teachers, parents, etc.)

### Step 4: Default Admin Account

The schema automatically creates an admin account:

- **Username**: `admin`
- **Email**: `admin@eduid.com`
- **Password**: `Admin@123`

---

## ⚙️ Configuration

### Step 1: Update Database Configuration

Edit `config/database.php`:

```php
private $host = "localhost";
private $db_name = "eduid_system";
private $username = "root";
private $password = "";  // Your MySQL password (usually empty for XAMPP)
```

### Step 2: Update Base URL

Edit `config/config.php`:

```php
define('BASE_URL', 'http://localhost/eduid/');
```

Change `/eduid/` to match your project folder name if different.

### Step 3: Verify Upload Directories

The system automatically creates these folders:

- `uploads/profiles/`
- `uploads/face_data/`
- `uploads/qr_codes/`

Ensure they have write permissions.

---

## 👤 Face Recognition Setup

### Step 1: Download Face-API.js Models

1. Download face detection models from: https://github.com/justadudewhohacks/face-api.js-models
2. Create folder: `assets/models/`
3. Copy these model files into the `models` folder:
   - `tiny_face_detector_model-weights_manifest.json`
   - `tiny_face_detector_model-shard1`
   - `face_landmark_68_model-weights_manifest.json`
   - `face_landmark_68_model-shard1`
   - `face_recognition_model-weights_manifest.json`
   - `face_recognition_model-shard1`
   - `face_expression_model-weights_manifest.json`
   - `face_expression_model-shard1`

### Step 2: Test Webcam Access

1. Open `http://localhost/eduid` in Chrome/Firefox
2. Allow camera permissions when prompted
3. Navigate to Face Registration page
4. Click "Start Camera" and verify video stream appears

### Step 3: Test Face Detection

1. Position your face within the circular guide
2. Click "Capture Face"
3. Verify face landmarks are detected (green points on face)
4. Click "Save" to register face data

---

## 📱 QR Code Library Installation

### Composer Method (Recommended):

```bash
cd C:\xampp\htdocs\eduid
composer require phpqrcode/phpqrcode
```

### Manual Method:

1. Download: https://github.com/t0k4rt/phpqrcode/archive/master.zip
2. Extract to `vendor/phpqrcode/`
3. Verify file structure:
   ```
   vendor/
   └── phpqrcode/
       ├── qrlib.php
       ├── qrconfig.php
       ├── qrtools.php
       └── ... other files
   ```

### Update Include Path:

In `dashboards/student/qr-code.php`, verify:

```php
require_once '../../vendor/phpqrcode/qrlib.php';
```

---

## 🚀 Running the Application

### Step 1: Start Services

1. Open XAMPP Control Panel
2. Start **Apache** and **MySQL**
3. Verify both are running (green status)

### Step 2: Access Application

Open your browser and navigate to:

```
http://localhost/eduid/
```

### Step 3: Sign In

1. Click **Sign In** button on landing page
2. Use default credentials:
   - **Username**: `admin`
   - **Password**: `Admin@123`
3. You'll be redirected to Admin Dashboard

### Step 4: Create Test Users

From Admin Dashboard:

1. Go to **User Management**
2. Click **Add New Student**
3. Fill in student details
4. System will auto-generate credentials
5. Repeat for Teachers and Parents

---

## 🔑 Default Credentials

### Admin Account

- **Username**: `admin`
- **Email**: `admin@eduid.com`
- **Password**: `Admin@123`
- **Access**: Full system control

### Creating Additional Users

All users (Students, Teachers, Parents) must be created by Admin through:
**Admin Dashboard → User Management → Add New User**

The system will generate:

- Username (based on name)
- Temporary password
- Email notification (if configured)

---

## ✨ Features Overview

### Admin Portal

- ✅ Dashboard with analytics and statistics
- ✅ Complete user management (Add/Edit/Delete/Suspend)
- ✅ Student, Teacher, Parent registration
- ✅ Attendance tracking and reports
- ✅ Exam management
- ✅ Event creation and management
- ✅ System settings configuration
- ✅ Access logs and audit trails
- ✅ Report generation and export

### Student Portal

- ✅ Personal dashboard with attendance overview
- ✅ Unique QR code generation and download
- ✅ Face registration for biometric verification
- ✅ Attendance history and statistics
- ✅ Upcoming exams view
- ✅ Event registration and check-in
- ✅ Profile management

### Teacher Portal

- ✅ QR code scanner for attendance
- ✅ Face recognition verification
- ✅ Class attendance marking
- ✅ Student list management
- ✅ Exam entry verification
- ✅ Event attendance tracking
- ✅ Reports generation

### Parent Portal

- ✅ View children's attendance
- ✅ Academic performance tracking
- ✅ Event participation history
- ✅ Real-time notifications
- ✅ Communication with teachers

### Security Features

- ✅ Dual-factor verification (QR + Face)
- ✅ Encrypted password storage
- ✅ Role-based access control
- ✅ Session management
- ✅ Activity logging
- ✅ Audit trails

### UI/UX Features

- ✅ Light and Dark mode
- ✅ Responsive design
- ✅ Modern gradient interface
- ✅ Real-time updates
- ✅ Intuitive navigation
- ✅ Professional dashboard layouts

---

## 🐛 Troubleshooting

### Issue: "Database connection failed"

**Solution:**

1. Verify MySQL is running in XAMPP
2. Check database credentials in `config/database.php`
3. Ensure `eduid_system` database exists
4. Run schema.sql if database is missing

### Issue: "QR Code not generating"

**Solution:**

1. Verify PHPQRCode library is installed
2. Check `vendor/phpqrcode/qrlib.php` exists
3. Ensure `uploads/qr_codes/` folder has write permissions
4. Check PHP error logs for details

### Issue: "Camera not working"

**Solution:**

1. Use HTTPS or localhost (required for WebRTC)
2. Grant camera permissions in browser
3. Close other applications using webcam
4. Try different browser (Chrome recommended)
5. Check browser console for errors

### Issue: "Face models not loading"

**Solution:**

1. Download all model files from GitHub
2. Place in `assets/models/` folder
3. Check browser console for 404 errors
4. Verify file paths in JavaScript

### Issue: "Cannot write to upload folder"

**Solution:**
Windows:

- Right-click `uploads` folder → Properties → Security
- Give write permissions to Users group

Linux:

```bash
sudo chmod -R 777 /path/to/eduid/uploads
```

### Issue: "Page not found / 404 error"

**Solution:**

1. Verify project is in correct folder (htdocs/eduid)
2. Check BASE_URL in `config/config.php`
3. Ensure Apache is running
4. Clear browser cache

### Issue: "Login page loops / redirects"

**Solution:**

1. Check PHP session is working
2. Verify `session_start()` is called
3. Clear browser cookies
4. Check PHP error logs

---

## 📧 Additional Support

### Checking PHP Configuration

Create `phpinfo.php` in project root:

```php
<?php
phpinfo();
?>
```

Visit `http://localhost/eduid/phpinfo.php` to verify PHP settings.

### Enable Error Reporting

In `config/config.php`, ensure:

```php
error_reporting(E_ALL);
ini_set('display_errors', 1);
```

### MySQL Workbench Connection

If you can't connect:

1. Check MySQL is running on port 3306
2. Verify username/password
3. Try resetting MySQL password in XAMPP

---

## 🎉 Success Indicators

You've successfully set up EduID when:

- ✅ Landing page loads at `http://localhost/eduid/`
- ✅ Theme toggle works (light/dark mode)
- ✅ Admin login successful with default credentials
- ✅ Admin dashboard displays statistics
- ✅ You can create a test student
- ✅ Student can view their QR code
- ✅ Camera starts for face registration
- ✅ Face detection models load successfully

---

## 📁 Project Structure

```
Dev/
├── assets/
│   ├── css/
│   │   └── style.css
│   ├── js/
│   │   ├── theme.js
│   │   └── face-recognition.js
│   ├── images/
│   │   └── logo.svg
│   └── models/        (Face-API.js models - download separately)
├── auth/
│   ├── login.php
│   └── logout.php
├── config/
│   ├── config.php
│   └── database.php
├── dashboards/
│   ├── admin/
│   │   └── index.php
│   ├── student/
│   │   ├── index.php
│   │   ├── qr-code.php
│   │   ├── face-registration.php
│   │   └── save-face.php
│   ├── teacher/
│   └── parent/
├── database/
│   └── schema.sql
├── uploads/          (Auto-created)
│   ├── profiles/
│   ├── face_data/
│   └── qr_codes/
├── vendor/           (Composer dependencies)
│   └── phpqrcode/
├── index.html
└── README.md
```

---

## 🔐 Security Best Practices

### For Production:

1. **Change Default Credentials**: Update admin password immediately
2. **Use HTTPS**: Install SSL certificate
3. **Disable Error Display**: Set `display_errors = 0` in production
4. **Database Security**: Use strong MySQL password
5. **File Permissions**: Set proper folder permissions (755 for folders, 644 for files)
6. **Regular Backups**: Backup database regularly
7. **Update Dependencies**: Keep PHP and libraries updated

---

## 📞 Contact & Support

For issues or questions:

1. Check this README thoroughly
2. Review browser console for JavaScript errors
3. Check PHP error logs in XAMPP
4. Verify all installation steps completed

**System Version**: 1.0.0
**Last Updated**: January 2026

---

## 🎓 Quick Start Checklist

- [ ] XAMPP installed and running
- [ ] Project files in htdocs/eduid
- [ ] Database created using schema.sql
- [ ] Config files updated with correct paths
- [ ] PHPQRCode library installed
- [ ] Face-API.js models downloaded
- [ ] Upload folders created with permissions
- [ ] Admin login successful
- [ ] Test student created
- [ ] QR code generated
- [ ] Camera works for face registration

**Congratulations! Your EduID system is ready to use! 🎉**
