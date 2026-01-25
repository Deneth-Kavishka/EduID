# Face-API.js Models Download Script
# This script downloads the required face recognition models

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Face-API.js Models Download Script" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# Create models directory
$modelsPath = "c:\xampp\htdocs\EduID\assets\models"
if (-not (Test-Path $modelsPath)) {
    Write-Host "Creating models directory..." -ForegroundColor Yellow
    New-Item -ItemType Directory -Path $modelsPath -Force | Out-Null
    Write-Host "✓ Models directory created" -ForegroundColor Green
} else {
    Write-Host "✓ Models directory exists" -ForegroundColor Green
}

Write-Host ""
Write-Host "Downloading Face-API.js models..." -ForegroundColor Yellow
Write-Host "This may take a few minutes..." -ForegroundColor Yellow
Write-Host ""

# Base URL for models
$baseUrl = "https://raw.githubusercontent.com/justadudewhohacks/face-api.js/master/weights"

# Models to download
$models = @(
    "tiny_face_detector_model-weights_manifest.json",
    "tiny_face_detector_model-shard1",
    "face_landmark_68_model-weights_manifest.json",
    "face_landmark_68_model-shard1",
    "face_recognition_model-weights_manifest.json",
    "face_recognition_model-shard1",
    "face_recognition_model-shard2"
)

$downloadedCount = 0
$failedCount = 0

foreach ($model in $models) {
    $url = "$baseUrl/$model"
    $output = Join-Path $modelsPath $model
    
    try {
        Write-Host "Downloading: $model..." -NoNewline
        Invoke-WebRequest -Uri $url -OutFile $output -UseBasicParsing -ErrorAction Stop
        Write-Host " Done" -ForegroundColor Green
        $downloadedCount++
    }
    catch {
        Write-Host " Failed" -ForegroundColor Red
        Write-Host "  Error: $($_.Exception.Message)" -ForegroundColor Red
        $failedCount++
    }
}

Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Download Summary" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Successfully downloaded: $downloadedCount/$($models.Count) models" -ForegroundColor Green
if ($failedCount -gt 0) {
    Write-Host "Failed downloads: $failedCount" -ForegroundColor Red
}
Write-Host ""

if ($downloadedCount -eq $models.Count) {
    Write-Host "All models downloaded successfully!" -ForegroundColor Green
    Write-Host "Face recognition is now ready to use." -ForegroundColor Green
}
else {
    Write-Host "Some models failed to download." -ForegroundColor Yellow
    Write-Host "Face recognition may not work properly." -ForegroundColor Yellow
    Write-Host "Please check your internet connection and try again." -ForegroundColor Yellow
}

Write-Host ""
Write-Host "Press any key to exit..." -ForegroundColor Cyan
$null = $Host.UI.RawUI.ReadKey("NoEcho,IncludeKeyDown")
