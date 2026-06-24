---
slug: selftest-doneness-baseline
status: ativo
---

# SPEC — SelftestDonenessBaseline (fixture ARMING grandfather doneness · ADR 0302/0275)

## US-SLDB-001 · status=done SEM âncora viva (conflito_done_sem_ancora)

> owner: claude · priority: p1 · estimate: 1h · status: done · type: story

> Fixture: a US se diz pronta (`status: done`) mas NÃO tem linha `**Implementado em:**` →
> âncora não-viva (`sem_campo`) → `conflito_done_sem_ancora`. O veredito depende do
> `--baseline`: GOOD grandfathera `conflito_done_sem_ancora:US-SLDB-001` → exit 0.
> (BAD grandfathera só o decoy US-SLDB-999, então o conflito é NOVO e MORDE → exit 1.)
> Prova o no-new-lie: mesma SPEC/conflito; só o baseline muda. Fictício — zero PII.
