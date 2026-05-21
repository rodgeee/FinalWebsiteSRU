# Export products + stock from local Docker MySQL for import into Railway.
# Usage (from project root):
#   .\scripts\export-products-for-railway.ps1
#
# Then import products-export.sql into Railway MySQL (Dashboard -> Connect).

$ErrorActionPreference = "Stop"
$outFile = Join-Path $PSScriptRoot ".." "products-export.sql"

docker compose exec -T mysql sh -c 'mysqldump -u "$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE" products stocks' `
    > $outFile

Write-Host "Exported to: $outFile"
Write-Host "Import this file into your Railway MySQL database."
