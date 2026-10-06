# Sessão fria — Tema escuro

> 1 thread = 1 sessão = 1 PR. [CC] 2026-10-05 (sha e22d2f85bcf4). **O índice manda.**

```
/onda tema-escuro --thread NN
```

| # | o que faz | dono | ficha | depende de |
|---|---|---|---|---|
| **01** | Sonda no espelho, 194 rotas (required + baseline) | CL | `01-sonda.md` | — |
| **02** | Mesma sonda em produção | CL | `02-producao.md` | 01 |
| **03** | Corrigir invertidas em produção (2 PRs) | CL | `03-invertidas.md` | 02 |
| **04** | Triagem no protótipo | CC | `04-triagem-prototipo.md` | 01 |
