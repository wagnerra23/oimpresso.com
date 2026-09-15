---
slug: 0400-handoff-integrity-required-emenda-0314
number: 400
title: "Emenda à 0314 — `handoff integrity` vira required por decisão soberana [W], com a DR-2 da 0336 dispensada e o zero de mordidas registrado"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-09-15"
accepted_at: "2026-09-15"
accepted_via: "Wagner [W] textual 2026-09-15: "promova a requerido" (sessão oimpresso-erp-lista), decidindo a promoção deste gate a required. OVERRIDE SOBERANO com a DR-2 da 0336 DISPENSADA e o zero de mordidas registrado no corpo desta ADR e na entrada de promocoes do baseline — molde das 0346/0348/0373. Nasce aceito (e não proposto) porque a decisão É de [W] e já foi dada: o agente não propôs, executou. Flip do vivo na branch protection = ato [W] separado (0275 §5 / R10)."
module: governance
quarter: 2026-Q3
tags: [governance, gates, ci, required, advisory, handoff, promocao, override-soberano]
supersedes: []
supersedes_partially: []
superseded_by: []
related:
  - 0314-poda-gates-onda-2-lei-fusoes
  - 0327-anchor-content-required-emenda-0314
  - 0336-gates-design-promocao-por-mordida-provada-emenda-0314
  - 0373-screen-grades-ratchet-required-emenda-0314
  - 0395-pageheader-ratchet-required-emenda-0314
  - 0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes
pii: false
---

# ADR 0400 — `handoff integrity` vira required (emenda à 0314)

## Decisão

O context **`handoff integrity (órfão/ref-morta só sobe = vermelho)`** passa de advisory a
**required** em `main`. Baseline 45 para 46 contexts em `classic_protection`.

Decisão de **[W]**, textual, 2026-09-15: *"promova a requerido"*.

## Contexto — e a honestidade que esta ADR existe para registrar

A [ADR 0314](0314-poda-gates-onda-2-lei-fusoes.md) fixou que required é para Tier-0
(dinheiro/PII/multi-tenant/fiscal). A [0336](0336-gates-design-promocao-por-mordida-provada-emenda-0314.md)
acrescentou o critério **DR-2**: promoção pede **mordida real provada** (2+ episódios de
introdução em PRs distintos). Este gate **não bate nenhum dos dois** — e isso fica escrito aqui,
não escondido.

