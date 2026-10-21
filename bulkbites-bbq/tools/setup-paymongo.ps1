$ErrorActionPreference = 'Stop'
$keyPointer = [IntPtr]::Zero
$keyText = $null
$secureKey = $null
$setupResult = 1
try {
    $phpCandidates = @((Get-Command php.exe -ErrorAction SilentlyContinue).Source, 'C:/xampp/php/php.exe','D:/xampp/php/php.exe','E:/xampp/php/php.exe')
    $phpExecutable = $phpCandidates | Where-Object { $_ -and (Test-Path -LiteralPath $_) } | Select-Object -First 1
    if (-not $phpExecutable) { throw 'PHP not found.' }
    $script = Join-Path $PSScriptRoot 'setup-paymongo.php'
    & $phpExecutable $script --status
    if ($LASTEXITCODE -eq 0) { exit 0 }
    if ($LASTEXITCODE -ne 2) { exit 1 }
    Write-Host 'Create/sign in to your PayMongo account. Use the secret TEST key from Developer Tools.'
    Write-Host 'This registers or reuses a TEST webhook for the saved public URL. It creates no payments.'
    $secureKey = Read-Host 'Secret TEST key (sk_test_; Enter reuses a saved key)' -AsSecureString
    $keyPointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($secureKey)
    $keyText = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($keyPointer)
    @{ secret_key = $keyText } | ConvertTo-Json -Compress | & $phpExecutable $script
    $setupResult = $LASTEXITCODE
} catch { Write-Host 'Local setup could not finish. Check PHP and the public demo URL.' }
finally {
    if ($keyPointer -ne [IntPtr]::Zero) { [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($keyPointer) }
    $keyText = $null
    if ($secureKey) { $secureKey.Dispose() }
}
exit $setupResult
