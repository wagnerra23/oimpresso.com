---
sessao: "01c"
titulo: "kb/Index — contrato entregue depois de migrar o PageHeader para o canon"
autor: "[CL]"
data: 2026-10-13
thread: 01-contratos.md
veredito: "entregue — com o #8934 e o PR do _saida-01b, a thread 01 fecha 6 de 6 contratáveis (home coberta por dashboard-visao-geral)."
---
# _saida-01c · kb/Index

O `_saida-01b` deixou o `kb` de fora: a tela importava o `PageHeader` antigo e o required "PageHeader · ratchet" exige
migrá-lo no mesmo PR que tocar a tela (ADR 0409). [W] aprovou a migração em 2026-10-13 ("pode fazer, um PR por tela").

## Entregue
- `kb/Index.tsx` passa para `@/Components/PageHeader`, mapeamento do Repair (#8531) e do Estoque (#9158): `icon` → `leading`
  com `<Icon>`, `description` → `subtitle`. `config/pageheader-shared-baseline.json` desce de 51 para 50.
- Contrato `kb.contract.json` (alvo `soltas--kb--index`: `header` · `stats` · `tri`, sem copy comum), âncoras, RUNBOOK
  `memory/requisitos/KB/RUNBOOK-kb-index.md` e `last_run` do `casos.md` (G-6) — o mesmo conteúdo que saiu do #9259.

## Mudança visual (medida, harness com o CSS de produção e as mesmas props)
| | antes (shared) | depois (canon) |
|---|---|---|
| h1 | 24px · 600 | 22px · 600 |
| subtítulo | 14px | 12px |
| ícone | caixa 40×40 `bg-primary/10` | ícone inline 18px |
| altura do bloco | 97px | 87px |

Print antes→depois entregue ao [W] na sessão. Não é render da tela inteira: o staging roda o checkout do main e não
recebe branch.

## Provas
`pageheader-migration-guard` ✅ (nenhuma adoção nova, nenhuma dívida tocada) · `--contract` nos ativos 0 falhas ·
`--map --check` rc=0 (`kb/Index` 3/3 `🟢 portado`) · `--anti-tautologia` rc=0 · os dois `.test.mjs` rc=0 · mordida
(`--root`): sem `data-contract="tri"` rc=1 · `casos-coverage-guard` sem violação nova.
