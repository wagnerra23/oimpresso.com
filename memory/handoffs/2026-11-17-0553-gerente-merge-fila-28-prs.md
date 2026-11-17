---
date: "2026-11-17"
time: "05:53"
slug: "gerente-merge-fila-28-prs"
tldr: "17 PRs mergeados por esta sessão + 3 por sessões paralelas, zero reverts, baseline prod igual em todos; #11070 destrava a lane Sells MySQL no main; pendente [W]: #10888 (.htaccess)."
---

# Handoff — gerente de merge, fila de 28 PRs (2026-11-17)

## Estado
- 21 PRs do snapshot no main (17 por esta sessão, 3 por sessão paralela, + #11070 novo). Zero reverts; baseline prod igual em todos os passos.
- Main voltou a verde na lane required Sells · Pest (MySQL) (#11070).

## Pendente
- **[W]: #10888** (`public/.htaccess`, redirect com barra no fim) — required verdes, não mergeado por ser runtime crítico e estar na lista de não-mergear. Após merge: `curl -sv` de `/pricing/`, `/ajuda/` + regressão.
- 8 drafts comentados (#10953 #10998–#11003 #10491).
- #11071 apareceu durante a rodada, não avaliado.

## Estado MCP no momento do fechamento
Brief #872 (gerado há ~5h na abertura): cycle sem nome ativo, 4 HITL pendentes [W], 633 US sem atribuição. Sem task MCP própria desta rodada.

Detalhe: [session log](../sessions/2026-11-17-gerente-merge-fila.md).
