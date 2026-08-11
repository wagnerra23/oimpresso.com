---
date: "2026-08-11"
time: "13:36 UTC"
slug: fronteira-que-nao-era-e-o-plano-que-nao-funcionava
tldr: "Fechado o resíduo declarado do PaymentGateway (5 endpoints fantasma no CONTRACTS.md) — os 5 existiam sob outro controller; a §6 inteira era plano de Onda 0 nunca confrontado com o código, com 4 divergências. Depois, [W] perguntou se módulo morto deveria estar na 'fronteira' do catalog-graph: 4 dos 5 eram removidos por ADR. Meu plano de corrigir por doc foi MEDIDO e não funcionava."
prs: [5566, 5582]
decided_by: [W]
next_steps:
  - "Nada bloqueado. Resíduos declarados nos próprios docs, não em memória."
  - "SCOPE do PaymentGateway declara CobrancaController e BoletoService, que vivem em Financeiro e RecurringBilling (gate reporta 'fora do módulo', informativo) — decisão de ownership, não de texto."
  - "Cutover da Onda 3 nunca aconteceu: PaymentGateway e RecurringBilling servem webhook do Inter em paralelo. Estado de produto."
  - "Modules/Project segue AMBIGUO no ghost-rename-map ('fila humana') e a Fase 3.9 do Forja planeja renomear Forja→Project. Decisão de domínio [W]."
---

## Estado MCP no momento do fechamento

⚠️ **Não consultado — MCP indisponível a sessão inteira.** O hook do `SessionStart` caiu em
fallback declarando *"servidor MCP não respondeu no tempo (timeout)"*, e nenhuma tool
`mcp__oimpresso__*` esteve exposta. Logo **não** rodei `cycles-active`, `my-work`,
`sessions-recent` nem `decisions-search`. Isto é ausência de medição, não estado verde —
registro como tal em vez de afirmar que consultei (mesma condição do handoff das 11:09).

O que consegui medir sem MCP: `Glob` de handoffs/sessions do dia (sem duplicata minha),
`gh pr view/checks` dos 2 PRs, e os gates locais citados abaixo.

## O que aconteceu

**Parte 1 — o resíduo do PaymentGateway ([#5566](https://github.com/wagnerra23/oimpresso.com/pull/5566)).**
O `SCOPE.md` tinha declarado em 2026-08-10 que o `CONTRACTS.md` §6 ainda citava 5 endpoints
`→ PaymentGatewayController@*`. Medido: o controller **nunca foi construído** (10 hits no repo,
todos em doc, zero em código), mas os 5 endpoints **existem** — sob `Settings\PaymentGatewaysController`,
prefixo `/settings/`, método `healthCheck`. Era ponteiro errado, não capacidade ausente.

A raiz está no cabeçalho do próprio doc: `v0.1 rascunho Onda 0`. A §6 era **plano**, e ao medir
a seção inteira apareceram mais 3 divergências — `CobrancaController` mora no Financeiro; os
webhooks são 7 e não 4, com outro path; e a frase *"301 redirect durante 30 dias após cutover
Onda 3"* era falsa (zero redirect nos 56 arquivos de rota; o RecurringBilling segue servindo em
paralelo). [W] autorizou reconciliar tudo.

**Parte 2 — "isso deveria estar na fronteira?" ([#5582](https://github.com/wagnerra23/oimpresso.com/pull/5582)).**
O `catalog-graph` chamava 5 módulos de *"fronteira futura/legada"*. Medido: **4 não são fronteira** —
`Accounting` (ADR 0172), `Admin` (0360), `SRS` (0357) foram removidos, e `Project` tem tombstone
sem data. Só `Notas` é futuro. O `Governance/SCOPE.md` diz textualmente *"a fronteira não existe
mais"* — e é essa linha que fazia o grafo criar o nó de fronteira.

## Lições catalogadas

**Erro meu, e é a lição principal: propus a [W] um plano sem medir se ele alcançava o objetivo.**
Recomendei corrigir 4 `not_contains` de SCOPE; [W] aprovou; só então medi que `moduleRefsIn` usa
`/Modules\/([A-Z]\w+)/g` sobre a string inteira — **qualquer** menção cria o nó, inclusive dentro
da lápide "módulo REMOVIDO". A correção por doc só funcionaria apagando o nome do módulo,
destruindo o registro que o §5 manda preservar. Voltei a [W] em vez de executar um "sim" que não
entregaria nada. É a família LC-08 aplicada a **proposta**, não a achado: a hora de medir é antes
de recomendar.

**O que salvou os dois PRs foi instrumento discordando de instrumento.** No #5566, `git merge-tree`
disse *limpo* e o GitHub disse *conflito* — o [#5548](https://github.com/wagnerra23/oimpresso.com/pull/5548)
tinha movido o `CONTRACTS.md` para `memory/requisitos/` no meio do trabalho. O merge trouxe o
conteúdo intacto, mas o dano real não foi apontado por nenhum dos dois: o link que eu escrevera
era relativo ao path velho e quebraria o `deadlink-gate` (required, ADR 0347).

**A verdade já existia curada e ninguém lia.** O `ghost-rename-map.json` tem `removed_at` +
`removed_by_adr` dos 4 módulos mortos desde sempre; o `catalog-graph` só não consultava. Não foi
preciso criar registro, régua nem gate — só ler o dono (§5 "aponta pro dono, não restateia").

## Artefatos gerados

| Arquivo | O quê |
|---|---|
| `memory/requisitos/PaymentGateway/CONTRACTS.md` | §6 reconciliada com `Routes/web.php` + nota datada das 4 divergências |
| `Modules/PaymentGateway/SCOPE.md` | comentário do resíduo vira fato datado, apontando o novo path do doc |
| `scripts/governance/catalog-graph.mjs` | `classifyReferencedOnly()` + `loadModuleTombstones()`; **só a mensagem** — nó, aresta e `catalog.json` intocados |
| `scripts/governance/catalog-graph.test.mjs` | 18 → 23 asserts, 2 BITE + 3 controles negativos |

## Persistência

- **git** — os 2 PRs mergeados por [W] via auto-merge (`2a96af77fa2`, `036fa9a619b`).
- **MCP** — ❌ não propagado nesta sessão; servidor indisponível. Nenhuma task criada/atualizada.
- **BRIEFING** — não tocado: mudança de doc de contrato + diagnóstico, sem alterar capacidade.

## Próximos passos pra retomar

```bash
node scripts/governance/catalog-graph.mjs --check
```

Nada pendente. Os resíduos que sobraram estão declarados nos próprios docs (não nesta memória),
e os 3 do frontmatter são decisão [W], não trabalho parado.

## Pointers detalhados

- Session log: [`2026-08-11-contrato-fantasma-e-a-fronteira-de-modulo-morto.md`](../sessions/2026-08-11-contrato-fantasma-e-a-fronteira-de-modulo-morto.md)
- Verificação do #5566: nenhum gate lê `CONTRACTS.md` (`git grep CONTRACTS` em `scripts/`, `.github/`, `bin/` = 0 hits) — os 4 checks locais provam não-regressão, não o fix.
- Registro dos tombstones: [`governance/ghost-rename-map.json`](../../governance/ghost-rename-map.json) chave `excluded`.
