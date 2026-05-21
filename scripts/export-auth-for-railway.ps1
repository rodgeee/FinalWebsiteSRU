# Export admin + staff tables for Railway (login accounts).
# Usage: .\scripts\export-auth-for-railway.ps1

$ErrorActionPreference = "Stop"
$outFile = Join-Path $PSScriptRoot ".." "auth-export.sql"

docker compose exec -T mysql sh -c 'mysqldump -u "$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE" adminuser staff' `
    > $outFile

Write-Host "Exported to: $outFile"
Write-Host "Import into Railway MySQL, then run: php bin/console app:verify-staff-for-login"
