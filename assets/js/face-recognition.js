/**
 * Face Recognition Module
 * EduID - Educational Identity Verification System
 * Uses Face-API.js for client-side face detection and recognition
 */

let video = null;
let canvas = null;
let detectionNet = null;
let faceDescriptor = null;

// Initialize when document is ready
document.addEventListener("DOMContentLoaded", async function () {
  video = document.getElementById("video");
  canvas = document.getElementById("canvas");

  const startBtn = document.getElementById("startBtn");
  const captureBtn = document.getElementById("captureBtn");
  const saveBtn = document.getElementById("saveBtn");

  if (startBtn) {
    startBtn.addEventListener("click", startCamera);
  }

  if (captureBtn) {
    captureBtn.addEventListener("click", captureFace);
  }

  if (saveBtn) {
    saveBtn.addEventListener("click", saveFaceData);
  }

  // Load face detection models
  await loadModels();
});

/**
 * Load Face-API.js models
 */
async function loadModels() {
  try {
    showStatus("Loading face detection models...", "info");

    // Use CDN for consistent model loading with vladmandic face-api
    const MODEL_URL =
      "https://cdn.jsdelivr.net/npm/@vladmandic/face-api@1.7.12/model";

    await Promise.all([
      faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_URL),
      faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL),
      faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL),
      faceapi.nets.faceExpressionNet.loadFromUri(MODEL_URL),
    ]);

    showStatus("Face detection models loaded successfully!", "success");
    console.log("Face detection models loaded");
  } catch (error) {
    console.error("Error loading models:", error);
    showStatus(
      "Error loading face detection models. Please refresh the page.",
      "error",
    );
  }
}

/**
 * Start camera and video stream
 */
async function startCamera() {
  try {
    showStatus("Starting camera...", "info");

    const stream = await navigator.mediaDevices.getUserMedia({
      video: {
        width: { ideal: 1280 },
        height: { ideal: 720 },
        facingMode: "user",
      },
      audio: false,
    });

    video.srcObject = stream;
    video.style.display = "block";

    // Hide placeholder
    const placeholder = document.getElementById("placeholder");
    if (placeholder) {
      placeholder.style.display = "none";
    }

    // Show capture button, hide start button
    document.getElementById("startBtn").style.display = "none";
    document.getElementById("captureBtn").style.display = "block";

    showStatus("Camera started! Position your face within the circle.", "info");

    // Start continuous face detection
    startFaceDetection();
  } catch (error) {
    console.error("Error starting camera:", error);
    showStatus(
      "Unable to access camera. Please grant camera permissions.",
      "error",
    );
  }
}

/**
 * Continuous face detection
 */
async function startFaceDetection() {
  const displaySize = {
    width: video.videoWidth,
    height: video.videoHeight,
  };

  faceapi.matchDimensions(canvas, displaySize);

  const detectFace = async () => {
    if (video.style.display === "block") {
      const detections = await faceapi
        .detectAllFaces(video, new faceapi.TinyFaceDetectorOptions())
        .withFaceLandmarks();

      const resizedDetections = faceapi.resizeResults(detections, displaySize);

      // Clear previous drawings
      const context = canvas.getContext("2d");
      context.clearRect(0, 0, canvas.width, canvas.height);

      // Draw detections
      faceapi.draw.drawDetections(canvas, resizedDetections);
      faceapi.draw.drawFaceLandmarks(canvas, resizedDetections);

      setTimeout(detectFace, 100);
    }
  };

  detectFace();
}

/**
 * Capture face and extract descriptor
 */
async function captureFace() {
  try {
    showStatus("Detecting face...", "info");

    // Detect face with full descriptors
    const detection = await faceapi
      .detectSingleFace(video, new faceapi.TinyFaceDetectorOptions())
      .withFaceLandmarks()
      .withFaceDescriptor();

    if (!detection) {
      showStatus(
        "No face detected. Please position your face properly.",
        "error",
      );
      return;
    }

    // Store face descriptor
    faceDescriptor = detection.descriptor;

    // Draw captured frame on canvas
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    const context = canvas.getContext("2d");
    context.drawImage(video, 0, 0, canvas.width, canvas.height);

    // Show canvas, hide video
    canvas.style.display = "block";
    video.style.display = "none";

    // Show save button, hide capture button
    document.getElementById("captureBtn").style.display = "none";
    document.getElementById("saveBtn").style.display = "block";

    showStatus(
      'Face captured successfully! Click "Save" to register.',
      "success",
    );
  } catch (error) {
    console.error("Error capturing face:", error);
    showStatus("Error capturing face. Please try again.", "error");
  }
}

/**
 * Save face data to server
 */
async function saveFaceData() {
  if (!faceDescriptor) {
    showStatus(
      "No face data to save. Please capture your face first.",
      "error",
    );
    return;
  }

  try {
    showStatus(
      'Saving face data... <span class="loading-spinner"></span>',
      "info",
    );

    // Disable save button
    const saveBtn = document.getElementById("saveBtn");
    saveBtn.disabled = true;

    // Convert canvas to blob
    canvas.toBlob(
      async (blob) => {
        const formData = new FormData();
        formData.append("face_image", blob, "face.jpg");
        formData.append(
          "face_descriptor",
          JSON.stringify(Array.from(faceDescriptor)),
        );

        const response = await fetch("save-face.php", {
          method: "POST",
          body: formData,
        });

        const result = await response.json();

        if (result.success) {
          showStatus(
            "Face registered successfully! You can now use face recognition.",
            "success",
          );

          // Redirect after 2 seconds
          setTimeout(() => {
            window.location.href = "index.php";
          }, 2000);
        } else {
          showStatus("Error saving face data: " + result.message, "error");
          saveBtn.disabled = false;
        }
      },
      "image/jpeg",
      0.95,
    );
  } catch (error) {
    console.error("Error saving face data:", error);
    showStatus("Error saving face data. Please try again.", "error");
    document.getElementById("saveBtn").disabled = false;
  }
}

/**
 * Show status message
 */
function showStatus(message, type) {
  const statusDiv = document.getElementById("statusMessage");
  if (statusDiv) {
    statusDiv.innerHTML = `<div class="status-message ${type}">${message}</div>`;
  }
}

/**
 * Stop camera stream
 */
function stopCamera() {
  if (video && video.srcObject) {
    const tracks = video.srcObject.getTracks();
    tracks.forEach((track) => track.stop());
    video.srcObject = null;
  }
}

// Clean up on page unload
window.addEventListener("beforeunload", stopCamera);
