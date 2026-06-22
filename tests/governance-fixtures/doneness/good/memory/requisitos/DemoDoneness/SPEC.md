# SPEC — DemoDoneness (fixture BOA do gate-selftest GT-G6 · catraca doneness-lint · ADR 0298)

### US-DD-001 · Done coerente (status=done + âncora viva)

> owner: claude · priority: p1 · estimate: 1h · status: done · type: story

**Implementado em:** `Modules/DemoDoneness/Real.md` · verificado@abc1234 (2026-06-22)

status=done E âncora viva (path existe no sandbox) → `consistente_done`. Sem contradição.

### US-DD-002 · Aberto sem âncora (zona-cinza, advisory)

> owner: claude · priority: p2 · estimate: 2h · status: todo · type: story

status=todo sem âncora → `zona_cinza_aberto_sem_anc`. É lacuna de cobertura (ADR 0273),
NÃO contradição → `--check` NÃO morde por ela. Prova que a catraca tolera a zona-cinza:
`--check` sai 0 e a tabela imprime "CONFLITOS (mordem em --check): 0". Fictício — zero PII.
