# Sessão fria — Estoque (Movimentações)

> 1 thread = 1 sessão = 1 PR. [CC] 2026-10-05 (sha aacb74f4df18). **O índice manda.**

```
/onda estoque --thread NN
```

| # | o que faz | dono | ficha | depende de |
|---|---|---|---|---|
| **00** | PUXAR as 4 Pages vivas → estoque-page (uma rota est-* por Page) | CC | `00-puxar-vivo.md` | — |
| **01** | Charters completos + casos das 4 Pages | CL | `01-trio.md` | — |
| **A1** | ALVO estoque--ajustes--index · estoque--transferencias--index | CL | `A1-alvos.md` | 00 |
| **02** | Contratos estoque-ajustes · estoque-transferencias (derivados do alvo) | CL | `02-contratos.md` | A1 · 01 |
