# Tier A Banner - forca lembranca das skills always-on
# Disparado em SessionStart pelo .claude/settings.json
# Ver ADR 0094 (Constituicao v2) + ADR 0095 (Skills tiers)
# G5 (2026-05-15): banner conta 8 LIVE + 1 dormente, em vez de incluir
# tudo misturado. Wagner: "se organiza, vai entrar o time MCP"
# 2026-05-15 14h fix-encoding: ASCII puro p/ PowerShell 5.1 compat

Write-Host ""
Write-Host "=== CONSTITUICAO v2 - 8 SKILLS TIER A LIVE always-on + 1 DORMENTE ==="
Write-Host ""
Write-Host "  LIVE (auto-trigger ativo):"
Write-Host "  1. brief-first              - chame mcp__oimpresso__brief-fetch PRIMEIRO"
Write-Host "  2. mcp-first                - tools MCP antes de Read/Glob/Grep filesystem"
Write-Host "  3. multi-tenant-patterns    - business_id global scope Tier 0 IRREVOGAVEL"
Write-Host "  4. commit-discipline        - 1 PR = 1 intent, <=300 linhas, sem PII"
Write-Host "  5. mwart-process            - unico caminho Blade->Inertia 5 fases ADR 0104"
Write-Host "  6. mwart-comparative V4     - gate visual F1.5+F3 + Cowork loop ADR 0114"
Write-Host "  7. charter-first            - leia *.charter.md ANTES de editar Pages/*.tsx"
Write-Host "  8. preflight-modulo NOVO    - ler SPEC+RUNBOOK+ADRs ANTES de Edit Modules/X G1 2026-05-15"
Write-Host ""
Write-Host "  DORMENTE (ativa quando feature builder entregar):"
Write-Host "  - ads-route                 - ativa quando S5 entregar decide ~jul/2026"
Write-Host ""
Write-Host "  ADRs canon: 0094 Constituicao v2 mae | 0095 Skills tiers | 0104 MWART | 0114 Cowork"
Write-Host "  Health: php artisan jana:health-check 5 checks SQL diarios"
Write-Host ""
Write-Host "=== INVIOLAVEL (Tier 0 sem ADR mae nova) ==="
Write-Host "  X business_id global scope ADR 0093"
Write-Host "  X Hostinger != CT 100 runtime ADR 0062"
Write-Host "  X ZERO auto-mem privada ADR 0061"
Write-Host "  X ADRs CANON sao append-only"
Write-Host ""

