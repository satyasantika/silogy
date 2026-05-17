#!/usr/bin/env pwsh
# SILOGY — reset database & seed (FlyEnv / Windows, tanpa Docker)
Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$root = Split-Path -Parent $PSScriptRoot
Set-Location $root

php artisan migrate:fresh --seed
