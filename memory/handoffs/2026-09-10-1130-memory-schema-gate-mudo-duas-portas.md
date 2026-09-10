---
date: "2026-09-10"
time: "11:30 BRT"
slug: "memory-schema-gate-mudo-duas-portas"
tldr: "Os jobs validate-handoff-schema e validate-session-schema detectavam 0 arquivos desde que nasceram: pathspec cru do git (o * casa /, entao **/ exige diretorio intermediario) contra pastas planas. Fechadas as DUAS portas de mudez — #7182 (pathspec :(glob)) e #7189 (o || true que engolia o rc do git diff). Bite-test de 3 pernas no job selftest, cada uma com controle negativo. Consequencia medida: o gate passa a reprovar 36/43 session logs novos, 24 deles de um gerador."
decided_by: ["W"]
prs: [7182, 7189]
related_adrs:
  - "0130-handoff-append-only-mcp-first"
  - "0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes"
next_steps:
  - "Gerador refutacao-gt-g5-* emite session log sem TL;DR (24 dos 36 vermelhos) — chip iniciado por [W] em sessao separada"
  - "Os 12 session logs a mao que reprovam: sem divida acionavel, cada autor ajusta ao criar o proximo"
  - "Reconciliar a contagem do LC-26 (contador 11, ultima rec rotulada 10a) — descompasso PREEXISTENTE, nao introduzido aqui; mexer nele sem apurar seria falsificar"
---

# memory-schema-gate: o gate que nunca mordeu, e as duas portas pelas quais saía verde

## TL;DR

Dois jobs de schema do `memory-schema-gate.yml` estavam **mudos desde que nasceram** — detectavam
0 arquivos sempre. Duas causas independentes, ambas fechadas hoje, ambas com bite-test e controle
negativo. O gate volta a morder, e a consequência disso está medida e declarada abaixo.

## O que estava quebrado

**Porta 1 — o pathspec (#7182).** `-- 'memory/handoffs/**/*.md'` no pathspec **cru** do git: ali
o `*` casa `/`, então `**/` exige um componente de diretório **intermediário**. `memory/handoffs/`
e `memory/sessions/` são **planas** (`git ls-tree -d` = vazio) — nenhum arquivo casava. Recibo em
400 commits: cru **0**, `:(glob)` **19 handoffs e 42 sessions**.

**Porta 2 — o rc do diff (#7189).** O `|| true` fechando o pipeline fazia `git diff` que falha
sair rc=0 com lista vazia: "diff quebrado" e "nada a validar" ficavam indistinguíveis.

O custo real já observado: dois handoffs fora do regex de nome e sem `## Estado MCP` mergearam
verdes; quem pegou foi o `doc-id-index --check-collisions`, por acidente.

## O que NÃO foi feito, e por quê

Remover o `|| true` — que era o conserto óbvio da porta 2. Medido em `bash -e`: o `grep` sai 1
quando não casa nada, então a remoção crua deixaria **vermelho o caso mais comum** (0 arquivos,
a maioria dos PRs). Teria trocado um gate mudo por um que reclama sempre. O conserto separa o rc
do `git diff` (falha ⇒ vermelho) do rc do `grep` (vazio ⇒ ok).

## Consequência declarada — o gate vai reclamar

Com o gate enxergando, medido no corpus real: handoffs novos **0/19** reprovam; sessions
novas/modificadas **36/43 (83,7%)**. Os 36 são reprovação **legítima** — o corpus usa
`## O pedido`, o schema (e o `_TEMPLATE.md`) exige `## TL;DR`/`## Contexto`. A prática drifou
porque o gate estava cego. **24 dos 36 vêm de um gerador**, com chip aberto.

Nenhum dos dois jobs é required — medido contra a união `classic_protection` + `rulesets` (46
contexts). O conserto **nasce advisory por construção**: fica vermelho, não bloqueia merge.

## Estado MCP no momento do fechamento

⚠️ **Honestidade sobre a fonte:** esta sessão **não tem as tools MCP conectadas** (não há
`mcp__Oimpresso_MCP__*` no toolset). O checklist do ADR 0130 não pôde ser cumprido pela via
canônica; o que segue é o que o hook `brief-fetch` do SessionStart entregou (**Brief #625**,
gerado ~84 min antes do início) mais o que foi medido em git/gh **nesta sessão**. Quem retomar
deve rodar `cycles-active` + `my-work` + `sessions-recent` de verdade — não herdar isto como
estado vivo.

- **Cycle ativo:** o brief veio com o campo vazio (`Cycle: — (—)`), não com um cycle nomeado.
- **HITL pendente [W]:** 5 (topo: "Decidir: `agent-corpus-counterfactual`", "Refinar runbook on-prem").
- **Em voo (brief):** 12 itens, topo em Forja (Triage), Produto ([G-06] BOM drag-drop, [V0] preço zero) e Infra (Zod schemas).
- **Flags do brief:** 🟠 683 US não atribuídas (524 sem dono) · 🟡 SDD composta 55,4 (Δ-0,2) · migration aging, PRs em review e visual regression sem nada crítico.
- **Medido nesta sessão (git/gh, não MCP):** `origin/main` em `99ad488960`; #7182 mergeado em
  `4c754ce153` e #7189 em `e2ffd156da` (este por `wagnerra23`, 13:06Z); run de push do
  `memory-schema-gate` em main: `0eba535ff6 success`. Sem colisão de sessão — nenhum outro PR
  aberto tocava `.github/workflows/memory-schema-gate.yml`.

## Para quem retomar

O bite-test é o ponto de entrada: `node scripts/tests/memory-schema-detect.test.mjs` roda em
segundos, sem deps de npm, e as 3 pernas dizem em uma tela se o gate ainda enxerga, ainda morde e
ainda propaga o rc. Se alguém reverter qualquer um dos dois consertos, ele fica vermelho no job
`selftest` — foi provado por controle negativo nas duas direções, não assumido.
