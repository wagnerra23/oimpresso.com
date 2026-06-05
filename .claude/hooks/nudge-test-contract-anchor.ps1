# nudge-test-contract-anchor.ps1 - PreToolUse (advisory - Check 9 / anti-regressao Opcao B Stage 1)
# Lembra de ancorar teste em CONTRATO (SPEC/ADR/proibicoes/charter), nao no codigo.
# Origem: sessao 2026-06-05 - teste tautologico FsmAuthorizationFlagPropertyTest mergeou (#2271).
# Mecaniza: memory/proibicoes.md secao "Ideias avaliadas e DESCARTADAS" (2026-06-05)
#           + Check 9 da skill module-completeness-audit.
# Advisory: exit 0 SEMPRE (nunca bloqueia). Fail-open.

$ErrorActionPreference = 'SilentlyContinue'
try {
    $raw = [Console]::In.ReadToEnd()
    if (-not $raw) { exit 0 }
    $payload = $raw | ConvertFrom-Json
    $path = [string]$payload.tool_input.file_path
    if (-not $path) { exit 0 }

    # So dispara em arquivo de teste PHP
    if ($path -notmatch '(?i)Test\.php$') { exit 0 }

    Write-Host ""
    Write-Host "[ANCORA DE CONTRATO - Check 9 / anti-regressao Opcao B]"
    Write-Host "  Antes de escrever este teste, confirme que a assercao deriva de um CONTRATO"
    Write-Host "  externo (SPEC / ADR / proibicoes / charter), NAO do que a classe ja faz."
    Write-Host "  - Cite a fonte no cabecalho: @see ADR-XXXX + a regra em portugues."
    Write-Host "  - Teste que copia o comportamento atual = tautologico = trava o drift (pior que nao ter)."
    Write-Host "  Ref: memory/proibicoes.md secao 'Ideias avaliadas e DESCARTADAS' (2026-06-05)."
    Write-Host ""
    exit 0
} catch { exit 0 }
