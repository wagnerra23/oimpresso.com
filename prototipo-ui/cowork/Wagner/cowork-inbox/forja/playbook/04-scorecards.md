---
sessao: "04"
titulo: Scorecards — 4 telas sem nota e 7 órfãos
dono: "[CL]"
base: 836619f64d24
---
# 04 · scorecards

Sem nota: `team-mcp/{CcSessions,Scorecard,Tasks,Team}/Index` (slug `team-mcp-<tela>-index`, regra `screenSlug` do `screen-coverage-map.mjs:303`). Órfãos: `forja-{activity,backlog,board,burndown,inbox,mywork,triage}-index.yaml` declaram `screen: Forja/<X>/Index` e a Page não existe. Antes de apagar os órfãos, conferir se `screen-grades-ratchet` os usa como piso; se usar, o recibo diz o número e a remoção espera.

## Prova
No JSON do `00-INDICE.md` (só `forja-triage` entra como `ausente`; os outros 6 vão no recibo). Recibo: `_saida-04.md`.
