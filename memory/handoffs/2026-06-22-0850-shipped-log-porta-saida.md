---
date: "2026-06-22"
hour: "08:50 BRT"
topic: "Shipped-log: porta de saída do loop automatizada (espinha 5-gaps)"
duration: "~2h"
authors: [Wagner, Claude Code]
---

# Shipped-log — porta de saída do loop, automatizada

## Estado MCP no momento
- **CYCLE-08** (Receita — Onda A) · 6d restantes · **esta sessão foi off-cycle** (governança/processo, igual handoffs recentes).
- `my-work`: 30 tasks (7 review, 8 blocked, 15 todo) — **nenhuma** desta sessão.
- HITL [W] pendentes: runbook on-prem pós-Gold · FIN-004 cobrança ROTA LIVRE.

## O que aconteceu
Wagner: *"quais PR não viraram roadmap?"* → auditoria de completude mostrou que a **porta de saída do loop não existia** (gerador `shipped-log-generate.mjs` estava **no disco, untracked, nunca commitado** e **reprovado** pela red-team 2026-06-21). Wagner: *"testar o sistema, ver se não perdi nada"* → medi a verdade por fonte completa: **1.073 PRs base=main** na janela do cycle (gerador v1 **abortava** no teto de 1000 da Search API), **33 push-direto** invisíveis a query de PR (incl. produto: ContactAddress/multi-tenant/oficina/cockpit), **revert** `#2104↔#2107` (merge≠entrega), tudo com **cross-check batendo no total_count**. Gerei o registro honesto **`CYCLE-08.md` ([#3185](https://github.com/wagnerra23/oimpresso.com/pull/3185) MERGED)**. Wagner: *"não quero rodar à mão nem pedir de novo, e quais os gaps — não é só esse erro"* → mapeei **4 camadas / 19 gaps** e ele escolheu a **espinha completa**. Entreguei em 2 PRs + provei o cron end-to-end.

## Artefatos gerados
- **[#3185](https://github.com/wagnerra23/oimpresso.com/pull/3185) MERGED** — `memory/governance/shipped/CYCLE-08.md` (one-shot, ~1010 linhas, rótulo honesto "mergeado≠entregue").
- **[#3188](https://github.com/wagnerra23/oimpresso.com/pull/3188) MERGED** — `scripts/governance/shipped-log-generate.mjs` **v2 honesto** (REST sub-janela sem teto · API `/commits` push-direto · borda BRT · cross-check exit 1 · revert reconciliado · aliases/NFD · `--check` freshness) + `shipped-log-generate.test.mjs` (**19 fixtures-armadilha**).
- **[#3189](https://github.com/wagnerra23/oimpresso.com/pull/3189) MERGED** — `shipped-log-cron.yml` (auto-PR + auto-merge, lê cycle+since do próprio shipped-log) · `shipped-log-gate.yml` (`--check`, advisory, PR+diário) · step no `governance-script-tests.yml` · 2 entradas no `gates-registry.json`.
- **[#3191](https://github.com/wagnerra23/oimpresso.com/pull/3191) OPEN (auto-merge armado)** — regeneração do `CYCLE-08.md` **pelo cron** = prova end-to-end de que roda sozinho.

## Persistência
- **git**: 3 PRs mergeados no main; #3191 em auto-merge. Tudo via `gh api contents` (working tree é shallow/worktree órfão — não toquei).
- **MCP**: webhook GitHub→MCP propaga ~2min.
- **BRIEFING**: n/a (governança, não módulo de produto).

## Próximos passos pra retomar
1. Confirmar **#3191 mergeou** (loop fechado sem humano).
2. **Fase 2 declarada (não feita):** G8 = cruzar merge↔deploy real (registro provar produção) · linha `shipped-log-health` no Daily Brief (server-side MCP).
3. **Resíduo G6:** cron regenera o cycle do shipped-log *mais recente*; **cycle NOVO precisa de 1 `workflow_dispatch` com inputs** (cycle+since) até ligar a janela ao cycle ativo do MCP.
4. Atualizar a **proposta** `proposals/2026-06-21-fechar-loop-cycle-shipped-log.md` (Onda 1 + parte 2/3 **implementadas**).

## Lições catalogadas
- **Editar working tree SHALLOW regride arquivo canon** — editei `gates-registry.json` local (desatualizado vs main) e o PUT **apagou 4 entradas** que outros PRs tinham registrado. Conserto: **reconstruir do `main` via `gh api contents`**, nunca do disco shallow. [reforça licao-no-checkout-worktree + shallow]
- **O enforcement se defendeu sozinho** — `memory-health [G]` (workflow novo sem registro no censo) **bloqueou 2 deslizes meus** antes do merge. Sinal de que a rede de gates funciona.
- **Seed v1 sem `window:` quebra o cron na transição** — o cron lê cycle+since do frontmatter; o one-shot não emitia `window:`. 1º run precisou de inputs explícitos pra semear o formato v2.
- **Plumbing Win/MSYS**: `gh api` body grande → `--input arquivo.json` (arg-list-too-long); `node` no Windows usa `D:/` (não `/d/`); `node -e` quebra com aspas simples no conteúdo → usar arquivo `.mjs`; endpoints `gh api` sem leading slash.

## Pointers detalhados
- Proposta/red-team: `memory/decisions/proposals/2026-06-21-fechar-loop-cycle-shipped-log.md`
- Gerador + teste: `scripts/governance/shipped-log-generate.{mjs,test.mjs}`
- Registro: `memory/governance/shipped/CYCLE-08.md`
