#!/usr/bin/env pwsh
# SILOGY — Pint (check) + Larastan level 6
Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$root = Split-Path -Parent $PSScriptRoot
Set-Location $root

& "$root\vendor\bin\pint" --test
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

& "$root\vendor\bin\phpstan" analyse --memory-limit=512M
exit $LASTEXITCODE
