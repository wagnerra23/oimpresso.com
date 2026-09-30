# Sessão fria — Atendimento

> 1 thread = 1 sessão nova = 1 PR. Gerado pelo [CC] em 2026-09-30 (sha ca44a3d54cd2). **O índice manda.**

```
/onda atendimento --thread NN
```

Leia só: placar (se não for `proximo`, pare) · o `NN-*.md` · o objeto da thread no json · o "lido no turno", relido no `main`. Termine com `_saida-NN.md` e PARE.

| # | o que faz | dono | ficha | depende de |
|---|---|---|---|---|
| **00** | PUXAR Caixa Unificada | CC | `01-puxar-vivo.md` | — |
| **01** | Render órfão /atendimento/inbox | CL | `02-inbox-orfao.md` | D1 |
| **02** | Casos: Channels | CL | `03-casos.md` | — |
| **03** | Casos: Macros + JanaTemplates | CL | `03-casos.md` | — |
| **04** | Casos: Csat, Métricas, Settings, Templates | CL | `03-casos.md` | — |
| **A1** | ALVO Caixa Unificada | CL | `04-alvo-contrato.md` | 00 |
| **05** | Contrato Caixa Unificada | CL | `04-alvo-contrato.md` | A1 |
