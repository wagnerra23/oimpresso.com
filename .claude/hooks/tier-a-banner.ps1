# Tier A Banner - forca lembranca das skills Tier A (nucleo seguranca/disciplina)
# Disparado em SessionStart pelo .claude/settings.json
# Ver ADR 0094 (Constituicao v2) + ADR 0095 (Skills tiers) + ADR 0225 (recalibracao 4.8)
# 2026-05-28 (ADR 0225): recalibrado 8->5 Tier A. Claude 4.8 (1M context, melhor
#   instruction-following) torna always-on de skill auto-trigger redundante.
#   6 rebaixadas pra auto-trigger (disparam por path/intencao no momento exato).
# 2026-05-15 14h fix-encoding: ASCII puro p/ PowerShell 5.1 compat

Write-Host ""
Write-Host "=== CONSTITUICAO v2 - 5 SKILLS TIER A (nucleo) + 6 AUTO-TRIGGER (ADR 0225) ==="
Write-Host ""
Write-Host "  TIER A (seguranca/LGPD/disciplina - sempre relevantes):"
Write-Host "  1. multi-tenant-patterns    - business_id global scope Tier 0 IRREVOGAVEL"
Write-Host "  2. commit-discipline        - 1 PR = 1 intent, <=300 linhas, sem PII"
Write-Host "  3. incident-done-checklist  - DoD smoke real ANTES de declarar pronto (R1)"
Write-Host "  4. memory-first-secret-search - consultar _INDEX-SECRETS ANTES de buscar token"
Write-Host "  5. hostinger-dns-autonomy   - nao escalar acao automatizavel pro Wagner"
Write-Host ""
Write-Host "  AUTO-TRIGGER (Tier B - disparam por path/intencao, ADR 0225):"
Write-Host "  - brief-first               - brief-fetch (conveniencia inicio sessao)"
Write-Host "  - mcp-first                 - tools MCP antes de filesystem"
Write-Host "  - mwart-process / -comparative - dispara em Edit Pages/*.tsx"
Write-Host "  - charter-first             - dispara ao editar tsx com .charter.md"
Write-Host "  - preflight-modulo          - dispara em Edit Modules/<X>/ (+ hook + proibicoes)"
Write-Host ""
Write-Host "  PROTOCOLO WAGNER: doc memory/reference/PROTOCOLO-WAGNER-SEMPRE.md (on-demand)"
Write-Host "    R1 smoke real + R10 aprovacao humana = Tier 0 duro (memory/proibicoes.md)"
Write-Host ""
Write-Host "  DORMENTE: ads-route (ativa quando S5 entregar decide ~jul/2026)"
Write-Host ""
Write-Host "  ADRs canon: 0094 Constituicao v2 | 0095 Skills tiers | 0225 recalibracao 4.8 | 0104 MWART"
Write-Host "  Health: php artisan jana:health-check 5 checks SQL diarios"
Write-Host ""
Write-Host "=== INVIOLAVEL (Tier 0 sem ADR mae nova) ==="
Write-Host "  X business_id global scope ADR 0093"
Write-Host "  X Hostinger != CT 100 runtime ADR 0062"
Write-Host "  X ZERO auto-mem privada ADR 0061"
Write-Host "  X ADRs CANON sao append-only"
Write-Host ""

