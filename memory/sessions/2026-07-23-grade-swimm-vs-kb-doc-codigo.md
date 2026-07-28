---
date: "2026-07-23"
topic: "Grade Swimm.io × KB doc↔código (re-pontuada pós A1–D) + entrega do trilho auto-document"
authors: [C, W]
outcomes:
  - "Trilho doc↔código do KB entregue A1–D (5 PRs: #4715/#4720/#4733/#4740 + limpeza #4738)"
  - "Grade re-pontuada: oimpresso 75 → 85 (Swimm 87) — gap doc↔código fechado, resíduo é UI/enterprise/generalidade"
  - "kb-pest.yml (lane KB per-PR MySQL) entrou por sessão paralela; charter-validate.sh morto removido"
---

# Grade Swimm.io × KB doc↔código — re-pontuada pós A1–D (2026-07-23)

## TL;DR

[W] pediu comparar Swimm.io (plataforma de documentação **acoplada ao código** com doc↔code sync)
com "meu sistema". O comparável **não é o ERP** — é o **aparato de sobrevivência-de-conhecimento do
KB** (doc↔código ancorado + gates + memory). A primeira grade (75 vs 87) expôs os gaps; nesta sessão
**construí o trilho inteiro (A1–D)** e re-pontuei: **oimpresso 75 → 85** (Swimm segue 87). O gap
doc↔código que [W] mirou está **fechado**; o resíduo de Swimm é **UI-no-editor**, **selo enterprise**
e **generalidade language-agnostic** — nenhum era o alvo.

> ⚠️ Honestidade (lápide §5 "claims REFUTADAS", 2026-07-09): quase toda régua isolada tem **par de
> mercado** (o gate stale-doc É o que o Swimm faz; deps-graph é análise estática padrão). O diferencial
> **não é uma régua** — é a pilha **integrada + auto-aplicada** dentro de um ERP vertical multi-tenant.
> A grade abaixo é honesta por-dimensão; **não** é claim de superioridade de categoria.

## O que entrou nesta sessão (o trilho auto-document, estilo Swimm)

