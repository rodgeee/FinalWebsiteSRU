# Deprecated: use export-data-for-railway.ps1 (products, stocks, orders, staff, adminuser).
Write-Host "Use: .\scripts\export-data-for-railway.ps1"
& (Join-Path $PSScriptRoot "export-data-for-railway.ps1")
