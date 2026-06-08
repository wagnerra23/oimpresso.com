# Smoke test do hook block-automem.ps1 -- valida regra dos 3 tiers (ADR 0131).
#
# Rodar manual:
#   pwsh .claude/hooks/block-automem.test.ps1
#   (ou) powershell .claude/hooks/block-automem.test.ps1
#
# Exit 0 = 3/3 casos passam. Nao-zero = falha (caso explicitado).

$ErrorActionPreference = 'Stop'

$hookPath = Join-Path $PSScriptRoot 'block-automem.ps1'
if (-not (Test-Path $hookPath)) {
    Write-Error "Hook nao encontrado em $hookPath"
    exit 2
}

# Detecta runtime PowerShell disponivel (pwsh 7+ preferido; powershell 5.1 fallback)
if (Get-Command pwsh -ErrorAction SilentlyContinue) {
    $script:psBin = 'pwsh'
} elseif (Get-Command powershell -ErrorAction SilentlyContinue) {
    $script:psBin = 'powershell'
} else {
    Write-Error "Nem pwsh nem powershell encontrados no PATH"
    exit 2
}

function Invoke-Hook {
    param([string]$ToolName, [string]$FilePath)

    $payload = @{
        tool_name = $ToolName
        tool_input = @{ file_path = $FilePath }
    } | ConvertTo-Json -Compress

    $result = $payload | & $script:psBin -NoProfile -File $hookPath 2>&1
    return [string]$result
}

$failures = @()

# Caso 1: Write em ~/.claude/projects/*/memory/foo.md -> DENY esperado
$caso1 = Invoke-Hook -ToolName 'Write' -FilePath 'C:/Users/wagne/.claude/projects/D--oimpresso-com/memory/foo.md'
if ($caso1 -match '"decision":"deny"') {
    Write-Host "[OK] Caso 1: auto-mem legada bloqueada"
} else {
    Write-Host "[FAIL] Caso 1: auto-mem legada NAO foi bloqueada -- output: $caso1"
    $failures += 'caso1'
}

# Caso 2: Write em ~/.claude/oimpresso-local/tasks-pessoais.md -> ALLOW esperado
$caso2 = Invoke-Hook -ToolName 'Write' -FilePath 'C:/Users/wagne/.claude/oimpresso-local/tasks-pessoais.md'
if ([string]::IsNullOrWhiteSpace($caso2) -or ($caso2 -notmatch '"decision":"deny"')) {
    Write-Host "[OK] Caso 2: oimpresso-local permitido"
} else {
    Write-Host "[FAIL] Caso 2: oimpresso-local foi BLOQUEADO indevidamente -- output: $caso2"
    $failures += 'caso2'
}

# Caso 3: Write em memory/decisions/0XXX-foo.md (canonico git) -> ALLOW esperado
$caso3 = Invoke-Hook -ToolName 'Write' -FilePath 'D:/oimpresso.com/memory/decisions/0131-tiering-memoria.md'
if ([string]::IsNullOrWhiteSpace($caso3) -or ($caso3 -notmatch '"decision":"deny"')) {
    Write-Host "[OK] Caso 3: canonico git permitido"
} else {
    Write-Host "[FAIL] Caso 3: canonico git foi BLOQUEADO indevidamente -- output: $caso3"
    $failures += 'caso3'
}

if ($failures.Count -eq 0) {
    Write-Host ""
    Write-Host "[PASS] 3/3 casos validados (ADR 0131 enforced)" -ForegroundColor Green
    exit 0
} else {
    Write-Host ""
    Write-Host "[FAIL] $($failures.Count)/3 casos falharam: $($failures -join ', ')" -ForegroundColor Red
    exit 1
}
