---
date: "2026-06-20"
time: "2302 BRT"
slug: "spec-schema-gate-e-aposentar-governance"
tldr: "Consertei o gate CI 'SPEC schema' (frontmatter sem aspas): version/last_updated quoted → 3 PRs verdes em main — #3095 (Sells/Fiscal/Ponto/OficinaAuto), #3097 (Dashboard: status live->ativo + related_adrs slugs), #3104 (salva Inventory/SPEC.md). Depois auditei feat/governance-ds-rollout-ledger ([W]: 'está quebrado'): shallow escondia que está 508 commits ATRÁS com só 24 próprios — código + DS Rollout + os 2 commits locais já estão TODOS em main. Decisão [W]: aposentar."
decided_by: [W]
cycle: "CYCLE-08"
prs: [3095, 3097, 3104]
next_steps:
  - "[W] rodar os passos de deleção do feat/governance-ds-rollout-ledger quando as sessões paralelas no worktree D:/oimpresso.com estiverem ociosas (git switch main → branch -D → push origin --delete)"
  - "Acompanhar merge do #3104 (salvage Inventory SPEC) — SPEC gates já verdes"
related_adrs: ["0130-handoff-append-only-mcp-first"]
---

# Handoff 2026-06-20 23:02 BRT — Gate SPEC schema consertado + auditoria/aposentadoria do branch governança

## TL;DR
Tarefa inicial: o gate CI **"SPEC (memory/requisitos/*/SPEC.md)"** (advisory) falhava recorrente porque frontmatter de SPEC tinha `version`/`last_updated` sem aspas (o `gray-matter`/js-yaml parseia data crua como `Date` → viola `type: string`). Consertei e, ao varrer, achei mais dívida latente. Depois [W] perguntou o que fazer do branch `feat/governance-ds-rollout-ledger` ("está quebrado") → auditoria completa → decisão de **aposentar**.

## Estado MCP no momento do fechamento
MCP server **oimpresso indisponível** nesta sessão (`cycles-active`/`my-work` retornaram "Server unavailable"). Estado vivo não capturado via MCP — usar `brief-fetch` ao retomar. Off-cycle assumido CYCLE-08 (último conhecido).

## O que aconteceu
1. **Fix do gate (raiz):** só `last_updated` com data ISO crua quebra de fato (version `X.Y.Z` com 2 pontos já parseia como string). Verifiquei replicando a stack EXATA do CI (`gray-matter@4` + `ajv/dist/2020@8` + `ajv-formats@3`) num harness temp — `php artisan jana:validate-memory` estava inutilizável (autoload do vendor quebrado, aponta `oimpresso-tmm-grade` ausente).
2. **PRs (todos verdes, off-main):**
   - **#3095** — quota version/last_updated em Sells/Fiscal/Ponto/OficinaAuto.
   - **#3097** — Dashboard tinha 2 violações pré-existentes ALÉM da data: `status: live` (fora do enum) e `related_adrs` como inteiros → status→ativo + slugs canônicos (0101 resolvido como `0101-tests-business-id-1-nunca-cliente`, [W]-confirmado).
   - **#3104** — salva `Inventory/SPEC.md` (discovery Estoque) pra main antes de aposentar o branch; já schema-clean (`modulo`→`module`, +version/+last_updated, proposed→rascunho, heading `## 7. User Stories` pro gate de seções). Substituiu o **#3099** (que mirava o branch governança — fechado por moot).
3. **Auditoria do branch governança:** clone era **shallow** (escondia merge-base). Após `git fetch --deepen=1000`: o branch está **508 commits ATRÁS da main** com só **24 próprios**. `git cherry` + apply-check + verificação por arquivo provaram que **TODO o código já está em main** (6 commits "shippáveis" idênticos/superados; DS Rollout via #2621; `.claude/` 0 únicos; os 2 commits locais não-pushados de hoje — sentinelas honestas + SentinelBiteTest — também já em main). Único exclusivo c/ futuro = Inventory SPEC (salvo). Decisão [W]: **aposentar**.

## Artefatos gerados
- 3 PRs em main: #3095, #3097, #3104 (frontmatter/docs, ~todos ≤570 linhas, SPEC gates verdes).
- #3099 criado+fechado (moot).
- Passos de deleção do branch entregues a [W] (não executados — checkout vivo + sessões paralelas).

## Persistência
- **git:** este handoff + índice via PR off-main (webhook GitHub→MCP propaga ~2min).
- **MCP:** indisponível nesta sessão.
- **BRIEFING:** não aplicável (sem módulo de produto tocado — só memory/SPEC docs).

## Próximos passos pra retomar
`gh pr view 3104` (confirmar merge) · depois [W] roda a deleção do branch governança (passos no corpo da conversa) quando paralelas ociosas.

## Lições catalogadas
- **Tocar um arquivo num PR re-valida o frontmatter INTEIRO dele** — quotar só a data do Dashboard teria acendido status-enum + related_adrs. Sempre validar o objeto todo antes de incluir um arquivo "só pra um fix".
- **Clone shallow falseia divergência** — `merge-base` exit 1 + counts inflados (335/234) eram artefato; `--deepen` revelou 24-à-frente/508-atrás. Nunca diagnosticar branch em shallow sem deepen.
- **`git cherry` (patch-id) + apply-check + grep-no-main** = tripé pra provar "já está em main" antes de re-shipar/deletar. Evitou 6 PRs redundantes.
- **`git worktree add -b X origin/<branch>`** seta upstream pro branch remoto de origem → `git push` cairia nele. Desarmar com `git branch --unset-upstream` + push com refspec explícito.

## Pointers detalhados
Conversa desta sessão (raciocínio do audit, comandos git cherry/apply-check, passos de deleção). Branch a deletar: `feat/governance-ds-rollout-ledger` (origin `2ecdd38cf`).
