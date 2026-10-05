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
| **03** | Dropar colunas | CL | `03-segredo-desktop.md` | 02 · D4 |
| **04** | Estado fora de GET | CL | `04-get.md` | — |
| **05** | Painel OAuth duplicado | CL | `05-client-duplicado.md` | D1 |
| **A1** | Alvos | CL | `A1-alvos.md` | D2 |
| **A3** | Remedir Logs (saiu da A1) | CL | `A3-remedir-logs.md` | — |
| **06** | Tela de licenças (2 PRs) | CL | `06-licencas.md` | 01 · 02 · 04 · A1 |
| **07** | Logs | CL | `07-logs.md` | A3 |
| **08** | Catálogo | CL | `08-catalogo.md` | D3 |
| **09** | Ligar a tela nova de licenças (flag useV2OfficeimpressoLicencas) | CL | `09-ligar-flag.md` | 06 · D6 |