**Mordidas reais: ZERO.** Medido com `gh run list --workflow=handoff-integrity.yml --limit 40`:
as únicas 4 `failure` são de 2026-09-11 em diante e **todas** são o bug de path introduzido pelo
[#7224](https://github.com/wagnerra23/oimpresso.com/pull/7224) — `HANDOFF_DIR` apontando para o
diretório antigo depois que a fila mudou para `memory/reference/prototipo-ui/`. Isso não é dívida
de handoff: é o gate acusando de "ref morta" 5 arquivos que existiam. Consertado no
[#7285](https://github.com/wagnerra23/oimpresso.com/pull/7285), no mesmo dia deste flip. Antes de
09-11, 100% `success`.

Ou seja: **o gate nunca pegou uma dívida real**, e pela letra da 0336 o agente não recomendaria a
promoção. [W] decidiu promover assim mesmo. É **override soberano**, no molde já praticado e
registrado nas [0346](0346-promove-topico-gate-required-override-soberano-emenda-0314.md) ("0 mordidas reais; o agente
recomendou ESPERAR"), [0348](0348-briefing-coverage-required-emenda-0314.md) ("após o proposal recomendar promover NENHUMA") e
[0373](0373-screen-grades-ratchet-required-emenda-0314.md) ("o agente recomendou NÃO promover e
[W] reafirmou"). A soberania do flip é da 0275 §5 / R10 e não se discute; o que o processo exige é
que o desvio seja **datado e legível**, e é o que este documento faz.

### O que pesa a favor, e é o argumento honesto

O episódio de 09-11 a 09-15 mostrou algo pior que uma dívida não pega: **o gate ficou cego por
construção**. Com o `HANDOFF_DIR` errado ele reportava `files: 0` — nunca via arquivo, logo
**nunca acharia um órfão**, que é metade do que ele existe para vigiar. O eixo ficou morto por 4
dias e o vermelho advisory não impediu nada, porque advisory não bloqueia e ninguém parou. Um
required teria forçado a leitura no primeiro PR.

Contra-argumento que fica registrado para quem reabrir: gate que nunca mordeu pode ser gate de
parede parada, e required de parede parada é custo de fila sem retorno (§5 2026-07-17,
`component-registry-check`). A resposta prática é que o custo aqui é baixo — Node puro, sem deps,
sem DB, sem rede, **27s** no run medido — e a reversão é uma linha.

### O que a 0336 exige e este gate cumpre

- **DR-3.1 (exit code não-wrapped):** cumprida. O guard sai `exit 1` real, sem `continue-on-error`
  e sem wrapper que engula o código.
- **Morde em fixture:** o selftest dá **18/18**, incluindo `órfão injetado -> exit 1` e
  `ref morta -> exit 1`, e exercita o **CLI de fora** por subprocesso (§5 2026-07-30), não helper
  exportado. Verde dele não é carimbo.
- **DR-2 (mordida em PR real):** **não cumprida** — dispensada por [W], como acima.

## Pré-requisitos do deadlock, pagos no MESMO PR

O incidente 2026-08-05 a 08-08 (§5) ensinou que promover mexendo no gatilho deixa órfão todo PR
aberto. Os quatro eixos de "required que não nasce" foram fechados aqui:

| eixo | estava | ficou |
|---|---|---|
| `paths:` no `pull_request` | 7 filtros | **removido** (always-run) |
| `types:` sem `synchronize` | `[opened, reopened, ready_for_review]` | **mais `synchronize`** |
| `branches:`/`on:` | ok | intocado |
| nome do job | `handoff integrity (advisory · …)` | `handoff integrity (órfão/ref-morta só sobe = vermelho)` |

Recibo: `node scripts/governance/required-always-run.mjs` resultou em
**47 contexts · 47 always-run · 0 filtrados · 0 sem `synchronize` · 0 não-resolvidos**.

**A dança zero-window do P14 NÃO se aplica** — e isso é medição, não suposição. Ela existe para
renomear job que **já é required** (o context antigo some antes de o novo existir). Aqui o gate
ainda era **advisory**: nenhum context com o nome antigo era exigido pela proteção, então o rename
entra **antes** do flip e o flip já nasce com o nome final. É o mesmo caso da
[0370](0370-module-surface-catalog-graph-required-emenda-0314.md).

O nome perdeu o rótulo `(advisory)` por outra razão, além de ficar falso: artefato **não declara o
próprio enforcement em presente** (§5 2026-07-16 · LC-10, 7 ocorrências). O novo nome descreve o
que o gate **faz** — catraca só-sobe — e não o seu status. Quem precisa saber o que é required lê
o dono: [`governance/required-checks-baseline.json`](../../governance/required-checks-baseline.json).

## Ordem de execução (e por que ela importa)

1. Consertar o gate — #7285, mergeado. **Pré-condição dura:** promover com o `main` vermelho
   travaria todo merge do repositório. Medido verde (`rc=0`) antes de abrir este PR.
2. Este PR: always-run, `synchronize`, rename, baseline 45 para 46, `_HOOKS-INDEX` regenerado, ADR.
3. **Merge — ato [W]** (R10).
4. Flip do vivo na branch protection, **obrigatoriamente** por `gh api --input <arquivo UTF-8 sem
   BOM>` — o context tem `ó` (U+00F3) e payload inline no shell Windows produz mojibake
   (incidente 2026-07-02: `main` BLOCKED com 54/54 verdes). Nunca `-f`/`-F`/heredoc inline.
5. `gh pr update-branch` nos PRs abertos (10 no momento) — sem isso eles carregam o workflow antigo
   e o context novo nunca nasce naquele SHA.
6. `node scripts/governance/protection-drift.mjs` sem 🔴.

## Índices derivados

Alterar o baseline deixa stale, no mesmo instante, o `_HOOKS-INDEX.md` (que embute a contagem).
Regenerado neste PR: `Contexts classic_protection (45)` para `(46)`, com
`hooks-manifest-generate.mjs --check` em `rc=0`.

## Consequências

- **Positiva:** fila de handoff e prompts não podem mais divergir em silêncio; órfão e ref morta
  passam a bloquear merge. A cegueira de 4 dias não se repete sem alguém ver.
- **Negativa:** mais um job obrigatório em todo PR (~27s). E, sendo um gate sem mordida histórica,
  ele entra sob suspeita legítima de parede parada — se em 90 dias não morder em PR real, cabe
  reavaliar (demoção via PR editando o baseline mais ADR, 0275 §5).
- **Reversível:** `gh api DELETE` do context (volta a 45) mais PR revertendo baseline e `types:`.

## Ratificação

Esta ADR **nasce `aceito`** — não há flip de metadata pendente. A decisão é de [W] e foi dada
antes do trabalho começar (*"promova a requerido"*); o agente executou, não propôs. É o molde da
[0336](0336-gates-design-promocao-por-mordida-provada-emenda-0314.md), cujo `accepted_via` também
cita a palavra de [W], e difere da [0395](0395-pageheader-ratchet-required-emenda-0314.md), que
nasceu `proposto` porque ali o agente propunha e aguardava.

O aceite cobre **a política** desta emenda. O **flip do vivo** na branch protection é ato [W]
separado (0275 §5 / R10) e acontece depois do merge.
