# Sessão fria — Connector

> 1 thread = 1 sessão = 1 PR. [CC] 2026-09-30 (sha 2fc50fa6dcb8). **O índice manda.** Restrição dura: nada que quebre o WR Comercial em campo.

```
/onda connector --thread NN
```

Leia só: placar · o `NN-*.md` · o objeto da thread · o "lido no turno", relido no `main`. Termine com `_saida-NN.md` e PARE.

| # | o que faz | ficha | depende de |
|---|---|---|---|
| **01** | Prova mínima | `01-prova.md` | — |
| **02** | Excluir revoga + install fora de GET | `02-seguranca.md` | 01 |
| **03** | Ninguém lê o segredo | `03-segredo.md` | 01 |
| **04** | Blade → Inertia + contrato (2 PRs) | `04-traducao.md` | 02 · 03 |
| **05** | Menu, /regenerate, connector.access | `05-menu.md` | 04 |
| **06** | Apagar legado | `06-legado.md` | 05 · [W2] |
| **07** | Quem usa a credencial | `07-quem-usa.md` | 04 |
| **08** | Saúde com histórico | `08-saude.md` | 04 |
| **09** | Aba Documentação (PR-b da 04) | CL | `09-documentacao.md` | 04 |
| **10** | Rodar UC-CONN-12 na lane MySQL (pulado em SQLite) | CL | `10-mysql.md` | 02 |
