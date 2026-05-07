# Tier A Banner — força lembrança das 6 skills always-on
# Disparado em SessionStart pelo .claude/settings.json
# Ver ADR 0094 (Constituição v2) + ADR 0095 (Skills tiers)

Write-Host ""
Write-Host "=== CONSTITUIÇÃO v2 — 6 SKILLS TIER A (always-on) ==="
Write-Host ""
Write-Host "  1. brief-first              — chame mcp__oimpresso__brief-fetch PRIMEIRO"
Write-Host "  2. mcp-first                — tools MCP antes de Read/Glob/Grep filesystem"
Write-Host "  3. multi-tenant-patterns    — business_id global scope (Tier 0 IRREVOGÁVEL)"
Write-Host "  4. commit-discipline        — 1 PR = 1 intent, ≤300 linhas, sem PII"
Write-Host "  5. charter-first   DORMENTE — ativa quando S4 entregar charter-fetch"
Write-Host "  6. ads-route       DORMENTE — ativa quando S5 entregar decide()"
Write-Host ""
Write-Host "  ADRs canon: 0094 (Constituição v2 mãe) · 0095 (Skills tiers)"
Write-Host "  Health: php artisan jana:health-check (5 checks SQL diários)"
Write-Host ""
Write-Host "=== INVIOLÁVEL (Tier 0 sem ADR mãe nova) ==="
Write-Host "  ⛔ business_id global scope (ADR 0093)"
Write-Host "  ⛔ Hostinger ≠ CT 100 runtime (ADR 0062)"
Write-Host "  ⛔ ZERO auto-mem privada (ADR 0061)"
Write-Host "  ⛔ ADRs CANON são append-only"
Write-Host ""
