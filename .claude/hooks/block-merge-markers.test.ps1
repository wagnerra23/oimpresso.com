# Smoke test -- block-merge-markers.ps1

$ErrorActionPreference = 'Stop'
$here = Split-Path $MyInvocation.MyCommand.Path -Parent
$hook = Join-Path $here 'block-merge-markers.ps1'

$failures = 0
$total = 0

function Test-Hook {
    param([string]$Name, [hashtable]$Payload, [string]$ExpectedDecision, [string]$Mode = 'strict')

    $script:total++
    $env:OIMPRESSO_MERGE_HOOK_MODE = $Mode
    $env:OIMPRESSO_MERGE_OVERRIDE = $null

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

Write-Host "=== block-merge-markers smoke ==="

$conflict = @"
<?php
namespace App;
<<<<<<< HEAD
public function foo() { return 1; }
=======
public function foo() { return 2; }
>>>>>>> feature-branch
"@

$clean = @"
<?php
namespace App;
public function foo() { return 1; }
"@

$comentado = @"
<?php
// Exemplo na docstring: a sequencia <<<<<<< HEAD aparece em conflitos.
public function foo() { return 1; }
"@

# T1: PHP com markers strict -> deny
Test-Hook -Name 'T1 PHP-com-markers strict deny' -Payload @{
    tool_name = 'Write'
    tool_input = @{ file_path = 'D:/test/Foo.php'; content = $conflict }
} -ExpectedDecision 'deny' -Mode 'strict'

# T2: PHP sem markers -> allow
Test-Hook -Name 'T2 PHP-sem-markers allow' -Payload @{
    tool_name = 'Write'
    tool_input = @{ file_path = 'D:/test/Foo.php'; content = $clean }
} -ExpectedDecision '(none)' -Mode 'strict'

# T3: markers em arquivo binario (PNG) -> skip
Test-Hook -Name 'T3 PNG-skip allow' -Payload @{
    tool_name = 'Write'
    tool_input = @{ file_path = 'D:/test/foo.png'; content = $conflict }
} -ExpectedDecision '(none)' -Mode 'strict'

# T4: warn mode com markers -> allow (warn nao bloqueia)
Test-Hook -Name 'T4 warn-mode-com-markers allow' -Payload @{
    tool_name = 'Write'
    tool_input = @{ file_path = 'D:/test/Foo.php'; content = $conflict }
} -ExpectedDecision '(none)' -Mode 'warn'

# T5: Edit em MD com markers -> deny (markdown tambem entra)
Test-Hook -Name 'T5 MD-Edit-com-markers strict deny' -Payload @{
    tool_name = 'Edit'
    tool_input = @{ file_path = 'D:/test/README.md'; new_string = $conflict }
} -ExpectedDecision 'deny' -Mode 'strict'

# T6: Self-exempt -- editar o proprio hook deve passar mesmo com markers (auto-citacao)
Test-Hook -Name 'T6 self-exempt-hook allow' -Payload @{
    tool_name = 'Edit'
    tool_input = @{ file_path = 'D:/oimpresso.com/.claude/hooks/block-merge-markers.ps1'; new_string = $conflict }
} -ExpectedDecision '(none)' -Mode 'strict'

# T7: Linha comentada com marker em PHP -- AINDA deve bloquear (e seguro pecar pelo excesso aqui)
# Comentario `// <<<<<<<` ESTA em coluna 0 com '//' apenas, mas o padrao regex pega LINE START '<<<<<<< '
# entao um comentario tipo `// <<<<<<<` nao bate. O test verifica isso.
Test-Hook -Name 'T7 comentario-explicando-marker allow' -Payload @{
    tool_name = 'Write'
    tool_input = @{ file_path = 'D:/test/Foo.php'; content = $comentado }
} -ExpectedDecision '(none)' -Mode 'strict'

Write-Host ""
Write-Host "Total: $total | Failures: $failures"
if ($failures -gt 0) { exit 1 } else { exit 0 }
