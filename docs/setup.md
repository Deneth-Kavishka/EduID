# EduID Local Setup Guide

## 1) Database (MySQL Workbench)

1. Open MySQL Workbench.
2. Create a schema named eduid.
3. Run database/schema.sql.
4. (Optional) Run database/seed.sql to insert demo accounts.
5. Update config/database.php with your MySQL credentials.
6. If you host the project in a subfolder, update base_url in config/config.php.

## 2) Face Detection (Localhost)

EduID uses face-api.js for local face detection.

Download these models from the official face-api.js repo:

- tiny_face_detector_model-weights_manifest.json
- tiny_face_detector_model-shard1
- face_landmark_68_model-weights_manifest.json
- face_landmark_68_model-shard1

Place them in public/assets/models.

## 3) Local Server

You can use the PHP built-in server:

- Set document root to the public/ folder.
- Example: php -S localhost:8000 -t public

Then open http://localhost:8000 in your browser.

## 4) Replace Hero Video

Add a video file at public/assets/videos/campus.mp4 to show in the landing page hero.
