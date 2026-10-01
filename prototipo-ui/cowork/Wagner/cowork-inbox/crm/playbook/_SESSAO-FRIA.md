# Sessão fria — Crm

> 1 thread = 1 sessão = 1 PR. [CC] 2026-09-30 (sha 89f32db43080). **O índice manda.**

```
/onda crm --thread NN
```

Leia só: placar (se não for `proximo`, pare) · o `NN-*.md` · o objeto da thread no json · o "lido no turno", relido no `main`. Termine com `_saida-NN.md` e PARE.

| # | o que faz | dono | ficha | depende de |
|---|---|---|---|---|
| **A1** | Alvos lote 1 | CL | `01-alvos.md` | — |
| **01** | Trio + contratos lote 1 | CL | `02-trio-lote1.md` | A1 · D1 · D4 |
| **02** | Leads | CL | `03-leads.md` | 01 · D2 |
| **03** | Acompanhamentos | CL | `04-acompanhamentos.md` | 01 |
| **04** | Painel | CL | `05-painel.md` | 01 |
| **05** | Lote 2 (volta pro Cowork) | CC | `06-lote2.md` | 02 |
