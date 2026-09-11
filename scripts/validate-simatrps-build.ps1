param(
    [string]$BuildDirectory = "public/build",
    [switch]$NormalizeWorkingTreeOnly
)

$ErrorActionPreference = "Stop"

function Stop-Validation([string]$Message) {
    Write-Host "[ERROR] $Message" -ForegroundColor Red
    exit 1
}

function Restore-GeneratedWayfinderOutput {
    $unstagedChanges = @(& git diff --name-only --)
    if ($LASTEXITCODE -ne 0) {
        Stop-Validation "Tidak dapat mengaudit perubahan tracked pada working tree."
    }

    $untrackedChanges = @(& git ls-files --others --exclude-standard --)
    if ($LASTEXITCODE -ne 0) {
        Stop-Validation "Tidak dapat mengaudit file untracked pada working tree."
    }

    $allChanges = @($unstagedChanges) + @($untrackedChanges)
    $unexpectedChanges = @(
        $allChanges | Sort-Object -Unique | Where-Object {
            $_ -notmatch "^(resources/js/actions/|resources/js/routes/)"
        }
    )

    if ($unexpectedChanges.Count -gt 0) {
        Stop-Validation "Working tree memuat perubahan di luar output Wayfinder: $($unexpectedChanges -join ', ')"
    }

    if ($unstagedChanges.Count -gt 0) {
        Write-Host "[CLEAN] Memulihkan file tracked Wayfinder hasil generate." -ForegroundColor Yellow
        & git restore --worktree -- "resources/js/actions" "resources/js/routes"
        if ($LASTEXITCODE -ne 0) {
            Stop-Validation "Tidak dapat memulihkan file tracked Wayfinder."
        }
    }

    if ($untrackedChanges.Count -gt 0) {
        Write-Host "[CLEAN] Menghapus file untracked Wayfinder hasil generate." -ForegroundColor Yellow
        foreach ($generatedFile in $untrackedChanges) {
            if (Test-Path -LiteralPath $generatedFile -PathType Leaf) {
                Remove-Item -LiteralPath $generatedFile -Force
            }
        }
    }

    $remainingTracked = @(& git diff --name-only --)
    $remainingUntracked = @(& git ls-files --others --exclude-standard --)
    if ($LASTEXITCODE -ne 0 -or $remainingTracked.Count -gt 0 -or $remainingUntracked.Count -gt 0) {
        Stop-Validation "Working tree belum bersih setelah pemulihan output generated."
    }
}

if ($NormalizeWorkingTreeOnly) {
    Restore-GeneratedWayfinderOutput
    Write-Host "[PASS] Working tree aman untuk memulai gate." -ForegroundColor Green
    exit 0
}

$manifestPath = Join-Path $BuildDirectory "manifest.json"
if (-not (Test-Path -LiteralPath $manifestPath -PathType Leaf)) {
    Stop-Validation "Manifest Vite tidak ditemukan: $manifestPath"
}

try {
    $manifest = Get-Content -LiteralPath $manifestPath -Raw | ConvertFrom-Json
} catch {
    Stop-Validation "Manifest Vite bukan JSON valid: $($_.Exception.Message)"
}

$requiredEntries = @(
    "resources/css/app.css",
    "resources/js/app.tsx"
)

$entryNames = @($manifest.PSObject.Properties.Name)
foreach ($entry in $requiredEntries) {
    if ($entryNames -notcontains $entry) {
        Stop-Validation "Entry wajib tidak ditemukan di manifest: $entry"
    }
}

$assets = New-Object System.Collections.Generic.List[string]
foreach ($property in $manifest.PSObject.Properties) {
    $entry = $property.Value

    if ($null -ne $entry.file -and -not [string]::IsNullOrWhiteSpace([string]$entry.file)) {
        $assets.Add([string]$entry.file)
    }

    if ($null -ne $entry.css) {
        foreach ($cssFile in @($entry.css)) {
            if (-not [string]::IsNullOrWhiteSpace([string]$cssFile)) {
                $assets.Add([string]$cssFile)
            }
        }
    }

    if ($null -ne $entry.assets) {
        foreach ($assetFile in @($entry.assets)) {
            if (-not [string]::IsNullOrWhiteSpace([string]$assetFile)) {
                $assets.Add([string]$assetFile)
            }
        }
    }
}

$assets = @($assets | Sort-Object -Unique)
if ($assets.Count -eq 0) {
    Stop-Validation "Manifest tidak memuat aset hasil build."
}

$hasJavaScript = $false
$hasCss = $false

foreach ($asset in $assets) {
    $normalized = $asset.Replace("\", "/")

    if ($normalized.StartsWith("/") -or $normalized.Contains("..")) {
        Stop-Validation "Path aset tidak aman di manifest: $asset"
    }

    if (-not $normalized.StartsWith("assets/")) {
        Stop-Validation "Aset harus berada di folder assets/: $asset"
    }

    $fileName = [System.IO.Path]::GetFileName($normalized)
    if ($fileName -notmatch "-[A-Za-z0-9_-]{6,}\.(js|mjs|css|woff2?|ttf|svg|png|jpe?g|webp)$") {
        Stop-Validation "Nama aset tidak memakai hash Vite: $asset"
    }

    $assetPath = Join-Path $BuildDirectory ($normalized.Replace("/", [System.IO.Path]::DirectorySeparatorChar))
    if (-not (Test-Path -LiteralPath $assetPath -PathType Leaf)) {
        Stop-Validation "Aset yang tercantum di manifest tidak ditemukan: $assetPath"
    }

    if ($normalized -match "\.(js|mjs)$") { $hasJavaScript = $true }
    if ($normalized -match "\.css$") { $hasCss = $true }
}

if (-not $hasJavaScript) {
    Stop-Validation "Build tidak menghasilkan aset JavaScript."
}

if (-not $hasCss) {
    Stop-Validation "Build tidak menghasilkan aset CSS."
}

Restore-GeneratedWayfinderOutput

$manifestHash = (Get-FileHash -LiteralPath $manifestPath -Algorithm SHA256).Hash
Write-Host "[PASS] Manifest dan $($assets.Count) aset hashed valid." -ForegroundColor Green
Write-Host "[PASS] SHA256 manifest: $manifestHash" -ForegroundColor Green
exit 0
