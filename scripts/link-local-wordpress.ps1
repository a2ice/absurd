<#
.SYNOPSIS
  Make the local WordPress runtime use exactly the files edited in this Git workspace.

.DESCRIPTION
  Creates Windows NTFS directory junctions for our two CUSTOM code directories.
  Never touches the WordPress database, wp-config.php, wp-admin, uploads or core.
  Default is a read-only preview. -Apply makes changes after moving the existing
  runtime code into a timestamped backup. Can be run on Dmitanium only.

.EXAMPLE
  .\scripts\link-local-wordpress.ps1
  .\scripts\link-local-wordpress.ps1 -Apply
  .\scripts\link-local-wordpress.ps1 -WordPressPublicPath 'C:\another\public' -Apply
#>
[CmdletBinding()]
param(
    [string] $WordPressPublicPath = 'C:\OSP6\home\absurdtheater-wp.local\public',
    [switch] $Apply
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$workspace = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$themeRel = 'wp-content\themes\absurd-stage-v1'
$pluginRel = 'wp-content\plugins\absurdtheater-core'
$entries = @(
    [pscustomobject]@{ Label = 'theme'; Relative = $themeRel },
    [pscustomobject]@{ Label = 'plugin'; Relative = $pluginRel }
)

function Canonical([string] $value) {
    return [IO.Path]::GetFullPath($value).TrimEnd('\')
}

if (-not (Test-Path (Join-Path $WordPressPublicPath 'wp-load.php') -PathType Leaf)) {
    throw "No WordPress wp-load.php at '$WordPressPublicPath'; do not guess the document root."
}

Write-Host "Git workspace: $workspace"
Write-Host "WordPress runtime: $WordPressPublicPath"
Write-Host "Mode: $(if ($Apply) { 'APPLY with rollback backup' } else { 'READ-ONLY DRY RUN' })"

foreach ($item in $entries) {
    $source = Join-Path $workspace $item.Relative
    $target = Join-Path $WordPressPublicPath $item.Relative
    if (-not (Test-Path $source -PathType Container)) {
        throw "Missing Git source: $source"
    }
    if (-not (Test-Path $target -PathType Container)) {
        throw "Missing local installed code: $target"
    }

    $item | Add-Member -NotePropertyName Source -NotePropertyValue $source -Force
    $item | Add-Member -NotePropertyName Target -NotePropertyValue $target -Force

    if ((Canonical $source) -ieq (Canonical $target)) {
        $item | Add-Member -NotePropertyName Status -NotePropertyValue 'ALREADY_SHARED' -Force
    } else {
        $targetItem = Get-Item -LiteralPath $target -Force
        if ($targetItem.LinkType) {
            $dest = @($targetItem.Target | ForEach-Object { [string] $_ })
            if ($dest.Count -eq 1 -and (Canonical $dest[0]) -ieq (Canonical $source)) {
                $item | Add-Member -NotePropertyName Status -NotePropertyValue 'JUNCTION_OK' -Force
            } else {
                throw "Existing reparse point at $target points elsewhere. Stop for manual investigation."
            }
        } else {
            $item | Add-Member -NotePropertyName Status -NotePropertyValue 'NEEDS_JUNCTION' -Force
        }
    }
    Write-Host "$($item.Label): $($item.Status)"
    Write-Host "    Source: $source"
    Write-Host "    Runtime: $target"
}

if (-not $Apply) {
    Write-Host 'No files changed. Review both paths and run -Apply only if they are correct.'
    return
}

$toChange = @($entries | Where-Object { $_.Status -eq 'NEEDS_JUNCTION' })
if ($toChange.Count -eq 0) {
    Write-Host 'Already linked. Saving a file in VS Code now changes the WordPress theme/plugin immediately.'
    return
}

$backupRoot = Join-Path 'C:\OSP6\user\a2ice-backups' ('absurd-wp-code-before-junction-' + (Get-Date -Format 'yyyyMMdd-HHmmss'))
New-Item -ItemType Directory -Force -Path $backupRoot | Out-Null
$moved = @()
$linked = @()

try {
    foreach ($item in $toChange) {
        $backup = Join-Path $backupRoot $item.Label
        Move-Item -LiteralPath $item.Target -Destination $backup
        $moved += [pscustomobject]@{ Item = $item; Backup = $backup }

        New-Item -ItemType Junction -Path $item.Target -Target $item.Source | Out-Null
        $linked += $item

        $installed = (Get-Item -LiteralPath $item.Target -Force)
        if ($installed.LinkType -ne 'Junction') {
            throw "Target not a junction after creation: $($item.Target)"
        }
        $marker = if ($item.Label -eq 'theme') { 'front-page.php' } else { 'absurdtheater-core.php' }
        $a = (Get-FileHash (Join-Path $item.Target $marker) -Algorithm SHA256).Hash
        $b = (Get-FileHash (Join-Path $item.Source $marker) -Algorithm SHA256).Hash
        if ($a -ne $b) { throw "SHA256 mismatch through junction: $marker" }
        Write-Host "Linked $($item.Label) and verified $marker"
    }
} catch {
    Write-Warning "Linking failed: $($_.Exception.Message). Attempting to restore original local code."
    for ($i = $linked.Count - 1; $i -ge 0; $i--) {
        $item = $linked[$i]
        # cmd rmdir deletes the junction itself, not the code it points to.
        & cmd.exe /c "rmdir `"$($item.Target)`""
    }
    for ($i = $moved.Count - 1; $i -ge 0; $i--) {
        $move = $moved[$i]
        if (-not (Test-Path -LiteralPath $move.Item.Target) -and (Test-Path -LiteralPath $move.Backup)) {
            Move-Item -LiteralPath $move.Backup -Destination $move.Item.Target
        }
    }
    throw
}

Write-Host "Local code junctions activated. Rollback copies: $backupRoot"
Write-Host 'Saving a file in the Git workspace now updates WordPress directly (browser reload may be needed).'
Write-Host 'Do not run the old sync-local-hero.ps1 anymore.'
Write-Host 'If needed, rollback: remove ONLY the junction itself (cmd /c rmdir <path>), then move the backup back.'
