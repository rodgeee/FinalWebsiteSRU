# Export catalog + dashboard data from local Docker MySQL for Railway.
# Usage (from project root):
#   .\scripts\export-data-for-railway.ps1
#
# Copy the output into data\railway-import.sql and redeploy, OR import via Railway MySQL UI.

$ErrorActionPreference = "Stop"
$root = Split-Path $PSScriptRoot -Parent
$outFile = Join-Path $root "data" "railway-import.sql"

if (-not (Test-Path (Join-Path $root "data"))) {
    New-Item -ItemType Directory -Path (Join-Path $root "data") | Out-Null
}

$tables = @(
    "products",
    "stocks",
    "orders",
    "orders_products",
    "services",
    "adminuser",
    "staff"
)

$tableList = ($tables -join " ")
docker compose exec -T mysql sh -c "mysqldump -u `"`$MYSQL_USER`" -p`"`$MYSQL_PASSWORD`" `"`$MYSQL_DATABASE`" $tableList --no-create-info --skip-triggers --complete-insert" `
    > $outFile

Write-Host "Exported to: $outFile"
Write-Host "Next: commit is optional (file is gitignored). Redeploy Railway to auto-import when products table is empty."
Write-Host "Or import manually in Railway MySQL."
