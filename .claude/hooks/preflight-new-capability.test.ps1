# Smoke test -- preflight-new-capability.ps1 (anti-reinvenção)

$ErrorActionPreference = 'Stop'
$here = Split-Path $MyInvocation.MyCommand.Path -Parent
$hook = Join-Path $here 'preflight-new-capability.ps1'

$failures = 0
$total = 0

function Test-Hook {
    param([string]$Name, [hashtable]$Payload, [bool]$ExpectWarn)

    $script:total++
    $json = $Payload | ConvertTo-Json -Compress -Depth 10
    $tmp = [System.IO.Path]::GetTempFileName()
    [System.IO.File]::WriteAllText($tmp, $json, (New-Object System.Text.UTF8Encoding $false))
    $output = & cmd /c "powershell -NoProfile -ExecutionPolicy Bypass -File `"$hook`" < `"$tmp`" 2>NUL"
    Remove-Item $tmp -Force -ErrorAction SilentlyContinue

    $joined = ($output | Out-String)
    $warned = [bool]($joined -match 'oimpresso-anti-reinvencao')
    if ($warned -eq $ExpectWarn) {
        Write-Host "  OK  $Name (warn=$warned)"
    } else {
        Write-Host "  FAIL $Name -- expected warn=$ExpectWarn got=$warned" -ForegroundColor Red
        $script:failures++
    }
}

Write-Host "=== preflight-new-capability smoke ==="

# 1. Checker NOVO (não existe) → avisa (o bug recorrente)
Test-Hook 'Checker novo avisa' @{
    tool_name = 'Write'
    tool_input = @{ file_path = 'D:\oimpresso.com\Modules\Governance\Services\Checkers\FooBarChecker.php'; content = '<?php' }
} $true

# 2. Reconciler novo → avisa
Test-Hook 'Reconciler novo avisa' @{
    tool_name = 'Write'
    tool_input = @{ file_path = 'D:\oimpresso.com\Modules\Jana\Services\FooReconciler.php'; content = '<?php' }
} $true

# 3. Arquivo NÃO-capability (ex Page.tsx) → silêncio
Test-Hook 'nao-capability silencioso' @{
    tool_name = 'Write'
    tool_input = @{ file_path = 'D:\oimpresso.com\resources\js\Pages\Foo\Index.tsx'; content = 'x' }
} $false

# 4. Arquivo capability que JÁ EXISTE (edit, não criar) → silêncio
Test-Hook 'capability existente (edit) silencioso' @{
    tool_name = 'Write'
    tool_input = @{ file_path = 'D:\oimpresso.com\Modules\Governance\Services\Checkers\AdrLinksChecker.php'; content = 'x' }
} $false

# 5. tool != Write → silêncio
Test-Hook 'Read silencioso' @{
    tool_name = 'Read'
    tool_input = @{ file_path = 'D:\oimpresso.com\Modules\X\Services\YChecker.php' }
} $false

Write-Host ""
if ($failures -eq 0) { Write-Host "PASS ($total testes)" -ForegroundColor Green; exit 0 }
else { Write-Host "FAIL ($failures/$total)" -ForegroundColor Red; exit 1 }
