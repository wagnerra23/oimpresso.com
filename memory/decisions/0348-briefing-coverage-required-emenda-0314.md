---
slug: 0348-briefing-coverage-required-emenda-0314
number: 348
title: "Emenda à 0314 — cobertura de BRIEFING por módulo backend (briefing-code-staleness --strict-coverage) promovida a REQUIRED com teste de mordida; override soberano [W]"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-07-23"
accepted_at: "2026-07-23"
accepted_via: "Wagner override soberano explícito (2026-07-23, sessão zealous-allen): 'eu quero teste para promover agora, faça promover com teste agora' — após o proposal 2026-07-23-sentinelas-staleness-prontidao-required recomendar promover NENHUMA. [W] exerce a prerrogativa R10/ADR 0238 e escolhe a única candidata require-safe zero-FP (--strict-coverage), com o teste de mordida (M/N/O) wirado no job required."
module: governance
quarter: 2026-Q3
tags: [governance, gates, ci, required, advisory, cobertura, briefing, staleness, promocao, override-soberano, emenda-0314]
supersedes: []
supersedes_partially: []
superseded_by: []
related:
  - 0314-poda-gates-onda-2-lei-fusoes
  - 0336-gates-design-promocao-por-mordida-provada-emenda-0314
  - 0327-anchor-content-required-emenda-0314
  - 0341-memory-schema-charter-spec-required-emenda-0314
  - 0346-promove-topico-gate-required-override-soberano-emenda-0314
  - 0256-knowledge-survival-meia-vida-catraca-sentinela
pii: false
---

# ADR 0348 — emenda à 0314: `briefing-code-staleness --strict-coverage` a REQUIRED

> **Status:** `aceito` (2026-07-23, [W] override soberano "faça promover com teste agora").
> Append-only — **não edito a [0314](0314-poda-gates-onda-2-lei-fusoes.md)**; esta ADR adiciona
> uma exceção pontual à regra "required = só Tier-0", no mesmo processo formal das
> [0327](0327-anchor-content-required-emenda-0314.md) / [0341](0341-memory-schema-charter-spec-required-emenda-0314.md) / [0346](0346-promove-topico-gate-required-override-soberano-emenda-0314.md).
>
> **Enactment:** o context `Modulo backend com BRIEFING (cobertura)` é adicionado ao branch
> protection de `main` por [W] (R10) **após** o job rodar verde no main uma vez (DR-3.4). O flip
> do vivo NÃO está feito neste PR — o PR entrega a máquina + o teste + o registro; o clique é ato [W].

## Contexto

O item 3 da grade anti-apodrecimento (2026-07-23, proposal
[2026-07-23-sentinelas-staleness-prontidao-required](proposals/2026-07-23-sentinelas-staleness-prontidao-required.md))
investigou quais das ~13 sentinelas de staleness/drift medem um **fato derivável, determinístico,
sem julgamento e sem falso-positivo** — logo candidatas legítimas a required. O veredito técnico
(confirmado por adversário cético) foi: **promover NENHUMA por mérito de critério**, porque

- toda a família de **frescor** ancora numa **data auto-declarada** (`updated_at:`/`last_updated:`) —
  gameável, o `last_validated`-teatro que a [proibicoes.md §5](../proibicoes.md) já baniu 2×;
- as 2 candidatas de **forma limpa** (`--strict-coverage` e `detect-ui-drift --strict`) são
  **não-Tier-0** → caem sob a [0336](0336-gates-design-promocao-por-mordida-provada-emenda-0314.md),
  que exige **mordida provada (≥2 no bite-log DR-2)** — e o bite-log foi medido **vazio (0)**.

[W], lendo o proposal (que já apontava `--strict-coverage` como *"a única forma-limpa"* require-safe),
exerceu o **override soberano** (ADR 0238 / R10): *promover agora, com teste*. Esta ADR registra a
promoção **honestamente como desvio consciente**, não como cumprimento dos critérios da 0336 — o
mesmo enquadramento que a [0346](0346-promove-topico-gate-required-override-soberano-emenda-0314.md)
e a [0339] adotaram.

## Decisão

Promover **`scripts/governance/briefing-code-staleness.mjs --strict-coverage`** a **required**, num job
dedicado hard-fail (`.github/workflows/briefing-coverage-required.yml`, context
`Modulo backend com BRIEFING (cobertura)` — nome ASCII puro, disciplina anti-mojibake da
[proibicoes §Ambiente](../proibicoes.md)). O gate falha o merge se **qualquer módulo backend**
(`Modules/<X>/` com dir `memory/requisitos/<X>/`) **não tiver `BRIEFING.md`**.

## Por que é o candidato CERTO (e por que não os outros)

| Propriedade | `--strict-coverage` (escolhido) | frescor (rejeitado) | `detect-ui-drift` (rejeitado) |
|---|---|---|---|
| Âncora | **existência de dir+arquivo** | data auto-declarada (gameável) | diff binário |
| Falso-positivo | **ZERO** (só-frontend escopado fora) | — | fricção alta (flaga bugfix legítimo) |
| Gameável | **não** | **sim** (bump de data) | parcial |
| Verde no main hoje | **sim** (51 aval., 0 gaps) | 7 stale | — |
| Require-safe (always-run, sem paths-filter) | **sim** | — | não (paths-filter) |

