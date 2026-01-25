# Face-API.js Models Setup

## Required Models for Face Recognition

The face recognition feature requires Face-API.js model files. Follow these steps:

### Option 1: Download Models (Recommended)

1. Download the models from: https://github.com/justadudewhohacks/face-api.js/tree/master/weights
2. Create folder: `c:\xampp\htdocs\EduID\assets\models`
3. Download and place these files in the models folder:
   - `tiny_face_detector_model-weights_manifest.json`
   - `tiny_face_detector_model-shard1`
   - `face_landmark_68_model-weights_manifest.json`
   - `face_landmark_68_model-shard1`
   - `face_recognition_model-weights_manifest.json`
   - `face_recognition_model-shard1`
   - `face_recognition_model-shard2`

### Option 2: Use CDN (Fallback)

If models are not available locally, the system will attempt to load from CDN automatically.

### Verify Setup

1. Go to User Management
2. Click "Add User" → "Student"
3. Click "Start Camera" in the Face Registration section
4. Check browser console for any model loading errors

### Troubleshooting

**Models not loading:**

- Check if files exist in `assets/models/` folder
- Verify file names match exactly
- Check browser console for errors
- Ensure camera permissions are granted

**Camera not working:**

- Grant camera permissions in browser
- Check if camera is being used by another application
- Try a different browser (Chrome/Firefox recommended)

**Face not detected:**

- Ensure good lighting
- Position face within the circle guide
- Face camera directly
- Remove glasses if detection fails
