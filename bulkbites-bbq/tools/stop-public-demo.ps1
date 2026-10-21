$ErrorActionPreference = 'Stop'
$workspaceRoot = Split-Path -Parent (Split-Path -Parent $PSScriptRoot)
$pidFiles = @((Join-Path $workspaceRoot '.tmp/public-demo/ngrok.pid'),(Join-Path $workspaceRoot '.tmp/ngrok-demo.pid'))
$stopped = $false
foreach ($pidFile in $pidFiles) {
    if (-not (Test-Path -LiteralPath $pidFile)) { continue }
    $tunnelPid = [int](Get-Content -LiteralPath $pidFile)
    $tunnelProcess = Get-CimInstance Win32_Process -Filter "ProcessId=$tunnelPid" -ErrorAction SilentlyContinue
    if ($tunnelProcess -and $tunnelProcess.Name -eq 'ngrok.exe' -and $tunnelProcess.CommandLine -match 'http://127\.0\.0\.1:8080') {
        Stop-Process -Id $tunnelPid -ErrorAction Stop
        $stopped = $true
    }
}
if ($stopped) { Write-Host 'BulkBites public tunnel stopped. Local PHP and MySQL were left running.' }
else { Write-Host 'No matching project-owned public tunnel was found. No process was stopped.' }
