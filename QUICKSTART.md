# 🚀 EduID Quick Start Guide

## Get Your System Running in 15 Minutes!

---

## ✅ Pre-Installation Checklist

Download and install these before starting:

- [ ] **XAMPP** for Windows: https://www.apachefriends.org/download.html
- [ ] **MySQL Workbench**: https://dev.mysql.com/downloads/workbench/
- [ ] **Modern Web Browser**: Chrome, Firefox, or Edge

---

## 📦 Step-by-Step Installation

### **STEP 1: Set Up Your Server Stack** (5 minutes)

#### **If Using XAMPP:**

1. **Install XAMPP**
   - Run the XAMPP installer
   - Choose default installation directory: `C:\xampp`
   - Complete the installation

2. **Start Services**
   - Launch XAMPP Control Panel
   - Click **Start** next to Apache
   - Click **Start** next to MySQL
   - Both should show GREEN status

#### **If Using WAMP:**

1. **Install WAMP**
   - Run WampServer installer
   - Install to `C:\wamp64`
2. **Start Services**
   - Click WampServer icon in system tray
   - Icon should be green (all services running)
   - If orange/red, click → Start All Services

#### **If Using Laragon:**

1. **Install Laragon**
   - Run Laragon installer
   - Choose installation path
2. **Start Services**
   - Open Laragon
   - Click **Start All**
   - Services auto-start

#### **If Using Individual Installations:**

1. **Start MySQL Service**
   - Open Services (Windows + R → services.msc)
   - Find MySQL80 → Start
2. **Start Apache**
   - Run httpd.exe or start via services