| Fase | PR | O que faz | Estado |
|---|---|---|---|
| **A1** | [#4715](https://github.com/wagnerra23/oimpresso.com/pull/4715) | persiste veredito do `kb:drift-detector` em `kb_nodes.code_drift_state` (self-heal) | ✅ merged |
| **A2** | [#4720](https://github.com/wagnerra23/oimpresso.com/pull/4720) | drift cobre nós **bridge** (ADR/session) via `mcp_memory_documents.content_md` | ✅ merged |
| **B** | (merged) | `kb:code-scan` — gera `KbNode` da estrutura PHP via AST (nikic/php-parser, ADR 0350) | ✅ merged |
| **C** | [#4733](https://github.com/wagnerra23/oimpresso.com/pull/4733) | `--project` namespaça o slug — multi-repo sem colisão | ✅ verde |
| **D** | [#4740](https://github.com/wagnerra23/oimpresso.com/pull/4740) | `kb:code-graph` — `kb_edges` de dependência "classe usa classe" (use-imports) | ✅ verde |
| limpeza | [#4738](https://github.com/wagnerra23/oimpresso.com/pull/4738) | remove `charter-validate.sh` morto (superseded) | ✅ verde |

Sem migration nova além do `code_drift_state` (A1). Tudo PHP-first, testado per-PR (lane sqlite +
`kb-pest.yml`), Tier 0 (`--business-id`, cross-tenant biz=1×99). Nada roda no runtime Hostinger (ADR 0062).

## A grade (0–10 por dimensão)

Enquadramento: Swimm = **produto** portável de doc-de-código; oimpresso = **aparato bespoke** de
governança de conhecimento do próprio repo. Totais **não são comensuráveis** — servem de mapa, não de veredito.

| # | Dimensão | Swimm | oimpresso (antes → **agora**) | Δ | Motor do Δ |
|---|---|:---:|:---:|:---:|---|
| 1 | Acoplamento doc↔código + drift | **9** | 8 → **9** | +1 | A1/A2 (drift persiste + cobre bridges + self-heal + cron) |
| 2 | Enforcement que **barra merge** (profundidade) | 6 | **9** | — | required gates (anchor/casos/domínio/multi-tenant) |
| 3 | Auto-gerar doc de **qualquer** código | **9** | 4 → **8** | +4 | B/C (`kb:code-scan` AST → KbNode) |
| 4 | Base consultável + IA | 8 | **8** | — | MCP hybrid-search + Jana |
| 5 | Surface no **IDE / editor** | **9** | **2** | — | não shipou UI (e é KB, não IDE) — **parqueado** |
| 6 | Engine de entendimento (deps/dataflow/dead-code) | **8** | 4 → **6** | +2 | D (`kb:code-graph` deps "usa"; falta dataflow/dead-code) |
| 7 | Onboarding / walkthroughs | **8** | **6** | — | briefings + brief-fetch (sem walkthrough interativo) |
| 8 | **Largura** da âncora (além do código) | 4 | **9** | — | código + UC + design + domínio + fiscal + multi-tenant |
| 9 | Auto-aplicação + ledger de regressão | 3 | **9** | — | §5 proibições + two-strikes (raro no mercado) |
| 10 | Postura enterprise (on-prem/SOC2/BYO-LLM) | **9** | **6** | — | CT 100 + Vaultwarden (não certificado/produtizado) |
| 11 | Generalidade / portabilidade (qualquer repo) | **9** | 2 → **5** | +3 | C (`--project` multi-repo + `--path` arbitrário; mas PHP-only) |
| 12 | Custo / posse | 5 | **8** | — | in-repo, grátis, 100% seu (você constrói/mantém) |
| | **Total (não-comensurável)** | **87** | 75 → **85** | +10 | — |

## Leitura

- **oimpresso fechou o gap que [W] mirou.** As 4 dimensões doc↔código que estavam baixas subiram
  todas: acoplamento (1), auto-gen (3), engine (6), portabilidade (11). O placar foi de 87–75 pra **87–85**.
- **O resíduo de Swimm é fora do alvo:** #5 (surface no editor — parqueado de propósito até a
  `kb/Index.v2` destravar), #10 (selo enterprise/certificação), e a **profundidade** de #3/#6/#11
  (Swimm é mais maduro, language-agnostic, e o engine dele tem dead-code/dataflow que o nosso `use`-graph
  ainda não colhe — isso viria de colher o grafo do **Larastan**, já na stack).
- **oimpresso lidera** onde sempre liderou (2, 8, 9, 12): enforcement que morde, largura de âncora
  (não só código), auto-aplicação/ledger, e posse/custo.

**Uma linha:** pra *documentar código de um repo qualquer com surface no editor*, Swimm ganha. Pra
*forçar conhecimento↔código↔domínio↔design a não driftar NESTE ERP, com enforcement que trava PR,
memória de regressão que se auto-aplica, e agora auto-document + grafo de dependências do código*, o
oimpresso está a **2 pontos** — e nas dimensões que [W] escolheu como alvo, empatou ou passou.

## O que ainda falta (não é do alvo desta sessão)

- **#5 — surfacar na UI da KB** (5º quadrante drift no `HealthPanel` + badge no `NodeReader` + os nós/edges
  de código no grafo). Parqueado: a `kb/Index.v2` é draft/contestada com decisões [W] abertas (D2/D4/D6);
  entra como **emenda de charter** quando destravar.
- **#6 — profundidade do engine:** colher **dead-code + dataflow do Larastan** (já require-dev na stack)
  em vez de só `use`-imports. Roda no CT 100. É a evolução natural do `kb:code-graph`.

## Refs

- Grade original (mesma sessão, pré-build): conversa 2026-07-23.
- ADR 0350 — nikic/php-parser require direto.
- Comandos: `kb:drift-detector` (A1/A2), `kb:code-scan` (B/C), `kb:code-graph` (D) — `Modules/KB/Console/Commands/`.
- Lápide de honestidade: [proibicoes.md §5 "claims REFUTADAS" 2026-07-09](../proibicoes.md).
