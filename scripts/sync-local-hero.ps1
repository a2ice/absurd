# Safely copy only HERO-002 files from this Git checkout to the local WordPress theme.
# Usage: .\scripts\sync-local-hero.ps1
#        .\scripts\sync-local-hero.ps1 -Apply
#        .\scripts\sync-local-hero.ps1 -WordPressPublicPath 'C:\path\to\public' -Apply
[CmdletBinding()]
param(
    [string] $WordPressPublicPath = 'C:\OSP6\home\absurdtheater-wp.local\public',
    [switch] $Apply
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$repoRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$sourceTheme = Join-Path $repoRoot 'wp-content\themes\absurd-stage-v1'
$destTheme = Join-Path $WordPressPublicPath 'wp-content\themes\absurd-stage-v1'
$relativeFiles = @(
    'front-page.php',
    'style.css',
    'assets\js\site.js',
    'assets\images\hero-start16x9.png',
    'assets\images\hero16x9.png',
    'assets\images\PixVerse_V6_Image_Text_540P_Heavy_burgundy_vel.mp4',
    'assets\images\PixVerse_V6_Transition_540P_The_camera_slowly_.mp4'
)

if (-not (Test-Path (Join-Path $WordPressPublicPath 'wp-load.php') -PathType Leaf)) {
    throw "WordPress not detected at $WordPressPublicPath. Do not guess the live document root: pass -WordPressPublicPath explicitly."
}
if (-not (Test-Path $destTheme -PathType Container)) {
    throw "Existing local theme is missing: $destTheme"
}
if (-not (Test-Path $sourceTheme -PathType Container)) {
    throw "Git source theme not found: $sourceTheme"
}
if ([IO.Path]::GetFullPath($sourceTheme).TrimEnd('\') -ieq [IO.Path]::GetFullPath($destTheme).TrimEnd('\')) {
    throw 'Source and destination are already the same directory. No copy required; verify OSP6 document root and browser caching.'
}

$sourcePhp = Get-Content (Join-Path $sourceTheme 'front-page.php') -Raw
$sourceJs = Get-Content (Join-Path $sourceTheme 'assets\js\site.js') -Raw
if ($sourcePhp -notmatch 'hero-frame--video' -or $sourceJs -notmatch 'hero-film') {
    throw 'Checked-out Git branch does not contain the HERO-002 video implementation. Run git branch --show-current and git status before retrying.'
}

Write-Host "Git source: $sourceTheme"
Write-Host "WordPress target: $destTheme"
Write-Host "Mode: $(if ($Apply) { 'APPLY (with backup)' } else { 'DRY RUN (no changes)' })"

$work = @()
foreach ($rel in $relativeFiles) {
    $src = Join-Path $sourceTheme $rel
    $dst = Join-Path $destTheme $rel
    if (-not (Test-Path $src -PathType Leaf)) { throw "Missing source file: $rel" }
    $same = (Test-Path $dst -PathType Leaf) -and
        ((Get-FileHash $src -Algorithm SHA256).Hash -eq (Get-FileHash $dst -Algorithm SHA256).Hash)
    $work += [pscustomobject]@{Relative=$rel;Source=$src;Destination=$dst;Changed=(-not $same);Existed=(Test-Path $dst -PathType Leaf)}
    Write-Host "$(if ($same) { 'SAME ' } else { 'UPDATE ' }) $rel"
}

if (-not $Apply) {
    Write-Host 'No files changed. After reviewing the target and filenames, use -Apply.'
    exit 0
}

$backupRoot = Join-Path 'C:\OSP6\user\a2ice-backups' ('absurd-hero-video-' + (Get-Date -Format 'yyyyMMdd-HHmmss'))
New-Item -ItemType Directory -Path $backupRoot -Force | Out-Null
$copied = @()

try {
    foreach ($item in $work) {
        if (-not $item.Changed) { continue }
        if ($item.Existed) {
            $backupPath = Join-Path $backupRoot $item.Relative
            New-Item -ItemType Directory -Path (Split-Path $backupPath -Parent) -Force | Out-Null
            Copy-Item -LiteralPath $item.Destination -Destination $backupPath -Force
        }
        New-Item -ItemType Directory -Path (Split-Path $item.Destination -Parent) -Force | Out-Null
        Copy-Item -LiteralPath $item.Source -Destination $item.Destination -Force
        $copied += $item
        if ((Get-FileHash $item.Source -Algorithm SHA256).Hash -ne (Get-FileHash $item.Destination -Algorithm SHA256).Hash) {
            throw "SHA256 mismatch after copying $($item.Relative)"
        }
    }
} catch {
    Write-Warning 'Copy failed: restoring all copied files where possible.'
    foreach ($item in $copied) {
        $backupPath = Join-Path $backupRoot $item.Relative
        if ($item.Existed -and (Test-Path $backupPath)) {
            Copy-Item -LiteralPath $backupPath -Destination $item.Destination -Force
        } elseif (-not $item.Existed) {
            Remove-Item -LiteralPath $item.Destination -Force -ErrorAction SilentlyContinue
        }
    }
    throw
}

Write-Host "LOCAL SYNC COMPLETED. Backup: $backupRoot"
try {
    $response = Invoke-WebRequest 'http://127.0.0.1:8767/' -UseBasicParsing -TimeoutSec 10 -Headers @{ 'Cache-Control'='no-cache' }
    $marker = $response.Content -match 'hero-frame--video'
    Write-Host "HTTP $($response.StatusCode), video hero marker: $marker"
    if (-not $marker) { Write-Warning 'The site still serves an old template: check which OSP6 document root and theme directory actually handle port 8767. Do not change WP DB URLs.' }
} catch {
    Write-Warning "Could not confirm local HTTP response: $($_.Exception.Message)"
}
Write-Host 'Next: hard-refresh Chrome (Ctrl+Shift+R), inspect the hero and media. No E2E PASS is implied.'
