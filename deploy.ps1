#!/usr/bin/env pwsh
<#
.SYNOPSIS
    Deploy this working tree to the live HA server.

.DESCRIPTION
    One-way copy. By default it is additive: there is no /MIR, so anything that
    exists only on the server (log/, lastimage/, media/, images/) is never
    deleted.

    -Prune adds a second pass: after copying, it lists files on the server that
    the repo no longer contains, and removes them once you confirm. log/,
    lastimage/, media/ and bin/ are never touched, and the directories a prune
    empties are removed too.

    Excluded on purpose:
      logins.php               holds credentials; deploying it would put them
                               back under the web root
      info.php, php_errors.log, test.php
                               local debris, would be world readable
      .git, .vscode            never belongs on a web server
      log, lastimage           runtime data
      bin                      SMB copies do not preserve the Unix exec bit,
                               and these .sh files are executed by PHP.
                               Deploy those by hand, then chmod +x on the server.

    config.php IS deployed. It is the tracked environment file (paths, subnets,
    feature flags) and holds no secrets. The repo copy is authoritative: a
    hand edit on the server is overwritten by the next deploy.

    Every PHP file is syntax checked first and the deploy aborts on any error.

.EXAMPLE
    ./deploy.ps1 -DryRun
        Show what would be copied, change nothing.

.EXAMPLE
    ./deploy.ps1
        Deploy.

.EXAMPLE
    ./deploy.ps1 -Prune -DryRun
        Deploy nothing, but list the files that exist only on the server.

.EXAMPLE
    ./deploy.ps1 -Prune
        Deploy, then list the orphans and ask before removing them.
#>
[CmdletBinding()]
param(
    [string] $Live = '\\home\www\ha',
    [switch] $DryRun,
    [switch] $Prune,
    [switch] $Force
)

$ErrorActionPreference = 'Stop'
$src = $PSScriptRoot

if (-not (Test-Path -LiteralPath $Live)) {
    throw "Live path not reachable: $Live"
}

# Normalise both roots: UNC paths, 8.3 short names and trailing separators all
# break the relative-path arithmetic the prune pass depends on.
$Live = (Get-Item -LiteralPath $Live).FullName.TrimEnd('\', '/')
$src  = (Get-Item -LiteralPath $src).FullName.TrimEnd('\', '/')

# ---------------------------------------------------------------- syntax check
$php = Get-Command php -ErrorAction SilentlyContinue
if ($php) {
    $bad = @()
    Get-ChildItem -Path $src -Filter *.php -Recurse -File |
        Where-Object { $_.FullName -notmatch '\\\.git\\' } |
        ForEach-Object {
            $out = & $php.Source -l $_.FullName 2>&1
            if ($LASTEXITCODE -ne 0) { $bad += "$($_.FullName): $out" }
        }
    if ($bad.Count -gt 0) {
        $bad | ForEach-Object { Write-Error $_ }
        throw 'PHP syntax errors found - nothing deployed'
    }
    Write-Host 'syntax check: all PHP files parse'
} else {
    Write-Warning 'php not on PATH - skipping syntax check'
}

# --------------------------------------------------------------------- deploy
$robocopyArgs = @(
    $src,
    $Live,
    '/E',
    '/R:2', '/W:2',
    '/XD', '.git', '.vscode', 'log', 'lastimage', 'bin',
    '/XF', 'logins.php', 'info.php', 'php_errors.log', 'test.php',
           '.gitignore', 'deploy.ps1', 'AGENTS.md', 'README.md', 'SPEC-remote-pwa.md', 'MANUAL.md',
    '/NJH', '/NJS', '/NP'
)
if ($DryRun) {
    # Show the plan when we are not writing anything.
    $robocopyArgs += '/L'
} else {
    $robocopyArgs += '/NFL'
    $robocopyArgs += '/NDL'
}

& robocopy @robocopyArgs
$code = $LASTEXITCODE

# robocopy exit codes: 0 nothing to do, 1 files copied, 2/3 extra files or dirs
# present, 4/5/6/7 combinations. 8 and above are failures.
if ($code -ge 8) { throw "robocopy failed with exit code $code" }

if ($DryRun) {
    Write-Host 'dry run complete - nothing written'
} elseif ($code -eq 0) {
    Write-Host 'nothing to deploy - live already matches'
} else {
    Write-Host 'deployed'
}

# ---------------------------------------------------------------------- prune
if ($Prune) {
    # Never prune these: they only ever exist on the server.
    $excludeDirs = @('.git', '.vscode', 'log', 'lastimage', 'bin', 'media', 'tmp')
    $excludeFiles = @('logins.php', 'info.php', 'php_errors.log', 'test.php',
                      '.gitignore', 'deploy.ps1', 'AGENTS.md', 'README.md', 'SPEC-remote-pwa.md', 'MANUAL.md')

    # What the copy above would leave behind: same tree, same exclusions.
    $expected = New-Object System.Collections.Hashtable ([System.StringComparer]::Ordinal)
    Get-ChildItem -LiteralPath $src -Recurse -File -Force | ForEach-Object {
        $rel = $_.FullName.Substring($src.Length + 1)
        if ($excludeDirs -contains ($rel -split '\\')[0]) { return }
        if ($excludeFiles -contains $_.Name) { return }
        $expected[$rel] = $true
    }

    $orphans = @(Get-ChildItem -LiteralPath $Live -Recurse -File -Force | Where-Object {
        $rel = $_.FullName.Substring($Live.Length + 1)
        ($excludeDirs -notcontains ($rel -split '\\')[0]) -and (-not $expected.ContainsKey($rel))
    })

    if ($orphans.Count -eq 0) {
        Write-Host 'prune: no orphans'
        return
    }

    Write-Host "prune: $($orphans.Count) file(s) exist only on the server"
    $orphans | ForEach-Object { Write-Host ('  ' + $_.FullName.Substring($Live.Length + 1)) }

    if ($DryRun) {
        Write-Host 'prune: dry run, nothing removed'
        return
    }
    if (-not $Force) {
        if ((Read-Host 'Remove these files? [y/N]') -notmatch '^(y|yes)$') {
            Write-Host 'prune: skipped'
            return
        }
    }

    # Track the directories, deepest first, so only empty ones disappear.
    $touched = New-Object System.Collections.Hashtable ([System.StringComparer]::Ordinal)
    foreach ($o in $orphans) {
        $rel = $o.FullName.Substring($Live.Length + 1)
        Remove-Item -LiteralPath $o.FullName -Force
        Write-Host "prune: removed $rel"
        $dir = $o.Directory
        while ($dir -and $dir.FullName.Length -gt $Live.Length) {
            $touched[$dir.FullName] = $true
            $dir = $dir.Parent
        }
    }

    $touched.Keys | Sort-Object Length -Descending | ForEach-Object {
        if (Test-Path -LiteralPath $_) {
            if (-not (Get-ChildItem -LiteralPath $_ -Force | Select-Object -First 1)) {
                Remove-Item -LiteralPath $_ -Force
                Write-Host ('prune: removed dir ' + $_.Substring($Live.Length + 1))
            }
        }
    }
}
