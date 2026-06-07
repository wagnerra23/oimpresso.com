# Smoke test -- licoes-code-two-strikes.ps1
# Roda: powershell -NoProfile -ExecutionPolicy Bypass -File .claude/hooks/licoes-code-two-strikes.test.ps1

$ErrorActionPreference = 'Stop'
$here = Split-Path $MyInvocation.MyCommand.Path -Parent
$hook = Join-Path $here 'licoes-code-two-strikes.ps1'

$failures = 0
$total = 0

function Invoke-Hook {
    param([string]$Content, [string]$Threshold = $null)

    $tmp = [System.IO.Path]::GetTempFileName()
    [System.IO.File]::WriteAllText($tmp, $Content, (New-Object System.Text.UTF8Encoding $false))

    $env:OIMPRESSO_LICOES_CODE_PATH = $tmp
    if ($Threshold) { $env:OIMPRESSO_LICOES_THRESHOLD = $Threshold } else { $env:OIMPRESSO_LICOES_THRESHOLD = $null }

    $out = & powershell -NoProfile -ExecutionPolicy Bypass -File $hook 2>$null
    Remove-Item $tmp -Force -ErrorAction SilentlyContinue
    $env:OIMPRESSO_LICOES_CODE_PATH = $null
    $env:OIMPRESSO_LICOES_THRESHOLD = $null

    return ($out -join "`n")
}

function Assert-Contains {
    param([string]$Name, [string]$Haystack, [string]$Needle, [bool]$ShouldContain = $true)
    $script:total++
    $has = $Haystack.Contains($Needle)
    if ($has -eq $ShouldContain) {
        Write-Host "  OK  $Name"
    } else {
        $verb = if ($ShouldContain) { 'esperava conter' } else { 'NAO devia conter' }
        Write-Host "  FAIL $Name -- $verb '$Needle'" -ForegroundColor Red
        Write-Host "       saida: $Haystack" -ForegroundColor DarkGray
        $script:failures++
    }
}

Write-Host "=== licoes-code-two-strikes smoke ==="

# T1: Ocorr 2 + Gate none -> ALARME (cita LC-90 + PROMOVER)
$c1 = @"
# header
## LC-90 - teste sem gate repetido
- **Ocorrencias:** 2
- **Gate:** none
"@
$o1 = Invoke-Hook -Content $c1
Assert-Contains 'T1 alarme cita LC-90' $o1 'LC-90' $true
Assert-Contains 'T1 mostra PROMOVER' $o1 'PROMOVER' $true

# T2: Ocorr 2 + Gate preenchido -> NAO alarma (id nao aparece)
$c2 = @"
## LC-91 - teste com gate existente
- **Ocorrencias:** 2
- **Gate:** multi-tenant-gate
"@
$o2 = Invoke-Hook -Content $c2
Assert-Contains 'T2 com gate nao alarma' $o2 'LC-91' $false

# T3: Ocorr 1 + Gate none -> WATCH, nao alarme
$c3 = @"
## LC-92 - novo sem reincidencia
- **Ocorrencias:** 1
- **Gate:** none
"@
$o3 = Invoke-Hook -Content $c3
Assert-Contains 'T3 watch mostra WATCH' $o3 'WATCH' $true
Assert-Contains 'T3 watch sem PROMOVER' $o3 'PROMOVER' $false

# T4: threshold=3 -> Ocorr 2 sem gate NAO alarma (vira watch)
$c4 = @"
## LC-93 - duas ocorrencias com limiar 3
- **Ocorrencias:** 2
- **Gate:** none
"@
$o4 = Invoke-Hook -Content $c4 -Threshold '3'
Assert-Contains 'T4 limiar 3 nao alarma LC-93 promover' $o4 'PROMOVER' $false
Assert-Contains 'T4 limiar 3 vira watch' $o4 'WATCH' $true

# T5: arquivo so com gates resolvidos -> sem saida
$c5 = @"
## LC-94 - tudo resolvido
- **Ocorrencias:** 5
- **Gate:** algum-gate
"@
$o5 = Invoke-Hook -Content $c5
Assert-Contains 'T5 tudo resolvido sem saida' $o5 'LICOES [CODE]' $false

Write-Host ""
Write-Host "Total: $total | Failures: $failures"
if ($failures -gt 0) { exit 1 } else { exit 0 }
