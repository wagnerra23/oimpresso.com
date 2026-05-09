# Protótipo F1 — financeiro-unificado

**Status:** 🟡 PINO HISTÓRICO — não usar como base de F3.
**Aprovado por:** [W] 2026-05-09 (Cowork)
**Stories:** US-FIN-013, US-FIN-020 (visao-unificada-cockpit-v2)

## Por que está parado

A tela `/financeiro/unificado` **JÁ ESTÁ EM PROD** com 3 bugs corrigidos pós-protótipo (PRs [#355](https://github.com/wagnerra23/oimpresso.com/pull/355), [#358](https://github.com/wagnerra23/oimpresso.com/pull/358)):

- header com `businessName` real da session (não hardcoded "ROTA LIVRE")
- período PT-BR via Carbon `isoFormat('MMMM YYYY')`
- 3 outros fixes operacionais Wagner em prod

O protótipo Cowork foi feito em cima da versão antiga, antes desses fixes. **Sobrescrever destrói trabalho.**

## O que esse pino serve pra

Histórico visual / referência futura — se quiser comparar Cockpit V2 do protótipo com a tela atual em prod, está aqui.

## Próximo passo realista

Refator visual incremental (não sobrescrita) via PR específico, se Wagner achar que vale. Nada urgente — tela já aprovada e em uso.

Ver `prototipo-ui/HANDOFF.md` pra prioridade do batch.
