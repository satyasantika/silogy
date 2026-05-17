#!/usr/bin/env pwsh
# SILOGY — jalankan test paralel (Pest / PHPUnit via Artisan)
Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$root = Split-Path -Parent $PSScriptRoot
Set-Location $root

php artisan config:clear --ansi
php artisan test --parallel
