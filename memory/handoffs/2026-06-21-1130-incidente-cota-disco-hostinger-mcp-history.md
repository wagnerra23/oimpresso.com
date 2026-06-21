---
date: "2026-06-21"
time: "11:30 BRT"
slug: incidente-cota-disco-hostinger-mcp-history
tldr: "Incidente prod: o DB estourou a cota de disco do Hostinger (6180/6144 MB) → o provedor auto-revogou INSERT/UPDATE/CREATE → toda escrita do ERP morreu (MySQL 1142). Estouro = mcp_memory_documents_history append-only sem teto (~5 GB), NÃO bug de escrita espúria. Resolvido: DROP history (DB 5788→816 MB) → ALL PRIVILEGES auto-restaurado. 3 PRs mergeados: #3125 guards write-canary+quota no health-check, #3130 retenção jana:memory-history-prune, #3131 proposta ADR mover memória pro CT 100 (gated)."
decided_by: [W]
cycle: CYCLE-08
prs: [3125, 3130, 3131]
related_adrs: ["0062-separacao-runtime-hostinger-ct100", "0061-conhecimento-canonico-git-mcp-zero-automem"]
next_steps:
  - "Decisão [W] na proposta #3131 (mover memória do MCP pro CT 100) — não-urgente, teto #3130 segura"
  - "Headroom durável: limpar bancos legados defuntos no hPanel (perfex/wr2/crm/… — ~5 GB invisíveis ao user SQL)"
---

# Incidente cota-disco Hostinger + hardening da memória (2026-06-21)

## Estado MCP no momento
- Cycle **CYCLE-08** (Receita Onda A) · 75% decorrido · 7d restantes. Incidente foi **off-task** (saúde de prod, não goal de cycle).
- `my-work`: 30 tasks ativas (7 review / 8 blocked / 15 todo) — nenhuma tocada nesta sessão.

## O que aconteceu
Começou com "pode fazer a auditoria? saúde do sistema" → `jana:health-check` ao vivo em prod (SSH key-based Hostinger). Gate vermelho (`profile_distiller_drift`). Investigação revelou que **toda escrita do ERP estava morta** (MySQL 1142 `command denied`) — `INSERT/UPDATE/CREATE` sumidos do grant.

**Causa-raiz real:** não foi grant adulterado — o **DB estourou a cota de disco** (`6180/6144 MB`) e o **Hostinger auto-revoga INSERT/UPDATE/CREATE** (deixa SELECT/DELETE/DROP pra você liberar espaço) e **re-restaura sozinho** ao voltar abaixo da cota. Quem estourou: `mcp_memory_documents_history` (append-only sem teto, ~5 GB). **NÃO é bug de escrita espúria** — o sync já deduplica; faltava **retenção**.

**Resolução:** DROP da history (libera ~5 GB; DB 5788→816 MB) → Hostinger auto-restaurou `ALL PRIVILEGES` (confirma a causa) → recriada vazia → `profile-distill` 76 ok → health-check `ok:true`. No fechamento, a history havia refeito 120 MB → **re-truncada**.

## Artefatos gerados
- **[PR #3125](https://github.com/wagnerra23/oimpresso.com/pull/3125)** (MERGED) — guards no health-check: `db_write_canary` (pega o sintoma) + `db_storage_quota` (pega a causa antes do corte); predicados puros + bite-tests. + incident doc `memory/sessions/2026-06-21-incidente-grant-insert-revogado.md`.
- **[PR #3130](https://github.com/wagnerra23/oimpresso.com/pull/3130)** (MERGED) — `jana:memory-history-prune` (diário 03:20 BRT, keep 20 + janela 90d, driver-agnóstico) + Pest na lane sqlite. Teto preventivo.
- **[PR #3131](https://github.com/wagnerra23/oimpresso.com/pull/3131)** (MERGED) — proposta ADR `memory/decisions/proposals/2026-06-21-mcp-memory-store-ct100.md`: mover memória do MCP pro MariaDB no CT 100 (opção `memory_ct100`, runbook, emenda 0062). **GATED — aguarda decisão [W].**

## Persistência
- **git:** 3 PRs em `main` + incident doc + esta entrada de handoff (este PR de fechamento).
- **MCP:** propaga via webhook GitHub→MCP (~2min após push).
- **prod:** escrita restaurada e confirmada (UPDATE 0-rows OK); history truncada.

## Próximos passos pra retomar
- **Decisão [W] na proposta #3131** (mover memória pro CT 100) — não-urgente, o teto #3130 segura.
- **Headroom durável:** os ~5 GB da conta estão em **bancos legados** (perfex/wr2/crm/… — meu user SQL não os enxerga); limpar os defuntos no **hPanel** é o ganho real — decisão [W] (dados de outros sistemas, não toquei).

## Lições catalogadas
- **Hostinger over-quota = revogação automática de escrita.** Grant com `DELETE/DROP` mas sem `INSERT/UPDATE/CREATE` é o conjunto "over-quota" deles — sinal de cota cheia, não de adulteração. Remediação: liberar espaço (DELETE/DROP estão liberados de propósito) → privilégios voltam sozinhos.
- **Medir antes de truncar.** Quase tranquei a history achando 5 GB; medição mostrou 120 MB (já saneada de manhã) e a conta só ~36 MB acima — premissa de 5 GB era stale. Sempre `information_schema` antes de DML destrutiva.
- **`information_schema` do user só vê o próprio schema** — invisível aos outros 13 bancos da conta; usar o hPanel pra ver o todo.
- Sentinela cego: 17 checks verdes com prod meio-morto porque nenhum provava **escrita** ("a suíte mente") → fechado por `db_write_canary` + `db_storage_quota`.

## Pointers detalhados
- Incident doc completo (timeline, evidência, guards, Fase 2): [`memory/sessions/2026-06-21-incidente-grant-insert-revogado.md`](../sessions/2026-06-21-incidente-grant-insert-revogado.md)
- Proposta CT 100: [`memory/decisions/proposals/2026-06-21-mcp-memory-store-ct100.md`](../decisions/proposals/2026-06-21-mcp-memory-store-ct100.md)
- SSH prod: `ssh -4 -i ~/.ssh/id_ed25519_oimpresso -p 65002 u906587222@148.135.133.115` (key-based; warm-up curl antes — quirk de timeout).
