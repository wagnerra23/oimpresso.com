# Smoke test -- block-bom-encoding.ps1
# Roda: powershell -NoProfile -ExecutionPolicy Bypass -File .claude/hooks/block-bom-encoding.test.ps1

$ErrorActionPreference = 'Stop'
$here = Split-Path $MyInvocation.MyCommand.Path -Parent
$hook = Join-Path $here 'block-bom-encoding.ps1'

$failures = 0
$total = 0

function Test-Hook {
    param([string]$Name, [hashtable]$Payload, [string]$ExpectedDecision, [string]$Mode = 'strict')

    $script:total++
    $env:OIMPRESSO_BOM_HOOK_MODE = $Mode
    $env:OIMPRESSO_BOM_OVERRIDE = $null

    # Escreve JSON via WriteAllText UTF-8 (preserva BOM real -- pipe stdout perde via OEM cp).
    $json = $Payload | ConvertTo-Json -Compress -Depth 10
    $tmpFile = [System.IO.Path]::GetTempFileName()
    [System.IO.File]::WriteAllText($tmpFile, $json, (New-Object System.Text.UTF8Encoding $false))

    # Roda hook redirecionando stdin do arquivo temp via cmd /c (preserva bytes raw).
    # Redireciona stderr pra NUL no cmd-level (2>NUL) -- PS 5.1 envolve stderr de native cmd em RemoteException.
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

Write-Host "=== block-bom-encoding smoke ==="

# T1: PHP com BOM em strict -> deny
$bom = [char]0xFEFF
Test-Hook -Name 'T1 PHP-com-BOM strict deny' -Payload @{
    tool_name = 'Write'
    tool_input = @{ file_path = 'D:/test/Foo.php'; content = "${bom}<?php`nnamespace App;" }
} -ExpectedDecision 'deny' -Mode 'strict'

# T2: PHP sem BOM em strict -> allow (sem JSON output)
Test-Hook -Name 'T2 PHP-sem-BOM strict allow' -Payload @{
    tool_name = 'Write'
    tool_input = @{ file_path = 'D:/test/Foo.php'; content = "<?php`nnamespace App;" }
} -ExpectedDecision '(none)' -Mode 'strict'

# T3: Markdown com BOM -> allow (md fora do escopo, BOM ok)
Test-Hook -Name 'T3 MD-com-BOM allow' -Payload @{
    tool_name = 'Write'
    tool_input = @{ file_path = 'D:/test/README.md'; content = "${bom}# Title" }
} -ExpectedDecision '(none)' -Mode 'strict'

# T4: Edit em JS com BOM -> deny
Test-Hook -Name 'T4 JS-Edit-com-BOM strict deny' -Payload @{
    tool_name = 'Edit'
    tool_input = @{ file_path = 'D:/test/app.js'; new_string = "${bom}const x = 1;" }
} -ExpectedDecision 'deny' -Mode 'strict'

# T5: Read tool -> sem mocking, hook nem checa
Test-Hook -Name 'T5 Read-not-matched allow' -Payload @{
    tool_name = 'Read'
    tool_input = @{ file_path = 'D:/test/Foo.php' }
} -ExpectedDecision '(none)' -Mode 'strict'

# T6: warn mode com BOM -> nao bloqueia (sem JSON output, exit 0)
Test-Hook -Name 'T6 BOM-warn-mode allow' -Payload @{
    tool_name = 'Write'
    tool_input = @{ file_path = 'D:/test/Foo.php'; content = "${bom}<?php" }
} -ExpectedDecision '(none)' -Mode 'warn'

Write-Host ""
Write-Host "Total: $total | Failures: $failures"
if ($failures -gt 0) { exit 1 } else { exit 0 }
