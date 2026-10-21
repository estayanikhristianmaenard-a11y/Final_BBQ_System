$ErrorActionPreference = 'Stop'
Write-Host 'BulkBites demo setup: enter credentials only in these local hidden prompts.'
& powershell.exe -NoProfile -ExecutionPolicy Bypass -File (Join-Path $PSScriptRoot 'setup-email.ps1')
if ($LASTEXITCODE -ne 0) { Write-Host 'Email still needs attention. Continuing with public access.' }
& powershell.exe -NoProfile -ExecutionPolicy Bypass -File (Join-Path $PSScriptRoot 'start-public-demo.ps1')
if ($LASTEXITCODE -ne 0) { Write-Host 'Public access needs attention.'; exit 1 }
Write-Host 'Payment demonstrations use the built-in simulator. No PayMongo account or API key is needed.'
Write-Host 'Open Manager > Ordering QR to download the menu QR and check readiness.'
Write-Host 'Use docs/DEMO-CHECKLIST.md for the phone-to-pickup presentation test.'
