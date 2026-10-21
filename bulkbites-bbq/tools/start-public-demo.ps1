$ErrorActionPreference = 'Stop'
$appRoot = Split-Path -Parent $PSScriptRoot
$workspaceRoot = Split-Path -Parent $appRoot
$runtimeDirectory = Join-Path $workspaceRoot '.tmp/public-demo'
New-Item -ItemType Directory -Force -Path $runtimeDirectory | Out-Null
$phpCandidates = @((Get-Command php.exe -ErrorAction SilentlyContinue).Source,'C:/xampp/php/php.exe','D:/xampp/php/php.exe','E:/xampp/php/php.exe')
$phpExecutable = $phpCandidates | Where-Object { $_ -and (Test-Path -LiteralPath $_) } | Select-Object -First 1
if (-not $phpExecutable) { throw 'Install XAMPP or add PHP to PATH.' }
function Test-DemoPort([int]$Port) {
    $client = New-Object Net.Sockets.TcpClient
    try { $task = $client.ConnectAsync('127.0.0.1',$Port); return $task.Wait(1000) -and $client.Connected }
    catch { return $false } finally { $client.Dispose() }
}
if (-not (Test-DemoPort 3306)) {
    $xamppRoot = Split-Path -Parent (Split-Path -Parent $phpExecutable)
    $mysqlExecutable = Join-Path $xamppRoot 'mysql/bin/mysqld.exe'
    $mysqlIni = Join-Path $xamppRoot 'mysql/bin/my.ini'
    if (-not (Test-Path -LiteralPath $mysqlExecutable)) { throw 'Start MySQL in XAMPP first.' }
    $mysqlProcess = Start-Process -FilePath $mysqlExecutable -ArgumentList "--defaults-file=$mysqlIni",'--standalone' -WindowStyle Hidden -WorkingDirectory (Split-Path $mysqlExecutable) -PassThru
    for ($attempt=0;$attempt -lt 15 -and -not (Test-DemoPort 3306);$attempt++) { Start-Sleep -Milliseconds 500 }
    if (-not (Test-DemoPort 3306)) { throw 'MySQL did not start. Check XAMPP.' }
}
if (-not (Test-DemoPort 8080)) {
    $phpProcess = Start-Process -FilePath $phpExecutable -ArgumentList '-S','127.0.0.1:8080','-t',$appRoot,(Join-Path $PSScriptRoot 'demo-router.php') -WorkingDirectory $appRoot -WindowStyle Hidden -RedirectStandardOutput (Join-Path $runtimeDirectory 'php-output.log') -RedirectStandardError (Join-Path $runtimeDirectory 'php-error.log') -PassThru
    $phpProcess.Id | Set-Content (Join-Path $runtimeDirectory 'php.pid')
    for ($attempt=0;$attempt -lt 15 -and -not (Test-DemoPort 8080);$attempt++) { Start-Sleep -Milliseconds 300 }
}
$localResponse = Invoke-WebRequest 'http://127.0.0.1:8080/' -UseBasicParsing -TimeoutSec 15
if ($localResponse.Headers['X-BulkBites-Demo'] -ne '1') { throw 'Port 8080 is not the BulkBites restricted demo server. Stop that service or use the project demo server first.' }
$tunnels = $null
try { $tunnels = Invoke-RestMethod 'http://127.0.0.1:4040/api/tunnels' -TimeoutSec 3 } catch {}
$publicTunnel = @($tunnels.tunnels | Where-Object { $_.proto -eq 'https' -and $_.config.addr -in @('http://127.0.0.1:8080','http://localhost:8080','localhost:8080') }) | Select-Object -First 1
if (-not $publicTunnel) {
    if ($tunnels) { throw 'Another ngrok session is already using port 4040. Stop that session before starting this demo.' }
    $ngrokCandidates = @((Get-Command ngrok.exe -ErrorAction SilentlyContinue).Source,(Join-Path $workspaceRoot '.tmp/bin/ngrok.exe'))
    $ngrokExecutable = $ngrokCandidates | Where-Object { $_ -and (Test-Path -LiteralPath $_) } | Select-Object -First 1
    if (-not $ngrokExecutable) {
        $binDirectory = Join-Path $workspaceRoot '.tmp/bin'
        New-Item -ItemType Directory -Force -Path $binDirectory | Out-Null
        $archivePath = Join-Path $runtimeDirectory 'ngrok.zip'
        Invoke-WebRequest 'https://bin.ngrok.com/c/bNyj1mQVY4c/ngrok-v3-stable-windows-amd64.zip' -OutFile $archivePath -UseBasicParsing
        Expand-Archive -LiteralPath $archivePath -DestinationPath $binDirectory -Force
        $ngrokExecutable = Join-Path $binDirectory 'ngrok.exe'
        $signature = Get-AuthenticodeSignature -LiteralPath $ngrokExecutable
        if ($signature.Status -ne 'Valid' -or $signature.SignerCertificate.Subject -notmatch 'ngrok') { throw 'ngrok signature verification failed.' }
    }
    $configArguments = @()
    $localConfig = Join-Path $runtimeDirectory 'ngrok.yml'
    $defaultConfig = Join-Path $env:LOCALAPPDATA 'ngrok/ngrok.yml'
    if (Test-Path -LiteralPath $localConfig) { $configArguments = @('--config',$localConfig) }
    elseif (-not (Test-Path -LiteralPath $defaultConfig)) {
        Write-Host 'Sign in at https://dashboard.ngrok.com/get-started/your-authtoken.'
        $secureToken = Read-Host 'ngrok authtoken (hidden)' -AsSecureString
        $tokenPointer = [IntPtr]::Zero
        try {
            $tokenPointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($secureToken)
            $tokenText = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($tokenPointer)
            if ($tokenText -notmatch '^[A-Za-z0-9_.-]{20,200}$') { throw 'Invalid ngrok token format.' }
            "version: '2'`nauthtoken: '$tokenText'`nweb_addr: 127.0.0.1:4040`n" | Set-Content -LiteralPath $localConfig -Encoding UTF8
            $localUser = [Security.Principal.WindowsIdentity]::GetCurrent().Name
            & icacls.exe $localConfig /inheritance:r /grant:r "${localUser}:(R,W)" | Out-Null
            if ($LASTEXITCODE -ne 0) { throw 'Could not restrict token-file permissions.' }
        } finally {
            if ($tokenPointer -ne [IntPtr]::Zero) { [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($tokenPointer) }
            $tokenText = $null; $secureToken.Dispose()
        }
        $configArguments = @('--config',$localConfig)
    }
    $ngrokProcess = Start-Process -FilePath $ngrokExecutable -ArgumentList (@('http','http://127.0.0.1:8080','--inspect=false','--log=stdout','--log-format=json','--log-level=warn') + $configArguments) -WorkingDirectory $workspaceRoot -WindowStyle Hidden -RedirectStandardOutput (Join-Path $runtimeDirectory 'ngrok-output.log') -RedirectStandardError (Join-Path $runtimeDirectory 'ngrok-error.log') -PassThru
    $ngrokProcess.Id | Set-Content (Join-Path $runtimeDirectory 'ngrok.pid')
    for ($attempt=0;$attempt -lt 30 -and -not $publicTunnel;$attempt++) {
        Start-Sleep -Milliseconds 500
        try {
            $tunnels = Invoke-RestMethod 'http://127.0.0.1:4040/api/tunnels' -TimeoutSec 2
            $publicTunnel = @($tunnels.tunnels | Where-Object { $_.proto -eq 'https' -and $_.config.addr -eq 'http://127.0.0.1:8080' }) | Select-Object -First 1
        } catch {}
        if ($ngrokProcess.HasExited) { break }
    }
    if (-not $publicTunnel) { throw 'ngrok could not start. Check account authentication and internet access. Logs remain local in .tmp/public-demo.' }
}
@{ public_base_url=$publicTunnel.public_url } | ConvertTo-Json -Compress | & $phpExecutable (Join-Path $PSScriptRoot 'setup-demo.php') --save-url
if ($LASTEXITCODE -ne 0) { throw 'The public ordering URL could not be saved.' }
Write-Host "Public menu: $($publicTunnel.public_url)/customer/menu.php"
Write-Host 'Manager QR page: /manager/demo.php after signing in.'
Write-Host 'Keep this PC and MySQL running. Tunnel processes run in the background.'
Write-Host 'When the public URL changes, download a fresh ordering QR. The local payment simulator needs no webhook.'
