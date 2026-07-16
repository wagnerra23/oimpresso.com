#!/usr/bin/env pwsh
# Smoke test agregador — roda CADA hook .ps1 com payload representativo
# e detecta erros PowerShell (encoding/reserved vars/syntax).
#
# Por que existe (regressão 2026-05-15):
# Audit descobriu 4 hooks quebrados em prod por:
#   1. UTF-8 sem BOM + acentos/em-dash quebram PS 5.1 (lê como CP1252)
#   2. `$input` é variável reservada PowerShell (sobrescrever causa side-effects)
#
# Hooks quebrados detectados na auditoria 2026-05-15 17h:
#   - block-mwart-violation.ps1 (em-dash linha 70 caractere 212)
#   - charter-validate.ps1 (em-dash linha 67 caractere 59)
#   - tier-a-banner.ps1 (em-dash + acentos)
#   - mcp-first-warning.ps1 ($input reservada + em-dash)
#
# Fix aplicado: Set-Content -Encoding utf8 adicionou BOM + rewrite ASCII puro
# do banner + rename $input → $rawInput.
#
# Este test PREVINE regressão futura — rodar antes de commit/PR via:
#   pwsh .claude/hooks/test-all-hooks-smoke.ps1
#
# Wagner regra 2026-05-15: "criar testes pra que isso nao volte"

$ErrorActionPreference = 'Continue'
$hooksDir = Join-Path $PSScriptRoot ''
$failures = @()
$skipped = @()
$ok = 0

# Payload por hook (path representativo que dispara o matcher)
$payloads = @{
    'brief-fetch-curl'          = '{"hook_event_name":"SessionStart"}'
    'charter-validate'          = '{"tool_name":"Edit","tool_input":{"file_path":"resources/js/Pages/Whatsapp/Inbox.tsx"}}'
    'check-skills-fresh'        = '{"hook_event_name":"SessionStart"}'
    'commit-discipline-check'   = '{"tool_name":"Bash","tool_input":{"command":"git commit -m test"}}'
    'mcp-first-warning'         = '{"tool_name":"Read","tool_input":{"file_path":"memory/decisions/0094-foo.md"}}'
    'memory-pending'            = '{"hook_event_name":"Stop"}'
    'modulo-preflight-warning'  = '{"tool_name":"Edit","tool_input":{"file_path":"Modules/Whatsapp/Services/Foo.php"}}'
    'tier-a-banner'             = ''
}

# Patterns que indicam erro PowerShell NO HOOK avaliado (BROKEN).
# Excluido `caractere:\d+` e `character:\d+` standalone — falso-positivo
# em stack-trace do proprio test-all-hooks-smoke.ps1. Precisa ancoragem
# em path do hook avaliado pra evitar self-detect.
$brokenPatterns = @(
    'ObjectNotFound',
    'Token .* inesperado',
    'Token .* unexpected',
    'O literal de hash estava incompleto',
    'Hash literal was incomplete',
    'CommandNotFoundException'
)

Get-ChildItem $hooksDir -Filter '*.ps1' | Where-Object {
    # Skip test files (recursivos OR self)
    $_.Name -notmatch '\.test\.ps1$' -and
    $_.Name -notmatch '^test-' -and
    $_.BaseName -ne 'test-all-hooks-smoke'
} | ForEach-Object {
    $hook = $_
    $hookBase = $hook.BaseName
    $payload = $payloads[$hookBase]

    if ($null -eq $payload) {
        $skipped += $hookBase
        return
    }

    # Roda hook com payload
    if ([string]::IsNullOrEmpty($payload)) {
        $output = & powershell -NoProfile -ExecutionPolicy Bypass -File $hook.FullName 2>&1 | Out-String
    } else {
        $output = $payload | & powershell -NoProfile -ExecutionPolicy Bypass -File $hook.FullName 2>&1 | Out-String
    }

    # Detecta padrão de erro
    $broken = $false
    foreach ($pattern in $brokenPatterns) {
        if ($output -match $pattern) {
            $broken = $true
            $failures += @{ hook = $hookBase; pattern = $pattern; output = $output.Substring(0, [Math]::Min(200, $output.Length)) }
            break
        }
    }

    if (-not $broken) {
        $ok++
        Write-Host "  OK $hookBase"
    }
}

Write-Host ""
Write-Host "=== Resumo ==="
Write-Host "  OK: $ok"
Write-Host "  Skipped (sem payload): $($skipped.Count)"
if ($skipped.Count -gt 0) {
    foreach ($s in $skipped) { Write-Host "    - $s" }
}
Write-Host "  BROKEN: $($failures.Count)"

if ($failures.Count -gt 0) {
    Write-Host ""
    Write-Host "=== Hooks com erro PowerShell ==="
    foreach ($f in $failures) {
        Write-Host ""
        Write-Host "  HOOK: $($f.hook)" -ForegroundColor Red
        Write-Host "  PATTERN: $($f.pattern)"
        Write-Host "  OUTPUT (prefix):"
        $f.output -split "`n" | Select-Object -First 4 | ForEach-Object { Write-Host "    $_" }
    }
    Write-Host ""
    Write-Host "FIX provavel: Set-Content -Encoding utf8 (adiciona BOM) OR rewrite ASCII puro" -ForegroundColor Yellow
    Write-Host "OR rename `$input -> `$rawInput (PS reserved variable)" -ForegroundColor Yellow
    exit 1
}

Write-Host ""
Write-Host "Todos hooks OK (compativel PS 5.1)" -ForegroundColor Green
exit 0
