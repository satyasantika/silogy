#!/usr/bin/env pwsh
# SILOGY — dev server alternatif bila tidak memakai Nginx FlyEnv (silogy.test)
Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$root = Split-Path -Parent $PSScriptRoot
Set-Location $root

php artisan serve --host=0.0.0.0 --port=8000
