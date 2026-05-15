---
name: cycle_id NULL escondia drift estrutural — cycles-close --rollover move 0 tasks
description: Tasks com cycle_id NULL não rolam automaticamente entre cycles. Drift detectado pelo brief não tem mecanismo de correção em massa.
type: feedback
---
Ao fazer `cycles-close --rollover_to=CYCLE-06` esperando mover 3 DOING (US-WA-040, US-COPI-100 + 1) pro novo cycle, MCP retornou `🔄 0 tasks rolaram pro cycle CYCLE-06`. Causa: campo `mcp_tasks.cycle_id` era NULL nessas tasks. Brief detecta drift por *commits/PRs em path*, não por tasks linkadas a cycle — então pode reportar "100% drift" enquanto nenhuma task formalmente pertence ao cycle.

**Why:** 2026-05-14, durante pivot CYCLE-05 → CYCLE-06. Brief #51 dizia "drift 100% (18/18 PRs fora do cycle ativo)". Tentei rollover assumindo que as 30 tasks ativas do meu work estavam linkadas ao CYCLE-05. Não estavam. Drift era duplo:
1. **Nominal** (rótulo do cycle erradoado) — sabíamos
2. **Estrutural** (tasks órfãs de cycle, `cycle_id IS NULL`) — descoberto na execução

**How to apply:**
- Ao fechar cycle, ANTES de `cycles-close --rollover_to`, validar com SQL/tinker quantas tasks têm `cycle_id = <id_velho>`:
  ```sql
  SELECT status, COUNT(*) FROM mcp_tasks WHERE cycle_id = 6 GROUP BY status;
  ```
- Se `cycle_id IS NULL` em massa, o rollover não funciona — precisa UPDATE em batch ANTES do close
- Quando criar task nova, sempre passar `cycle:CYCLE-XX` no `tasks-create` pra evitar órfã (gap na ferramenta atual — `tasks-create` não tem `cycle` parameter visível)
- Brief detectar drift sem tasks linkadas é anti-pattern — abrir issue pra futuro Brief também considerar `cycle_id IS NULL` na contagem de drift

**Não confundir com:**
- Drift nominal (rótulo do cycle vs trabalho real) — esse é resolvido por cycles-close + cycles-create novo
- Drift estrutural — exige UPDATE de cycle_id em massa, hoje só via SQL direto / tinker

**Refs:** ADR 0130 (handoff append-only) · CYCLE-06 (pivot 2026-05-14) · `mcp__Oimpresso_MCP___Wagner__cycles-close`
