---
sessao: "01"
titulo: casos.md das 3 telas team-mcp sem trio
dono: "[CL]"
base: 836619f64d24
---
# 01 · casos team-mcp

`CcSessions/Index`, `Tasks/Index` e `Team/Index` têm `.tsx` + `.charter.md` e não têm `.casos.md` (`screen-coverage-map --screen` → "trio completo: ✗ INCOMPLETO"). 1 PR por tela (big-bang de casos é proibido pelo G-2). Executor: agent `sdd-from-source <Mod/Tela>` — UC derivado do SDD/charter, nunca do `.tsx`; cada UC citado por ≥1 teste em `Modules/Forja/Tests/Feature/`.

## Prova
No JSON do `00-INDICE.md`. Recibo: `_saida-01.md`.
