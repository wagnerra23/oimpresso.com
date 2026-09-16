---
date: "2026-09-16"
time: "14:45 UTC"
slug: token-pares-revogacao-e-o-dia-de-medir-errado
tldr: "Fechei os 3 next_steps do handoff de 11:00 em 6 PRs. O achado que mais rende nao e nenhum deles: `tasks-create` NAO cria nada duravel por desenho desde ontem (US-COPI-149 done), entao 'criar task MCP' virou US no SPEC, pelo git. E os 24 settings.local.json 'com o token literal' carregavam um token REVOGADO — nao era credencial viva. Errei 5x no dia, todas a mesma familia: medir a coisa errada e quase publicar."
prs: [7395, 7400, 7412, 7415, 7417, 7423]
decided_by: [W]
related_adrs:
  - 0057-tela-team-admin-regras-governanca-tokens-mcp
next_steps:
  - "AVISAR O LUIZ — e a unica coisa aberta, e e do [W]. O token #22 dele foi revogado; o sintoma aparece no primeiro SessionStart (banner de fallback do brief + tools mcp__oimpresso__* ausentes). Emitir: `php artisan mcp:token:gerar --user=569 --name=\"DXT — Luiz (16/09/2026)\"`. A US-INFRA-053 ja explica o resto pra ele"
  - "O `.claude/skills/oimpresso-cc-watcher-setup/SKILL.md:468` ainda cita o comando fantasma `copiloto:mcp:gerar-token` — corrigi so o MEMORY_TEAM_ONBOARDING (que estava no caminho critico); a skill ficou, e a ADR 0055:154 e checklist `- [ ]` honesto, nao precisa mexer"
  - "US-FORJA-011 (redacao no ingest) esta `proposto` e o `cc-watcher` segue desligado ate ela existir — o `.jsonl` do incidente da LC-35 continua no backlog do watcher, entao religar antes e ingerir o segredo que a contencao evitou"
  - "MCP indisponivel a sessao INTEIRA: `OIMPRESSO_MCP_TOKEN` ausente do ambiente. Os settings.local.json ja estao na forma de referencia; falta so exportar a variavel (nao ha US-INFRA pro [W], so pros 4 devs — 049..052)"
---

# 2026-09-16 14:45 UTC — Os pares, a revogacao, e o dia em que medi errado cinco vezes

## TL;DR

Os 3 `next_steps` do handoff de 11:00 fecharam, em 6 PRs. Mas o que vale guardar sao **dois
achados que mudaram o enquadramento do pedido** e **uma classe de erro que se repetiu o dia todo**.

## Estado MCP no momento do fechamento

⚠️ **O checklist MCP-first da [ADR 0130](../decisions/0130-handoff-append-only-mcp-first.md) NAO
pode ser rodado — declaracao, nao omissao.** `ToolSearch` por tools `oimpresso` devolve zero, com
`AUTH_HEADER_REJECTED` (401). Causa **medida**, e e consequencia direta do trabalho de ontem: os
`settings.local.json` agora carregam `Bearer ${OIMPRESSO_MCP_TOKEN}` e a variavel **esta ausente do
ambiente desta sessao** — fail-closed operando como desenhado. Fallback filesystem usado: `Glob`
dos handoffs irmaos (uma outra sessao fechou hoje 13:30, `gt-g5-duas-familias-gate-e-lapide`) +
`git log` das ADRs (0401, 0402, 0403 aceitas desde ontem).

## Os dois achados que mudaram o pedido

**1. "Cria a task MCP" nao era caminho — e nao por causa do token.** A **US-COPI-149 esta `done`**
desde ontem, e o conserto dela foi *tirar* a escrita: `createCanonical()` nao escreve mais no SPEC
(`written` sempre `false`). Ou seja, **mesmo com MCP conectado, `tasks-create` nao cria nada
duravel** — ele gera id e texto; quem persiste e o git. O precedente esta na propria US: as 4 tasks
de ontem *"acabaram escritas a mao no SPEC de Infra"*. O `next_step` do handoff anterior atribuia o
bloqueio ao MCP estar fora; isso estava **incompleto**.

**2. Os 24 `settings.local.json` "com o token literal" carregavam um token MORTO.** Identifiquei por
`sha256_token` (como o servidor identifica, sem expor valor): os 24 tinham **um unico** token, o
**#4**, revogado E soft-deleted em 2026-09-15 19:01:12 — o instante exato da rotacao #4 -> #30. Nao
era credencial viva em disco. Migrei os 24 para a forma de referencia (cirurgico: so o valor do
`Authorization`; `permissions` preservado em 24/24), e o residuo foi a **zero**.

