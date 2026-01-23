# EduID

EduID is a secure student identity verification system with QR scanning, face detection, and role-based portals for Admin, Teacher, Student, and Parent.

## Features

- Admin control center with user provisioning
- Student, Teacher, and Parent dashboards
- QR verification and live camera face detection (local)
- Light/Dark mode toggle
- Modern landing page with custom logo

## Quick Start (Local)

1. Create a MySQL database using the schema in database/schema.sql.
2. (Optional) Load sample data from database/seed.sql.
3. Update database credentials in config/database.php.
4. If you host the project in a subfolder, update base_url in config/config.php.
5. Download face-api.js models into public/assets/models (see docs/setup.md).
6. Start a PHP server and open public/index.php in the browser.

### Demo Accounts (seed.sql)

- admin@eduid.local / Admin@123
- teacher@eduid.local / Admin@123
- student@eduid.local / Admin@123
- parent@eduid.local / Admin@123

## Recommended Folder Structure

- app/ → PHP bootstrap, services, and views
- config/ → App and database configuration
- database/ → Schema and seed SQL
- public/ → Web root with pages and assets
- docs/ → Local setup and assets guidance
