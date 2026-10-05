# Sessão fria — Crm

> 1 thread = 1 sessão = 1 PR. [CC] 2026-09-30 (sha 89f32db43080). **O índice manda.**

```
/onda crm --thread NN
```

Leia só: placar (se não for `proximo`, pare) · o `NN-*.md` · o objeto da thread no json · o "lido no turno", relido no `main`. Termine com `_saida-NN.md` e PARE.

| # | o que faz | dono | ficha | depende de |
|---|---|---|---|---|
| **A1** | Alvos lote 1 | CL | `A1-alvos.md` | — |
| **01** | Trio + contratos lote 1 | CL | `01-trio-lote1.md` | A1 · D1 · D4 |
| **02** | Leads | CL | `02-leads.md` | 01 · D2 |
| **03** | Acompanhamentos | CL | `03-acompanhamentos.md` | 01 |
| **04** | Painel | CL | `04-painel.md` | 01 |
| **05** | Lote 2 (volta pro Cowork) | CC | `05-lote2.md` | 02 |
| **06** | Leads: formulário (reusa Cliente/Create parametrizado — D2) | CL | `06-leads-form.md` | 02 |
| **07** | Acompanhamentos: escrita em Inertia (adicionar, recorrente, editar, log, excluir) | CL | `07-acompanhamentos-escrita.md` | 03 |
| **08** | Leads: show sem filtro type=lead + raiz do SELECT de colunas removidas (CrmUtil) | CL | `08-leads-escopo.md` | — |