3. **Verify PHP**
   - Open Command Prompt
   - Type: `php -v`
   - Should show PHP version

   ![Services Running](https://img.shields.io/badge/Apache-Running-brightgreen) ![MySQL Running](https://img.shields.io/badge/MySQL-Running-brightgreen)

---

### **STEP 2: Install Project Files** (2 minutes)

1. **Copy Files**

   ```
   Navigate to: C:\xampp\htdocs\
   Create folder: eduid
   Copy all project files from 'Dev' folder into: C:\xampp\htdocs\eduid\
   ```

2. **Verify Structure**
   ```
   C:\xampp\htdocs\eduid\
   ├── index.html          ✓
   ├── config\             ✓
   ├── assets\             ✓
   ├── database\           ✓
   └── README.md           ✓
   ```

---

### **STEP 3: Create Database** (3 minutes)

1. **Open MySQL Workbench**
   - Launch MySQL Workbench
   - Click on **Local instance 3306**
   - Default password is usually empty (just click OK)

2. **Import Database Schema**
   - Click: **File → Open SQL Script**
   - Navigate to: `C:\xampp\htdocs\eduid\database\schema.sql`
   - Click the **Execute** button (⚡ lightning icon)
   - Wait for "Script executed successfully" message

3. **Verify Database**
   - In left sidebar, click **Refresh** (🔄)
   - You should see `eduid_system` database
   - Expand it to see all tables

   ✅ **Success!** Database created with default admin account.

---

### **STEP 4: Install QR Code Library** (3 minutes)

#### **Option A: Using Composer** (Recommended if you have Composer)

```bash
cd C:\xampp\htdocs\eduid
composer require phpqrcode/phpqrcode
```

#### **Option B: Manual Installation**

1. Download: https://github.com/t0k4rt/phpqrcode/archive/master.zip
2. Extract the ZIP file
3. Create folder: `C:\xampp\htdocs\eduid\vendor\phpqrcode\`
4. Copy all files from extracted folder into the phpqrcode directory
5. Verify `qrlib.php` exists at: `C:\xampp\htdocs\eduid\vendor\phpqrcode\qrlib.php`

---

### **STEP 5: Download Face Recognition Models** (2 minutes)

1. **Download Models**
   - Go to: https://github.com/justadudewhohacks/face-api.js-models
   - Click **Code → Download ZIP**
   - Extract the ZIP file

2. **Copy Model Files**
   - Create folder: `C:\xampp\htdocs\eduid\assets\models\`
   - Copy these files from extracted folder to models folder:
     ```
     ✓ tiny_face_detector_model-weights_manifest.json
     ✓ tiny_face_detector_model-shard1
     ✓ face_landmark_68_model-weights_manifest.json
     ✓ face_landmark_68_model-shard1
     ✓ face_recognition_model-weights_manifest.json
     ✓ face_recognition_model-shard1
     ✓ face_expression_model-weights_manifest.json
     ✓ face_expression_model-shard1
     ```

---

### **STEP 6: Launch Application!** (1 minute)

1. **Open Your Browser**
   - Launch Chrome, Firefox, or Edge
   - Go to: `http://localhost/eduid/`
2. **You Should See:**
   - Beautiful landing page with EduID logo
   - Light/Dark mode toggle working
   - "Sign In" button in the header

   🎉 **Congratulations! Your system is running!**

---

## 🔐 First Login

### **Admin Access**

1. Click **Sign In** button
2. Enter credentials:
   ```
   Username: admin
   Password: Admin@123
   ```
3. Click **Sign In**
4. You'll be redirected to Admin Dashboard

### **What You'll See:**

- ✅ Clean, modern dashboard
- ✅ Statistics cards showing system overview
- ✅ Navigation menu with all features
- ✅ Quick action buttons

---

## 👥 Creating Your First Users

### **Add a Student**

1. From Admin Dashboard, click **User Management** in sidebar
2. Click **Add New Student** button
3. Fill in the form:
   ```
   Student Number: S001
   First Name: John
   Last Name: Doe
   Date of Birth: 2005-01-15
   Grade: 10
   Class Section: A
   Email: john.doe@example.com
   Phone: +1234567890
   ```
4. Click **Save**
5. Note the auto-generated username and password

### **Add a Teacher**

1. Click **Add New Teacher**
2. Fill in details:
   ```
   Employee Number: T001
   First Name: Jane
   Last Name: Smith
   Department: Science
   Subject: Physics
   Email: jane.smith@example.com
   ```
3. Click **Save**

### **Add a Parent**

1. Click **Add New Parent**
2. Fill in details and link to student
3. Click **Save**

---

## 🎯 Testing Key Features

### **Test 1: Student QR Code** ✅

1. Logout and login as student (use credentials created above)
2. Navigate to **My QR Code** in sidebar
3. You should see:
   - Student's unique QR code displayed
   - Download button
   - Print button
4. Click **Download QR Code** to save it

**Expected Result:** QR code PNG file downloads successfully

---

### **Test 2: Face Registration** ✅

1. As student, go to **Face Registration**
2. Click **Start Camera**
3. Browser will ask for camera permission - Click **Allow**
4. Position face within circular guide
5. Click **Capture Face**
6. Wait for green detection markers
7. Click **Save Face Data**

**Expected Result:** "Face registered successfully!" message

---

### **Test 3: Theme Toggle** ✅

1. Click the moon/sun icon in header
2. Page should switch between light and dark mode
3. Theme preference is saved automatically

**Expected Result:** Smooth transition between themes

---

### **Test 4: Teacher Verification** ✅

1. Login as teacher
2. Go to **QR Scanner**
3. Click **Start Camera**
4. Show the student's QR code to camera
5. System should detect and decode QR code
6. Student information displayed

**Expected Result:** Student verified successfully

---

## 🐛 Common Issues & Quick Fixes

### ❌ **Problem: "Cannot connect to database"**

**Solution:**

1. Check MySQL is running in XAMPP (should be green)
2. Open MySQL Workbench and verify connection works
3. Check password in `config/database.php` (usually empty for XAMPP)

---

### ❌ **Problem: "QR Code not generating"**

**Solution:**

1. Verify folder exists: `C:\xampp\htdocs\eduid\uploads\qr_codes\`
2. Right-click uploads folder → Properties → Security → Give write permissions
3. Check if `vendor\phpqrcode\qrlib.php` exists

---

### ❌ **Problem: "Camera not working"**

**Solution:**

1. Use `http://localhost/eduid/` (localhost is required for camera access)
2. Click **Allow** when browser asks for camera permission
3. Close other apps using webcam (Skype, Teams, etc.)
4. Try different browser (Chrome recommended)

---

### ❌ **Problem: "Face models not loading"**

**Solution:**

1. Open browser console (F12)
2. Check for 404 errors
3. Verify all 8 model files are in `assets/models/` folder
4. Check file names match exactly (case-sensitive)

---

### ❌ **Problem: "Page not found / 404"**

**Solution:**

1. Verify Apache is running in XAMPP
2. Check URL is: `http://localhost/eduid/` (not `127.0.0.1`)
3. Verify files are in `C:\xampp\htdocs\eduid\` folder

---

### ❌ **Problem: "Blank white page"**

**Solution:**

1. Check PHP errors: Create file `test.php` in eduid folder:
   ```php
   <?php
   error_reporting(E_ALL);
   ini_set('display_errors', 1);
   echo "PHP is working!";
   ?>
   ```
2. Visit `http://localhost/eduid/test.php`
3. If you see "PHP is working!" - PHP is fine, check other files

---

## 📊 System Features Overview

### **Admin Portal** 🔧

- ✅ Complete user management
- ✅ System-wide analytics
- ✅ Attendance reports
- ✅ Event management
- ✅ System settings
- ✅ Access logs

### **Student Portal** 🎓

- ✅ Personal QR code
- ✅ Face registration
- ✅ Attendance history
- ✅ Exam schedule
- ✅ Event registration

### **Teacher Portal** 👨‍🏫

- ✅ QR code scanner
- ✅ Face verification
- ✅ Mark attendance
- ✅ Student management
- ✅ Reports

### **Parent Portal** 👨‍👩‍👧

- ✅ View children's attendance
- ✅ Performance tracking
- ✅ Event participation
- ✅ Real-time notifications

---

## 🎨 Customization Tips

### **Change Logo**

Replace file: `assets/images/logo.svg` with your institution's logo

### **Change Colors**

Edit: `assets/css/style.css`

```css
:root {
  --primary-color: #2563eb; /* Change this */
  --secondary-color: #7c3aed; /* And this */
}
```

### **Change Institution Name**

Edit: `config/config.php`

```php
define('SITE_NAME', 'Your Institution Name');
```

---

## 📱 Browser Compatibility

| Browser | Version | QR Code | Face Recognition | Status          |
| ------- | ------- | ------- | ---------------- | --------------- |
| Chrome  | 90+     | ✅      | ✅               | Fully Supported |
| Firefox | 88+     | ✅      | ✅               | Fully Supported |
| Edge    | 90+     | ✅      | ✅               | Fully Supported |
| Safari  | 14+     | ✅      | ✅               | Fully Supported |

---

## 📞 Need Help?

### **Before Asking for Help:**

1. ✅ Read the full README.md file
2. ✅ Check this Quick Start Guide
3. ✅ Verify all installation steps completed
4. ✅ Check browser console for errors (F12)
5. ✅ Check XAMPP error logs

### **Getting Error Details:**

1. **PHP Errors:** Check `C:\xampp\apache\logs\error.log`
2. **MySQL Errors:** Check MySQL Workbench error messages
3. **JavaScript Errors:** Press F12 in browser → Console tab

---

## ✅ Final Checklist

Before going live, verify:

- [ ] Database created and populated
- [ ] Admin login works
- [ ] Can create test student
- [ ] Student can view QR code
- [ ] Camera permissions working
- [ ] Face detection loading
- [ ] Theme toggle working
- [ ] All 4 portals accessible
- [ ] Uploads folder has permissions

---

## 🎉 Success!

Your EduID system is now fully operational!

### **Next Steps:**

1. **Change admin password** for security
2. **Register all students** in the system
3. **Train staff** on using QR scanner and face verification
4. **Test thoroughly** before production use
5. **Setup regular backups** of the database

### **Production Checklist:**

- [ ] Change default admin password
- [ ] Use HTTPS (SSL certificate)
- [ ] Disable error display in PHP
- [ ] Set strong MySQL password
- [ ] Configure email notifications
- [ ] Setup automatic database backups
- [ ] Test on actual hardware (scanners, cameras)

---

**System Version:** 1.0.0  
**Last Updated:** January 2026  
**Estimated Setup Time:** 15-20 minutes

**🎓 Your institution is now equipped with modern identity verification! 🎓**
