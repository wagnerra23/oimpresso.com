# loop-fechar-check.ps1 - Rotina IA-OS "Fechar o Loop" (ADR 0234, seed automation #1)
# Disparado em SessionStart (pos brief-fetch). Advisory, NAO bloqueia.
# Manifesto: .claude/loop-fechar-o-loop.json (_automation_registry: true)
#
# Proposito: lembrar de FECHAR loops abertos no inicio da sessao --
#   - decisao (ADR) sem metrica de sucesso definida (principio duro 4)
#   - US marcada done sem evidencia (smoke/test) anexada (R1)
# Observabilidade pura, zero LLM. Registrada no registry de automacoes.
# 2026-05-29 ASCII puro p/ PowerShell 5.1 compat

Write-Host ""
Write-Host "=== ROTINA: FECHAR O LOOP (ADR 0234) ==="
Write-Host "  Loop fechado = decisao -> acao -> METRICA -> aprendizado."
Write-Host "  Antes de encerrar, confira loops abertos:"
Write-Host "  - ADR aceita sem metrica de sucesso? (principio duro 4)"
Write-Host "  - US 'done' sem evidencia (smoke/test real)? (R1)"
Write-Host "  - PR mergeado sem brief-update do modulo?"
Write-Host ""
