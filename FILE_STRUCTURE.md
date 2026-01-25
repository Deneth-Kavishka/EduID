# 📁 EduID Project File Structure

## Complete Directory Layout

```
Dev/
│
├── 📄 index.html                          # Landing page
├── 📄 README.md                           # Complete setup documentation
├── 📄 QUICKSTART.md                       # Quick start guide (15 min setup)
│
├── 📁 assets/                             # Static assets
│   ├── 📁 css/
│   │   └── 📄 style.css                   # Main stylesheet (light/dark mode)
│   ├── 📁 js/
│   │   ├── 📄 theme.js                    # Theme toggle functionality
│   │   └── 📄 face-recognition.js         # Face detection & recognition
│   ├── 📁 images/
│   │   ├── 📄 logo.svg                    # System logo
│   │   └── 📄 default-avatar.png          # Default user avatar
│   └── 📁 models/                         # Face-API.js models (download separately)
│       ├── 📄 tiny_face_detector_model-*
│       ├── 📄 face_landmark_68_model-*
│       ├── 📄 face_recognition_model-*
│       └── 📄 face_expression_model-*
│
├── 📁 auth/                               # Authentication
│   ├── 📄 login.php                       # Login page with role-based redirect
│   └── 📄 logout.php                      # Logout and session cleanup
│
├── 📁 config/                             # Configuration files
│   ├── 📄 config.php                      # Main configuration & helper functions
│   └── 📄 database.php                    # Database connection class
│
├── 📁 database/                           # Database schema
│   └── 📄 schema.sql                      # Complete MySQL database structure
│
├── 📁 dashboards/                         # User portals
│   │
│   ├── 📁 admin/                          # Admin Portal
│   │   ├── 📄 index.php                   # Admin dashboard
│   │   ├── 📄 users.php                   # User management (CRUD)
│   │   ├── 📄 students.php                # Student management
│   │   ├── 📄 teachers.php                # Teacher management
│   │   ├── 📄 parents.php                 # Parent management
│   │   ├── 📄 attendance.php              # Attendance overview
│   │   ├── 📄 exams.php                   # Exam management
│   │   ├── 📄 events.php                  # Event management
│   │   ├── 📄 reports.php                 # Analytics & reports
│   │   ├── 📄 logs.php                    # Access logs
│   │   ├── 📄 settings.php                # System settings
│   │   └── 📄 profile.php                 # Admin profile
│   │
│   ├── 📁 student/                        # Student Portal
│   │   ├── 📄 index.php                   # Student dashboard
│   │   ├── 📄 profile.php                 # Student profile & edit
│   │   ├── 📄 qr-code.php                 # View & download QR code
│   │   ├── 📄 face-registration.php       # Face registration page
│   │   ├── 📄 save-face.php               # Face data save endpoint
│   │   ├── 📄 attendance.php              # Attendance history
│   │   ├── 📄 exams.php                   # Upcoming exams
│   │   ├── 📄 events.php                  # Event registration
│   │   └── 📄 settings.php                # Student settings
│   │
│   ├── 📁 teacher/                        # Teacher Portal
│   │   ├── 📄 index.php                   # Teacher dashboard
│   │   ├── 📄 profile.php                 # Teacher profile
│   │   ├── 📄 qr-scanner.php              # QR code scanner
│   │   ├── 📄 face-verification.php       # Face verification
│   │   ├── 📄 mark-attendance.php         # Manual attendance
│   │   ├── 📄 students.php                # Student list
│   │   ├── 📄 exams.php                   # Exam verification
│   │   ├── 📄 events.php                  # Event management
│   │   └── 📄 reports.php                 # Class reports
│   │
│   └── 📁 parent/                         # Parent Portal
│       ├── 📄 index.php                   # Parent dashboard
│       ├── 📄 profile.php                 # Parent profile
│       ├── 📄 children.php                # Children overview
│       ├── 📄 attendance.php              # Children's attendance
│       ├── 📄 exams.php                   # Children's exams
│       ├── 📄 events.php                  # Children's events
│       └── 📄 notifications.php           # Notifications
│
├── 📁 uploads/                            # File uploads (auto-created)
│   ├── 📁 profiles/                       # Profile pictures
│   ├── 📁 face_data/                      # Face images
│   └── 📁 qr_codes/                       # Generated QR codes
│
└── 📁 vendor/                             # Third-party libraries
    └── 📁 phpqrcode/                      # QR code generation library
        └── 📄 qrlib.php                   # Main QR library file

```

---

## 📊 File Count Summary

| Category             | Count | Purpose                        |
| -------------------- | ----- | ------------------------------ |
| **PHP Files**        | 40+   | Backend logic, pages, APIs     |
| **CSS Files**        | 1     | Styling with light/dark mode   |
| **JavaScript Files** | 2     | Theme toggle, face detection   |
| **SQL Files**        | 1     | Database schema                |
| **Image Files**      | 2+    | Logo, avatars, icons           |
| **Documentation**    | 3     | README, Quick Start, Structure |
| **Config Files**     | 2     | Database, system config        |

**Total Project Files:** 50+ files

---

## 🔑 Key File Descriptions

### **Configuration Files**

- `config/database.php` - PDO database connection handler
- `config/config.php` - Site settings, constants, helper functions

