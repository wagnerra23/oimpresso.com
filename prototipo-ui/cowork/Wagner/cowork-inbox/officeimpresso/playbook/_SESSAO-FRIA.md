# Sessão fria — Officeimpresso

> 1 thread = 1 sessão = 1 PR. [CC] 2026-09-30 (sha 89f32db43080). **O índice manda.**

```
/onda officeimpresso --thread NN
```

Leia só: placar (se não for `proximo`, pare) · o `NN-*.md` · o objeto da thread no json · o "lido no turno", relido no `main`. Termine com `_saida-NN.md` e PARE.

| # | o que faz | dono | ficha | depende de |
|---|---|---|---|---|
| **01** | L1+L7 escopo na API | CL | `01-seguranca-api.md` | — |
| **02** | L2 parar de gravar senha | CL | `02-segredo-desktop.md` | — |
| **03** | Dropar colunas | CL | `02-segredo-desktop.md` | 02 · D4 |
| **04** | Estado fora de GET | CL | `03-get.md` | — |
| **05** | Painel OAuth duplicado | CL | `04-client-duplicado.md` | D1 |
| **A1** | Alvos | CL | `05-alvos.md` | D2 |
| **06** | Tela de licenças (2 PRs) | CL | `06-licencas.md` | 01 · 02 · 04 · A1 |
| **07** | Logs | CL | `07-logs.md` | A1 |
| **08** | Catálogo | CL | `08-catalogo.md` | D3 |
