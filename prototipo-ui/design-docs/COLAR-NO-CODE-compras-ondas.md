# Compras (`Modules/Compras` + core `/purchases`) — ponte do módulo · reescrita 2026-09-08

> **Este arquivo virou ponteiro.** O pacote de 10 blocos que morava aqui (04/09) foi **medido contra a árvore `9101f86af501` em 08/09 e reprovou**: dos 8 arquivos que ele pedia, **6 já existiam no `main`**. Manter o texto aqui é cache que envelhece (L-42) — a versão medida vive no playbook.
> **Dono do módulo agora:** `cowork-inbox/compras/playbook/00-INDICE.md` (`SINCRONIZAR Compras`) — 5 threads, **2 executáveis**, 3 travadas em decisão [W]. Destino no `main`: `prototipo-ui/design-docs/cowork-inbox/compras/playbook/`.
> **Constituição:** `CONSTITUICAO-COWORK.md` (C1–C13) + `memory/proibicoes.md` — citadas, não copiadas. As 7 leis específicas do Compras estão no `§0` do índice do playbook.

## Por que o pacote de 04/09 morreu (medido em 08/09, árvore `9101f86af501`)
- `Purchase/{Index,Create,Edit,Show}.casos.md` — **os 4 existem** (28.167 · 22.643 · 19.415 · 24.739 B). O doc mandava criar.
- `contrato/{compras-cockpit,purchase-create}.contract.json` — **os 2 existem** (3.946 · 4.272 B).
- `GradeMatrixInput` — **plugado**: `Purchase/Create.tsx:26` importa, `:459` usa. Deixou de ser "declarado pelo charter".
- Sobrou **1 lacuna de código real**: `e2e/` tem 17 specs e **nenhum** de compras/purchase.
- Apareceu **1 defeito meu**: a coluna **Margem** no drawer do protótipo — busca `margem|margin|lucro` em `Modules/Compras/` = **0 ocorrências**. Número sem fonte (C7).

## Threads (estado derivado — quem manda é o placar, não este arquivo)
| # | thread | dono | estado |
|---|---|---|---|
| 01 | Rede: `e2e/compras-cockpit.spec.ts` + `e2e/purchase-create.spec.ts` | [CL] | **próximo** |
| 02 | Coluna Margem sem fonte (build do Cowork) | [CC] | **próximo** |
| 03 | Fornecedores — aba sem receptor | [W] | bloqueada (D-FORN) |
| 04 | Ghost `/compras/create` — conflito de canon | [W] | bloqueada (D-GHOST) |
| 05 | Smoke/canary da grade tam×cor | [W2] | bloqueada (D-GRADE) |

`node scripts/qa/placar-indice.mjs --indice prototipo-ui/design-docs/cowork-inbox/compras/playbook/00-INDICE.md --root . --proximo`

## RESÍDUO Compras — fila [W] (preservada)
1. **Ghost `/compras/create`: remove (1 arquivo) ou cria (6, contra o Non-Goal C1 do charter)?**
2. **Fornecedores é tela?** (`contacts type=supplier`; não existe `Pages/Fornecedor*`) — view de contatos · tela própria (5 arquivos) · Non-Goal escrito.
3. **Smoke/canary da grade tam×cor** (US-COM-005, biz=4 Larissa) — gate [W2], nenhum arquivo destrava.
4. **Alvo de toque em 1280 denso** — 24×24 WCAG ou exceção declarada (pendente também em CRM, Repair, HRM, Ponto, Patrimônio: uma resposta serve pro ERP todo).
5. **Grade do DS** (`th` sem `scope`, `TH` ordenável sem semântica) — 5º módulo com o achado; o `SortTh` do Compras é a referência certa a portar.
