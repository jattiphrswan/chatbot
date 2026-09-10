param (
    [string]$PluginSlug = "gemini-chat-assistant",
    [string]$Version = "1.0.1"
)

$ErrorActionPreference = "Stop"

$RootPath = (Get-Item .).FullName
$DistDir = Join-Path $RootPath "dist"
$StageDir = Join-Path $DistDir $PluginSlug
$ZipFile = Join-Path $DistDir "$PluginSlug-$Version.zip"

Write-Host ">>> Building release package for $PluginSlug v$Version..."

if (Test-Path $DistDir) {
    Remove-Item -Path $DistDir -Recurse -Force
}
New-Item -ItemType Directory -Force -Path $StageDir | Out-Null

$FilesToCopy = @(
    "gemini-chat-assistant.php",
    "uninstall.php",
    "readme.txt",
    "README.md",
    "LICENSE"
)

foreach ($file in $FilesToCopy) {
    if (Test-Path (Join-Path $RootPath $file)) {
        Copy-Item -Path (Join-Path $RootPath $file) -Destination $StageDir
    }
}

$DirsToCopy = @(
    "includes",
    "admin",
    "public",
    "templates"
)

foreach ($dir in $DirsToCopy) {
    if (Test-Path (Join-Path $RootPath $dir)) {
        Copy-Item -Path (Join-Path $RootPath $dir) -Destination (Join-Path $StageDir $dir) -Recurse -Force
    }
}

if (Test-Path (Join-Path $RootPath "assets")) {
    Copy-Item -Path (Join-Path $RootPath "assets") -Destination (Join-Path $StageDir "assets") -Recurse -Force
}
if (Test-Path (Join-Path $RootPath "languages")) {
    Copy-Item -Path (Join-Path $RootPath "languages") -Destination (Join-Path $StageDir "languages") -Recurse -Force
}

# Remove any accidental files
Get-ChildItem -Path $StageDir -Include "*.DS_Store", "Thumbs.db" -Recurse -Force | Remove-Item -Force

Write-Host ">>> Creating installable WordPress ZIP archive with POSIX forward slashes..."
Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem

if (Test-Path $ZipFile) {
    Remove-Item $ZipFile -Force
}

$zip = [System.IO.Compression.ZipFile]::Open($ZipFile, [System.IO.Compression.ZipArchiveMode]::Create)
Get-ChildItem -Path $StageDir -Recurse -File | ForEach-Object {
    $entryName = $_.FullName.Substring($DistDir.Length + 1).Replace('\', '/')
    [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($zip, $_.FullName, $entryName, [System.IO.Compression.CompressionLevel]::Optimal) | Out-Null
}
$zip.Dispose()

$ZipItem = Get-Item $ZipFile
$SizeKB = [math]::Round($ZipItem.Length / 1KB, 2)
$Hash = (Get-FileHash -Path $ZipFile -Algorithm SHA256).Hash

Write-Host ">>> Release artifact created successfully:"
Write-Host "    Artifact: $($ZipItem.Name)"
Write-Host "    Path:     $ZipFile"
Write-Host "    Size:     $SizeKB KB ($($ZipItem.Length) bytes)"
Write-Host "    SHA256:   $Hash"
Write-Host ">>> Done."