## Erros meus — 5, todos a mesma familia

Nenhum instrumento avisou; todos devolveram numero plausivel. O que pegou foi rodar de novo
perguntando outra coisa.

| # | o que medi errado | chegou ao [W]? |
|---|---|---|
| 1 | bite-test contra a **arvore errada** (branch 3 commits atras, onde a ADR nem existia) | nao |
| 2 | **janela lida como recencia** — `30d=188` eram todas de UM dia, 20 dias atras | **sim** (alarme falso) |
| 3 | detector de `${ENV}` medindo **presenca** (`process.env` em qualquer lugar) — 3 versoes distintas do hook, so 1 expande | nao |
| 4 | `grep -P` sem suporte no locale fabricou lista de "47 required sem conclusao" | nao |
| 5 | disse **"7 PRs"** no relatorio final; contados, sao **6** | **sim** |

O #2 virou o `rec` n+20 da LC-08 ([#7417](https://github.com/wagnerra23/oimpresso.com/pull/7417)) —
**sem lapide nova**, porque a dona do limite ja existe (§5 2026-08-13, item (d)) e escrever outra
duplicaria regua consolidada.

## O que foi para o main

| PR | o que |
|---|---|
| [#7395](https://github.com/wagnerra23/oimpresso.com/pull/7395) | o PR que a sessao foi aberta pra abrir — ledger `rec` n+19 + errata LC-35 + lapide §5 + handoff de 11:00 |
| [#7400](https://github.com/wagnerra23/oimpresso.com/pull/7400) | **US-FORJA-011** — redacao de segredo na fronteira de ingest (`/api/cc/ingest`) |
| [#7412](https://github.com/wagnerra23/oimpresso.com/pull/7412) | decisao dos pares de token: **concorrentes, nenhum se revoga** |
| [#7415](https://github.com/wagnerra23/oimpresso.com/pull/7415) | registro da revogacao do #22/#23 + como ler os campos sem errar |
| [#7417](https://github.com/wagnerra23/oimpresso.com/pull/7417) | ledger `rec` n+20 |
| [#7423](https://github.com/wagnerra23/oimpresso.com/pull/7423) | **US-INFRA-053** (aviso ao Luiz) + comando fantasma corrigido |

Fora do git: **9 -> 7 tokens vivos** (#22 e #23 revogados, `revoked_by=1`) e **24 -> 0** copias do
token literal em disco nesta maquina.

## A decisao dos pares, em uma linha

**#10 vs #30 (Wagner) e #11 vs #21 (Maiara) sao clientes CONCORRENTES — nenhum se revoga.** O
discriminador **nao** e IP (o tag dominante cobre 11 de 16 tokens, rede compartilhada) nem
`user_agent` (`node` nos quatro): e o **`endpoint` do `mcp_audit_log`** — handshake-only
(`initialize`/`tools/list`, zero `tools/call`) = Claude Desktop; quem emite `tools/call` trabalha. A
armadilha esta no par da Maiara: **o token util e o MAIS VELHO**, entao "revoga o antigo" mataria
quem trabalha.

## O #7400 travou 2x por vermelho HERDADO

`main` esteve com a ADR 0401 `proposto` citada por codigo que roda -> `memory-health` (required)
vermelho na **arvore inteira**, bloqueando todo PR aberto (o #7406 que consertou diz "destrava 6
PRs"). Medido: `origin/main` puro dava `1 🔴 fail`. **E o re-run nao resolve** — ele replica o
payload do evento original e nao recompoe a base; so `git merge origin/main` resolveu. Custo
declarado: **117 -> 84 lanes**, as 33 advisory de `opened` nao re-rodam.

## Proximos passos

No frontmatter. O unico que e do [W] e o primeiro: **avisar o Luiz**.

## Pointers

- Session log desta sessao: [`memory/sessions/2026-09-16-token-pares-revogacao.md`](../sessions/2026-09-16-token-pares-revogacao.md)
- Handoff anterior: [`2026-09-16-1100`](2026-09-16-1100-poda-worktrees-e-token-fora-do-disco.md)
- Inventario de token: [`memory/_INDEX-SECRETS.md`](../_INDEX-SECRETS.md) (linha do token MCP)