### **Authentication**

- `auth/login.php` - Multi-role login with session management
- `auth/logout.php` - Secure logout with activity logging

### **Core Features**

- `assets/js/face-recognition.js` - Face detection using Face-API.js
- `dashboards/student/qr-code.php` - QR code generation
- `dashboards/student/save-face.php` - Face data endpoint

### **Database**

- `database/schema.sql` - Complete schema with:
  - 14 tables
  - 2 views for reporting
  - Default admin user
  - Sample system settings

---

## 📂 Folder Permissions

### **Windows XAMPP**

```
uploads/          - Full control (read/write)
vendor/           - Read only
assets/           - Read only
config/           - Read only (protect sensitive data)
```

### **Linux/Mac**

```bash
chmod 755 -R eduid/
chmod 777 -R eduid/uploads/
```

---

## 🎯 Entry Points

| URL                    | File                           | Purpose           |
| ---------------------- | ------------------------------ | ----------------- |
| `/`                    | `index.html`                   | Landing page      |
| `/auth/login.php`      | `auth/login.php`               | Login page        |
| `/dashboards/admin/`   | `dashboards/admin/index.php`   | Admin dashboard   |
| `/dashboards/student/` | `dashboards/student/index.php` | Student dashboard |
| `/dashboards/teacher/` | `dashboards/teacher/index.php` | Teacher dashboard |
| `/dashboards/parent/`  | `dashboards/parent/index.php`  | Parent dashboard  |

---

## 🔐 Security Files

### **Protected Files**

- `config/database.php` - Database credentials
- `config/config.php` - System configuration
- `uploads/*` - User uploaded content

### **Public Files**

- `index.html` - Landing page
- `assets/css/*` - Stylesheets
- `assets/js/*` - Client-side scripts
- `assets/images/logo.svg` - Logo

---

## 🗄️ Database Tables

### **Core Tables (9)**

1. `users` - Main user authentication
2. `students` - Student details
3. `teachers` - Teacher details
4. `parents` - Parent details
5. `attendance` - Attendance records
6. `exam_entries` - Exam verification
7. `events` - Event management
8. `event_registrations` - Event attendance
9. `access_logs` - Activity logging

### **Support Tables (5)**

10. `face_recognition_data` - Face descriptors
11. `notifications` - User notifications
12. `system_settings` - System configuration

### **Views (2)**

- `v_student_attendance_summary` - Attendance statistics
- `v_daily_attendance_report` - Daily reports

---

## 📦 External Dependencies

### **Required Downloads**

1. **PHPQRCode**
   - Location: `vendor/phpqrcode/`
   - Source: https://github.com/t0k4rt/phpqrcode

2. **Face-API.js Models**
   - Location: `assets/models/`
   - Source: https://github.com/justadudewhohacks/face-api.js-models
   - Size: ~10MB total

3. **Font Awesome** (CDN)
   - Icons for UI
   - Loaded via CDN link

4. **Google Fonts** (CDN)
   - Inter font family
   - Loaded via CDN link

---

## 🎨 CSS Architecture

### **Main Stylesheet** (`assets/css/style.css`)

- CSS Variables for theming
- Light/Dark mode support
- Responsive grid layouts
- Component styles
- Utility classes
- Print styles

**Lines of Code:** ~1000+ lines

---

## 🚀 JavaScript Modules

### **theme.js**

- Theme switching logic
- LocalStorage persistence
- Icon updates

### **face-recognition.js**

- Camera initialization
- Face detection
- Descriptor extraction
- Server communication

---

## 📱 Responsive Breakpoints

```css
Desktop:  > 1024px  (Full sidebar, grid layouts)
Tablet:   768-1024px (Sidebar toggle, 2-column grid)
Mobile:   < 768px   (Hidden sidebar, single column)
```

---

## 🔄 Data Flow

```
User Login → Authentication → Role Check → Dashboard Redirect
                ↓
        Session Management
                ↓
        Activity Logging
                ↓
        Dashboard Display
                ↓
        Feature Access
```

---

## 📊 Database Size Estimates

### **Initial Installation**

- Schema: ~100KB
- Default admin: ~1KB
- Total: ~101KB

### **After 1000 Students**

- Users: ~200KB
- Students: ~500KB
- Face data: ~50MB
- QR codes: ~10MB
- Attendance (1 year): ~2MB
- **Total:** ~65MB

---

## 🛠️ Development Files

**Not Included** (Optional for developers):

- `.gitignore` - Git ignore rules
- `composer.json` - PHP dependencies
- `package.json` - NPM dependencies
- `.htaccess` - Apache configuration
- `.env` - Environment variables

---

## ✅ Deployment Checklist

- [ ] All files copied to server
- [ ] Database created and imported
- [ ] Config files updated
- [ ] Upload folders created
- [ ] Permissions set correctly
- [ ] PHPQRCode installed
- [ ] Face models downloaded
- [ ] Test admin login
- [ ] Test student features
- [ ] Test camera access
- [ ] Test QR generation

---

**Project Structure Version:** 1.0.0  
**Last Updated:** January 2026  
**Total Lines of Code:** ~10,000+ lines

This structure follows modern web development best practices with clear separation of concerns, modular architecture, and scalability in mind.
