# Unidar Desktop Compilation Script (PowerShell)
# This script helps compile the project with all necessary dependencies and JavaFX modules.

$LIB_DIR = "lib"
$SRC_DIR = "src"
$OUT_DIR = "out"
$RESOURCES_DIR = "resources"

# 1. Create Output Directory
if (-not (Test-Path $OUT_DIR)) { New-Item -ItemType Directory -Path $OUT_DIR }

# 2. Build Classpath (all JARs in lib)
$JAR_FILES = Get-ChildItem "$LIB_DIR\*.jar" | ForEach-Object { $_.FullName }
$CLASSPATH = if ($JAR_FILES) { $JAR_FILES -join ";" } else { "." }

# 3. Detect JavaFX (if on JDK 11+)
$JAVAFX_SDK = $null # Replace with path to JavaFX SDK if not in JDK (e.g. "C:\javafx-sdk-21\lib")
$MODULE_ARGS = ""
if ($JAVAFX_SDK -and (Test-Path $JAVAFX_SDK)) {
    $MODULE_ARGS = "--module-path ""$JAVAFX_SDK"" --add-modules javafx.controls,javafx.fxml"
}

# 4. Get all Java sources
$SOURCES = Get-ChildItem -Path $SRC_DIR -Filter *.java -Recurse | ForEach-Object { $_.FullName }

Write-Host "🚀 Compiling Unidar Desktop..." -ForegroundColor Cyan

# 5. Compile
javac -d $OUT_DIR -cp "$CLASSPATH" $MODULE_ARGS $SOURCES

if ($LASTEXITCODE -eq 0) {
    Write-Host "✅ Compilation successful! Copying resources..." -ForegroundColor Green
    
    # 6. Copy Resources to out
    Copy-Item -Path "$RESOURCES_DIR\*" -Destination $OUT_DIR -Recurse -Force
    
    Write-Host "Done. To run the app use:" -ForegroundColor Yellow
    Write-Host "java -cp ""$OUT_DIR;$CLASSPATH"" $MODULE_ARGS tn.unidar.desktop.MainApp" -ForegroundColor White
} else {
    Write-Host "❌ Compilation failed." -ForegroundColor Red
}
