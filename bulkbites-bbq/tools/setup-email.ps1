$ErrorActionPreference = 'Stop'
$setupExitCode = 1
$passwordPointer = [IntPtr]::Zero
$plainPassword = $null
$payload = $null
$securePassword = $null
try {
    $phpCandidates = @()
    $phpCommand = Get-Command php.exe -ErrorAction SilentlyContinue
    if ($phpCommand) { $phpCandidates += $phpCommand.Source }
    $searchDirectory = $PSScriptRoot
    for ($depth = 0; $depth -lt 6 -and $searchDirectory; $depth++) {
        $phpCandidates += Join-Path $searchDirectory 'php/php.exe'
        $searchDirectory = Split-Path -Parent $searchDirectory
    }
    foreach ($drive in @('C:', 'D:', 'E:')) { $phpCandidates += "$drive/xampp/php/php.exe" }
    $phpExecutable = $phpCandidates | Where-Object { Test-Path -LiteralPath $_ -PathType Leaf } | Select-Object -First 1
    if (-not $phpExecutable) { throw 'PHP was not found. Install XAMPP or add php.exe to PATH.' }
    $setupScript = Join-Path $PSScriptRoot 'setup-email.php'
    # Inspect without reading stdin, prompting for credentials, or sending email.
    & $phpExecutable $setupScript --status
    $configurationStatus = $LASTEXITCODE
    if ($configurationStatus -eq 0) { exit 0 }
    if ($configurationStatus -ne 2) { exit 1 }
    Write-Host 'BulkBites BBQ - first-time Gmail sender setup'
    Write-Host 'Use a Google App Password. Do not enter your normal account password.'
    Write-Host 'The password is hidden and is sent to the local PHP process through stdin.'
    $sender = Read-Host 'Sender Google account email'
    $securePassword = Read-Host '16-letter Google App Password' -AsSecureString
    $passwordPointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($securePassword)
    $plainPassword = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($passwordPointer)
    $payload = @{ sender = $sender; password = $plainPassword } | ConvertTo-Json -Compress
    $payload | & $phpExecutable $setupScript
    $setupExitCode = $LASTEXITCODE
} catch {
    # Avoid dumping invocation details or any credential-bearing variables.
    Write-Host 'Setup could not finish. Check that XAMPP is installed and the config directory is writable.'
} finally {
    if ($passwordPointer -ne [IntPtr]::Zero) { [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($passwordPointer) }
    $plainPassword = $null
    $payload = $null
    if ($securePassword) { $securePassword.Dispose() }
}
exit $setupExitCode
