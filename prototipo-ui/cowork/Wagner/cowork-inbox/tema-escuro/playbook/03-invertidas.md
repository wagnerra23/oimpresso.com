---
sessao: "03"
titulo: Placas invertidas em produção
dono: "[CL]"
base: e22d2f85bcf4
---
# 03 · Corrigir em produção (2 PRs)

Só com D1. Padrão invertido = `background: var(--text)` + `color: var(--bg|--surface)`. No protótipo (05/10) virou `color-mix(in oklch, var(--accent) 14%, var(--surface))` com borda de accent 35%. **Não criar token novo** sem ADR. PR-a: Vendas (CreateV3 + Index). PR-b: o resto da lista da 02.
