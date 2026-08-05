---
slug: 0370-module-surface-catalog-graph-required-emenda-0314
number: 370
title: "Emenda à 0314 — module-surface e catalog-graph promovidos a REQUIRED (índice derivado que drifta, com required-readiness paga no mesmo PR)"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-08-05"
module: governance
quarter: 2026-Q3
tags: [governance, gates, ci, required, module-surface, catalog-graph, branch-protection, indice-derivado, required-readiness, deadlock]
supersedes: []
superseded_by: []
related: [0314-poda-gates-onda-2-lei-fusoes, 0369-tres-lanes-pest-valor-estoque-lei-required-emenda-0314, 0336-gates-design-promocao-por-mordida-provada-emenda-0314, 0271-revisao-gates-ci-estado-real-required-e-subtracao-segura, 0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes, 0256-knowledge-survival-meia-vida-catraca-sentinela]
---

# ADR 0370 — Emenda à 0314: `module-surface` e `catalog-graph` promovidos a REQUIRED

## Contexto

Segunda leva do dia. A [ADR 0369](0369-tres-lanes-pest-valor-estoque-lei-required-emenda-0314.md)
promoveu 3 lanes Pest (valor/estoque/lei) e **deixou estes dois de fora de propósito**: mordiam
de verdade, mas guardam **índice derivado**, não Tier-0 — e a 0369 registrou que mereciam
"emenda própria com argumento próprio, não carona". Esta é a emenda.

**O que eles guardam:**
- `module-surface` — o `SUPERFICIE.md` de cada módulo é **gerado** da árvore. Se o módulo ganha
  um Controller e ninguém regenera, o índice mente. É a [ADR 0256](0256-knowledge-survival-meia-vida-catraca-sentinela.md)
  aplicada: *derivado e enforçado sobrevive; escrito e lembrado apodrece*.
- `catalog-graph` — prova que `memory/governance/catalog.json` é a derivada determinística dos
  `SCOPE.md` + `SUPERFICIE.md`, sem aresta pendurada.

## Sinal de custo (medido em 2026-08-05, classificado pelo STEP)

| gate | mordidas | janela | leitura |
|---|---:|---|---|
| `module-surface` | **10** | 3 dias, **6 branches**, várias no mesmo dia | drift **ativo e recorrente** |
| `catalog-graph` | **3** | 31/07–01/08, todas em branches `ads-*` | **episódio pontual** |

Todas as 13 são o step do próprio `--check` reprovando — nenhuma é infra.

**Falso-positivo crônico DESCARTADO por medição, não por confiança:** ambos saem **exit 0 no
`main`**. Ou seja, o gate não vive vermelho; ele acusa **drift real introduzido pelo PR**. Gate
certo, autor errado. Isso satisfaz a condição que o próprio `module-surface.yml` declarava no
cabeçalho — *"promoção a required = N módulos adotarem + FP medido = 0"* — que estava escrita
havia meses e nunca tinha sido verificada.

## Required-readiness: o pré-requisito que quase virou deadlock

**Este é o ponto mais importante desta ADR.** Os dois workflows declaravam `pull_request` **COM
`paths:`** — ou seja, **não eram always-run**. Promovê-los assim significaria: qualquer PR que não
tocasse `Modules/**` (ou `SCOPE.md`) deixaria o context **PENDING para sempre**, travando o merge
do repositório inteiro. É exatamente o **deadlock de 2026-07-02** registrado em
`memory/proibicoes.md` §Ambiente, onde `main` ficou `BLOCKED` com 54/54 checks verdes.

A 0369 não teve esse problema porque as 3 lanes Pest **já nasceram** required-safe
([ADR 0271](0271-revisao-gates-ci-estado-real-required-e-subtracao-segura.md) onda 2: sem `paths`
+ `dorny/paths-filter` interno para skip-as-pass). Aqui o pré-requisito **foi pago no mesmo PR**:
o `paths:` saiu dos dois.