`--strict-coverage` é a **mesma classe aceita do casos-gate G-1** (o próprio script diz:
*"isto é ENFORÇÁVEL (diferente do frescor): o sinal é a EXISTÊNCIA… NÃO-gameável por data
auto-escrita"*) e o **mesmo shape** que a [0341](0341-memory-schema-charter-spec-required-emenda-0314.md)
usou pra promover charter/spec: **família em zero-violação, custo-zero, não acorda legado, morde só o
que nascer torto**.

## O "teste para promover" (o que [W] pediu)

O gate **não é presença-nua** — ele carrega um teste de mordida que PROVA que morde, e esse teste
roda **dentro** do job required (test quebrado = job vermelho antes do gate). São os casos
**M / N / O** de [`briefing-code-staleness.test.mjs`](../../scripts/governance/briefing-code-staleness.test.mjs),
sobre a função pura `isBriefingCoverageGap`:

- **N (bite / fixture-ruim):** `{hasBackend:true, hasDoor:false}` → **GAP** (morde). O comentário do
  teste é literal: *"a fixture-ruim que prova que --strict-coverage MORDE: sem esta, o gate seria teatro"*.
- **M (release):** `{hasBackend:true, hasDoor:true}` → NÃO é gap (libera).
- **O (anti-FP):** `{hasBackend:false, hasDoor:false}` (área só-frontend tipo `User/Perfil`) → NÃO é gap.

Isto é o controle-negativo modelo `gate-selftest` (GT-G6): a fixture-boa/fixture-ruim prova que a
catraca morde, não só que existe.

## O que é honesto declarar (não escondo)

1. **Isto NÃO é Tier-0.** Cobertura de BRIEFING é higiene de doc, não dinheiro/PII/multi-tenant/fiscal.
   Sob a régua-default da 0314 **não** seria required. Vira required **só** pelo override soberano [W].
2. **Mordida real = 0.** O bite-log DR-2 (`design-gate-bites.jsonl`) está vazio e o `coverageGaps` de
   hoje é `[]` — o gate **nunca disparou no mundo**. Promover um gate que nunca mordeu é, pela letra
   da §5, o **anti-padrão foundation-ratchet / 0346 zero-day**. Registro isto como **desvio consciente
   [W]**, não como critério batido — igual a 0346/0339/0341.
3. **Dano limitado (por que o desvio é aceitável):** (a) **zero-FP** — existência de arquivo não tem
   ambiguidade; (b) **verde no main** — não trava nenhum PR aberto hoje; (c) **greenfield** — 0 dívida
   de legado (cobertura já 100%); (d) **require-safe** — always-run, Node puro <60s, sem paths-filter;
   (e) **exit-code REAL** (DR-3.1: sem wrapper/`continue-on-error` — não é required-toothless).
4. **Resíduo de escopo (declarado):** o scan é ancorado em `memory/requisitos/<X>/` — um `Modules/<X>/`
   **sem nenhum dir requisitos** escapa. `RUNBOOK-criar-modulo` cria o dir requisitos, então o caso
   comum é coberto; ancorar direto em `Modules/` (fechar o resíduo) fica pra PR próprio se [W] quiser.

## DR-3 (ADR 0336) — checklist do PR de promoção

1. [x] **Exit-code desembrulhado** — o job roda `--strict-coverage` sem `continue-on-error`; `--check` fail = job fail.
2. [ ] **Bite-log ≥2** — **NÃO cumprido** (override soberano; substituído pelo teste M/N/O + framing 0346).
3. [x] **Registros sincronizados no mesmo PR** — `gates-registry.json` (novo workflow, `terminal: required` + `anchor`) e este ADR. O `checkM` do `.memory-health-baseline.json` **não precisa** de edição: gate novo com `terminal`+`anchor` passa o Check M sem grandfather (verificado em `memory-health.mjs:606-614`).
4. [ ] **Verde no main uma vez** ANTES do flip — o job roda no `push: main` do merge; SÓ depois [W] adiciona o context ao branch protection (evita travar PRs por nome divergente).
5. [ ] **Janela + [W]** — [W] já autorizou (override soberano). Janela ≥14d **waivada** por [W] explícito, como na 0346 (soak pulado; barra de reversão BAIXA em troca).

+ `required-checks-baseline.json` recebe o context + entrada em `promocoes` no mesmo PR (senão o
`protection-drift.mjs` sinaliza 🟡 required-novo-fora-do-baseline).

## Gate de reversão (herda 0327/0336/0346 — barra BAIXA porque o soak foi pulado)

Qualquer falso-positivo real (bloqueou merge legítimo sem gap de cobertura verdadeiro), ou fricção que
[W] julgue indevida, **rebaixa a advisory imediatamente** (remover o context dos required via `gh api`
+ nota nesta ADR). Demover ≠ apagar — o reporter advisory (`--strict-coverage` local) sobrevive.

## Ratificação (R10)

- [x] [W] autoriza a promoção (override soberano, 2026-07-23 "faça promover com teste agora").
- [ ] (pós-merge) job verde no `push: main`.
- [ ] (pós-verde) [W] adiciona o context `Modulo backend com BRIEFING (cobertura)` ao branch protection de `main` (comando pronto no PR).
