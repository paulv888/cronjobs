#!/usr/bin/env pwsh
<#
.SYNOPSIS
    Deploy this working tree to the live HA server.

.DESCRIPTION
    One-way, additive copy. There is no /MIR, so anything that exists only on
    the server (log/, lastimage/, .htaccess, media/, images/) is never deleted.

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
#>
[CmdletBinding()]
param(
    [string] $Live = '\\home\www\ha',
    [switch] $DryRun
)

$ErrorActionPreference = 'Stop'
$src = $PSScriptRoot

if (-not (Test-Path -LiteralPath $Live)) {
    throw "Live path not reachable: $Live"
}

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
           '.gitignore', 'deploy.ps1', 'AGENTS.md',
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