**O custo do sempre-rodar foi medido, não estimado.** O `gh run list` mostrava 480s e 382s de
média — mas isso é **fila de runner**. A duração real do *job* é **19s** (module-surface),
**18s** (selftest) e **33s** (catalog-graph); o script em si roda em **<1s**. Rodar sempre é
barato; a leitura ingênua do run teria matado a promoção por um custo que não existe.

## Decisão

Promover a **required** os 3 contexts (37 → 40):

- `Self-test — classificação por papel + montagem determinística` *(module-surface — integridade
  do próprio gerador; se a classificação quebra, o resto não vale nada)*
- `SUPERFICIE.md == árvore (módulos vivos + adotados)` *(module-surface)*
- `catalog.json == SCOPEs + Classes B` *(catalog-graph)*

No `gates-registry.json`, `terminal` vira `required` e o `promote_by` sai.

**Renomeação preventiva:** o job do `catalog-graph` chamava-se `… (advisory)`. Virar required com
esse nome faria o label mentir — a lápide §5 2026-07-16 custou 8 sites errados por isso. Foi
renomeado **antes** de entrar no baseline; como ainda não era required, não houve context a
preservar e a dança zero-window do P14 não foi necessária.

## Por que required, se não é Tier-0

A 0314 fixou "required = só Tier-0", e as emendas [0327](0327-anchor-content-required-emenda-0314.md),
[0347](0347-deadlink-gate-required-emenda-0314.md), [0348](0348-briefing-coverage-required-emenda-0314.md),
[0354](0354-teammcp-pest-required-emenda-0314.md) e [0369](0369-tres-lanes-pest-valor-estoque-lei-required-emenda-0314.md)
abriram exceções pelo mesmo processo. O argumento aqui **não é** que índice derivado seja Tier-0 —
é que **índice derivado que drifta é mentira com selo de canon**: o `SUPERFICIE.md` é lido como
verdade sobre o que existe no módulo, e um índice atrasado leva a decisão errada em silêncio. A
reincidência (10 em 3 dias) mostra que advisory **não estava segurando** — que é a mesma régua
que a 0369 usou para as lanes que falhavam no `main`.

## Consequências

**Positivo:** (a) índice derivado desatualizado passa a bloquear merge, e regenerar custa um
comando; (b) os dois gates saem do limbo de "advisory eterno" que a
[ADR 0298](0298-teto-de-governanca-anti-proliferacao-gates.md) existe pra impedir; (c) o
`module-surface` deixa de ter, no próprio cabeçalho, uma condição de promoção nunca verificada.

**Custo/risco:** (a) os dois passam a rodar em **todo** PR (~19s + ~18s + ~33s) — medido e
aceito; (b) PR que toque `Modules/**` sem regenerar `SUPERFICIE.md` **para** até regenerar — é o
objetivo, mas é atrito novo, e a taxa medida (10 em 3 dias) sugere que vai doer no começo;
(c) remover `paths:` aumenta o número de check-runs do repo.

**Gate de reversão:** se qualquer um dos 3 contexts acusar **falso-positivo** (drift que o autor
não introduziu) em ≥2 PRs distintos, rebaixar aquele context a advisory por emenda a esta ADR
(append-only, nunca edição) e tratar o FP como defeito do gerador.

## Alternativas consideradas

- **(A) Promover sem remover o `paths:`** — rejeitado: é o deadlock de 2026-07-02, com o `main`
  travado para todos. Foi o primeiro achado desta ADR e quase passou despercebido.
- **(B) Não promover, por não serem Tier-0** — rejeitado: a régua da 0369 (advisory que não
  segura, com mordida provada e FP descartado) se aplica igual aqui, e a reincidência de 10 em
  3 dias é o sinal mais forte de qualquer gate desta leva.
- **(C) Promover só o `catalog-graph`** (episódio pontual, menos atrito) — rejeitado: é justamente
  o que tem MENOS sinal. Promover o de menor mordida e deixar o de maior seria escolher pelo
  conforto, não pela evidência.
