# SPEC — DemoDoneness (fixture RUIM do gate-selftest GT-G6 · catraca doneness-lint · ADR 0298)

### US-DD-001 · "diz pronto, zero prova" (status=done SEM âncora)

> owner: claude · priority: p1 · estimate: 1h · status: done · type: story

status=done mas NENHUMA linha `**Implementado em:**` → âncora não-viva (`sem_campo`) →
`conflito_done_sem_ancora`. `doneness-lint --check` DEVE sair 1 e imprimir a acusação
"⚠️ US-DD-001 … → conflito_done_sem_ancora". Se passar (exit 0), a catraca parou de morder.

### US-DD-002 · "diz a-fazer, código existe" (status=aberto + âncora viva)

> owner: claude · priority: p1 · estimate: 2h · status: todo · type: story

**Implementado em:** `Modules/DemoDoneness/Real.md` · verificado@abc1234 (2026-06-22)

status=todo mas o path existe (âncora viva) → `conflito_aberto_com_ancora`. Cobre o 2º
braço da catraca no mesmo arquivo. Fictício — zero PII.
