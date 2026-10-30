param()

$ErrorActionPreference = 'Stop'
$avelaProject = Split-Path -Parent $PSScriptRoot
$avelaPointer = Join-Path $avelaProject '.avela-local.json'
if (-not (Test-Path -LiteralPath $avelaPointer)) { throw 'Private setup is missing. See docs/LOCAL_HANDOFF_REVIEW.md.' }
$avelaRuntime = (Get-Content -Raw -LiteralPath $avelaPointer | ConvertFrom-Json).runtime_directory
$avelaContext = Get-Content -Raw -LiteralPath (Join-Path $avelaRuntime 'restore-context.json') | ConvertFrom-Json
if ([IO.Path]::GetFullPath($avelaContext.project) -ne [IO.Path]::GetFullPath($avelaProject)) { throw 'This runtime belongs to another checkout.' }
$avelaConfig = Join-Path $avelaContext.legacy_runtime 'mysql.ini'
$avelaListeners = @(Get-NetTCPConnection -State Listen -LocalPort $avelaContext.port -ErrorAction SilentlyContinue)
if ($avelaListeners.Count -eq 0) {
    Start-Process -FilePath 'C:\Program Files\MySQL\MySQL Server 8.0\bin\mysqld.exe' `
        -ArgumentList ('--defaults-file="' + $avelaConfig + '"') -WorkingDirectory $avelaContext.legacy_runtime -WindowStyle Hidden | Out-Null
    for ($avelaTry = 0; $avelaTry -lt 30; $avelaTry++) {
        Start-Sleep -Milliseconds 500
        $avelaListeners = @(Get-NetTCPConnection -State Listen -LocalPort $avelaContext.port -ErrorAction SilentlyContinue)
        if ($avelaListeners.Count -gt 0) { break }
    }
}
if ($avelaListeners.Count -eq 0) { throw 'The configured local database did not start. No installation or reset was attempted.' }
foreach ($avelaListener in $avelaListeners) {
    $avelaProcess = Get-CimInstance Win32_Process -Filter ('ProcessId=' + $avelaListener.OwningProcess)
    if (-not $avelaProcess.CommandLine.Contains($avelaConfig)) { throw 'The database port belongs to another runtime.' }
}
& $avelaContext.php (Join-Path $PSScriptRoot 'check_device.php')
if ($LASTEXITCODE -ne 0) { throw 'Device readiness failed; server startup stopped.' }
$avelaOrigin = [Uri]$avelaContext.origin
if ($avelaOrigin.Scheme -ne 'http' -or $avelaOrigin.Host -notin @('localhost','127.0.0.1') -or $avelaOrigin.Port -le 1024) { throw 'Only a local development origin is allowed.' }
$avelaWeb = @(Get-NetTCPConnection -State Listen -LocalPort $avelaOrigin.Port -ErrorAction SilentlyContinue)
if ($avelaWeb.Count -gt 0) {
    foreach ($avelaListener in $avelaWeb) {
        $avelaProcess = Get-CimInstance Win32_Process -Filter ('ProcessId=' + $avelaListener.OwningProcess)
        if (-not $avelaProcess.CommandLine.Contains($avelaProject)) { throw 'The HTTP port belongs to another checkout.' }
    }
    Write-Output ('Avela is already running at ' + $avelaContext.origin)
    return
}
# No old-machine payment/email workers, public tunnel or scheduler are started.
Write-Output ('Starting Avela at ' + $avelaContext.origin + '. Keep this terminal open.')
& $avelaContext.php -d ('session.save_path=' + (Join-Path $avelaRuntime 'sessions')) `
    -d ('sys_temp_dir=' + (Join-Path $avelaRuntime 'temp')) `
    -S ($avelaOrigin.Host + ':' + $avelaOrigin.Port) -t (Join-Path $avelaProject 'public') (Join-Path $avelaProject 'router.php')
exit $LASTEXITCODE
