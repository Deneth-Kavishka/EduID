async function initFaceVerification() {
  const video = document.getElementById("faceVideo");
  const statusEl = document.getElementById("faceStatus");

  if (!video || !statusEl) {
    return;
  }

  statusEl.textContent = "Loading face models...";

  const base = window.APP_BASE_URL || "";
  await faceapi.nets.tinyFaceDetector.loadFromUri(`${base}/assets/models`);
  await faceapi.nets.faceLandmark68Net.loadFromUri(`${base}/assets/models`);

  const stream = await navigator.mediaDevices.getUserMedia({ video: true });
  video.srcObject = stream;

  video.addEventListener("play", () => {
    statusEl.textContent = "Camera ready. Align your face.";
    const canvas = faceapi.createCanvasFromMedia(video);
    document.getElementById("faceCanvas").innerHTML = "";
    document.getElementById("faceCanvas").appendChild(canvas);
    const displaySize = { width: video.width, height: video.height };
    faceapi.matchDimensions(canvas, displaySize);

    setInterval(async () => {
      const detection = await faceapi.detectSingleFace(
        video,
        new faceapi.TinyFaceDetectorOptions(),
      );
      const resized = faceapi.resizeResults(detection, displaySize);
      canvas.getContext("2d").clearRect(0, 0, canvas.width, canvas.height);
      if (resized) {
        faceapi.draw.drawDetections(canvas, resized);
        statusEl.textContent = "Face detected. Verification passed.";
      } else {
        statusEl.textContent = "No face detected. Adjust lighting.";
      }
    }, 1000);
  });
}

function initQrScanner() {
  const qrRegion = document.getElementById("qr-reader");
  if (!qrRegion || !window.Html5Qrcode) {
    return;
  }

  const qr = new Html5Qrcode("qr-reader");
  qr.start(
    { facingMode: "environment" },
    { fps: 10, qrbox: 250 },
    (decodedText) => {
      document.getElementById("qrResult").textContent = decodedText;
    },
  ).catch(() => {
    document.getElementById("qrResult").textContent =
      "Unable to access camera.";
  });
}
