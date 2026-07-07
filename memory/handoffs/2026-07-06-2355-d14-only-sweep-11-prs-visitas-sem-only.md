# Handoff 2026-07-06 23:55 — Sweep D-14 (visitas Inertia sem `only:`) — 11 PRs + 1 draft

## O que foi feito
Sweep repo-wide da **classe irmã** do defer-sweep de manhã: `router.get/visit/reload` de navegação-de-estado (filtro/sort/paginação/aba) **sem `only:`** → full reload. Fix = padrão do [PR #3889](https://github.com/wagnerra23/oimpresso.com/pull/3889) (`only:` no frontend + closures por-business no controller). 85 ofensores → **74 arquivos**, **11 PRs por módulo** (#3894–#3904) + **1 draft** (#3906 Board).

Detalhe completo: [session log 2026-07-06-d14-only-sweep-visitas-sem-only.md](../sessions/2026-07-06-d14-only-sweep-visitas-sem-only.md).

## PRs abertos (base main)
- **Mergeáveis quando verdes** (Wagner aprova — R10): #3894 Financeiro · #3895 Ponto · #3896 ProjectMgmt · #3897 Jana · #3898 Essentials · #3899 Atendimento+Whatsapp · #3900 OficinaAuto/Vehicles · #3901 Repair · #3902 MemCofre+Nfse · #3903 core/admin.
- **⚠️ #3904 VALOR/ESTOQUE — NÃO MERGEAR sem dupla confirmação Wagner** (Regra Mestre): Compras, Purchase, Sells/Caixa, Stock×2, Payments — só listagens, tabela de impacto no body provando zero mudança de cálculo.
- **⚠️ #3906 Board (DRAFT)**: precisa re-rodar e2e no CT100 (G-7 stale-results, tela Tier-0 live-prod Martinho biz=164) antes de sair de draft.

## Próximos passos
1. **Aguardar CI** (`gh pr checks <PR>`) — corrigidos em sessão: casos G-6 (bump last_run ContasPagar/Receber no #3894) + Vite build (JSX comment em Activity no #3896).
2. **Wagner aprova merge** dos 10 mergeáveis (após verde).
3. **#3904**: apresentar tabela antes→depois; só mergear com OK explícito.
4. **#3906 Board**: `npm run e2e:check` + `npm run casos:results` no CT100, commitar manifesto, tirar de draft.
5. **Prova runtime (R1/Regra 0)** pós-deploy: 1 filtro por tela no Chrome → `X-Inertia-Partial-Data` + marker vivo.
6. **Gap reportado (não corrigido — Tier 0 valor):** Home filtro de loja é no-op server-side na versão Inertia (`getSellTotals` sem `$location_id`) — só `?legacy=1` passa o param. Precisa de dupla confirmação pra corrigir (muda valor exibido).

## Estado MCP no momento do fechamento
**MCP indisponível nesta sessão** — `brief-fetch` falhou no SessionStart (curl exit 28, fallback ativado). `cycles-active`/`my-work`/`sessions-recent`/`decisions-search` não consultáveis. Fonte = git `origin/main` fresco (worktree `claude/d14-only-sweep` @ base 6f03503d90). Quando MCP voltar: registrar as tasks deste sweep via `tasks-*` e cruzar com cycle ativo.
