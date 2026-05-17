# Smoke test -- block-routes-string-legacy.ps1

$ErrorActionPreference = 'Stop'
$here = Split-Path $MyInvocation.MyCommand.Path -Parent
$hook = Join-Path $here 'block-routes-string-legacy.ps1'

$failures = 0
$total = 0

function Test-Hook {
    param([string]$Name, [hashtable]$Payload, [string]$ExpectedDecision, [string]$Mode = 'strict')

    $script:total++
    $env:OIMPRESSO_ROUTES_HOOK_MODE = $Mode
    $env:OIMPRESSO_ROUTES_OVERRIDE = $null

    $json = $Payload | ConvertTo-Json -Compress -Depth 10
    $tmpFile = [System.IO.Path]::GetTempFileName()
    [System.IO.File]::WriteAllText($tmpFile, $json, (New-Object System.Text.UTF8Encoding $false))

    $output = & cmd /c "powershell -NoProfile -ExecutionPolicy Bypass -File `"$hook`" < `"$tmpFile`" 2>NUL"
    Remove-Item $tmpFile -Force -ErrorAction SilentlyContinue

    $decision = '(none)'
    if ($output) {
        try { $decision = ($output | ConvertFrom-Json).decision } catch { $decision = '(parse-error)' }
    }

    if ($decision -eq $ExpectedDecision) {
        Write-Host "  OK  $Name (decision=$decision)"
    } else {
        Write-Host "  FAIL $Name -- expected=$ExpectedDecision got=$decision" -ForegroundColor Red
        $script:failures++
    }
}

Write-Host "=== block-routes-string-legacy smoke ==="

$legacyContent = @"
<?php
use Illuminate\Support\Facades\Route;

Route::get('/sells', 'SellController@index');
Route::resource('purchases', 'PurchaseController');
"@

$fqcnContent = @"
<?php
use Illuminate\Support\Facades\Route;
use Modules\Sells\Http\Controllers\SellController;

Route::get('/sells', [SellController::class, 'index']);
Route::resource('purchases', \Modules\Purchases\Http\Controllers\PurchaseController::class);
"@

$comentado = @"
<?php
// NUNCA fazer: Route::get('/foo', 'BarController@baz') -- usar FQCN
Route::get('/foo', [BarController::class, 'baz']);
"@

# T1: routes/web.php com string legacy -> deny
Test-Hook -Name 'T1 routes-legacy-strict deny' -Payload @{
    tool_name = 'Write'
    tool_input = @{ file_path = 'D:/oimpresso.com/routes/web.php'; content = $legacyContent }
} -ExpectedDecision 'deny' -Mode 'strict'

# T2: routes/web.php com FQCN -> allow
Test-Hook -Name 'T2 routes-fqcn allow' -Payload @{
    tool_name = 'Write'
    tool_input = @{ file_path = 'D:/oimpresso.com/routes/web.php'; content = $fqcnContent }
} -ExpectedDecision '(none)' -Mode 'strict'

# T3: Modules/X/Routes/web.php com legacy -> deny
Test-Hook -Name 'T3 module-routes-legacy deny' -Payload @{
    tool_name = 'Edit'
    tool_input = @{ file_path = 'D:/oimpresso.com/Modules/Sells/Routes/web.php'; new_string = $legacyContent }
} -ExpectedDecision 'deny' -Mode 'strict'

# T4: Modules/X/Http/routes.php (variante nWidart) com legacy -> deny
Test-Hook -Name 'T4 module-http-routes-legacy deny' -Payload @{
    tool_name = 'Write'
    tool_input = @{ file_path = 'D:/oimpresso.com/Modules/Jana/Http/routes.php'; content = $legacyContent }
} -ExpectedDecision 'deny' -Mode 'strict'

# T5: Arquivo fora de routes/* -> allow (out of scope)
Test-Hook -Name 'T5 not-routes-file allow' -Payload @{
    tool_name = 'Write'
    tool_input = @{ file_path = 'D:/test/Foo.php'; content = $legacyContent }
} -ExpectedDecision '(none)' -Mode 'strict'

# T6: comentario explicando o padrao -> allow (skip linhas comecando com //)
Test-Hook -Name 'T6 comentario-allow' -Payload @{
    tool_name = 'Write'
    tool_input = @{ file_path = 'D:/oimpresso.com/routes/web.php'; content = $comentado }
} -ExpectedDecision '(none)' -Mode 'strict'

# T7: warn mode com legacy -> allow (warn nao bloqueia)
Test-Hook -Name 'T7 warn-mode allow' -Payload @{
    tool_name = 'Write'
    tool_input = @{ file_path = 'D:/oimpresso.com/routes/web.php'; content = $legacyContent }
} -ExpectedDecision '(none)' -Mode 'warn'

# T8: Self-exempt -- editar o proprio rules/routes.md (cita 'Controller@method' em backticks)
Test-Hook -Name 'T8 self-exempt-rules allow' -Payload @{
    tool_name = 'Edit'
    tool_input = @{ file_path = 'D:/oimpresso.com/.claude/rules/routes.md'; new_string = $legacyContent }
} -ExpectedDecision '(none)' -Mode 'strict'

Write-Host ""
Write-Host "Total: $total | Failures: $failures"
if ($failures -gt 0) { exit 1 } else { exit 0 }
