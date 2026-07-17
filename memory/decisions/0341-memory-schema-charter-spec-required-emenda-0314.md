---
slug: 0341-memory-schema-charter-spec-required-emenda-0314
number: 341
title: "Emenda à 0314 — memory-schema REQUIRED só nas 2 famílias limpas (charter · spec); as outras 6 esperam backfill"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-07-17"
module: governance
tags: [governance, gates, ci, required, schema, memoria, branch-protection]
supersedes: []
superseded_by: []
related:
  - 0327-anchor-content-required-emenda-0314
  - 0314-poda-gates-onda-2-lei-fusoes
  - 0271-revisao-gates-ci-estado-real-required-e-subtracao-segura
  - 0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes
---

# ADR 0341 — emenda à 0314: `memory-schema` a REQUIRED em `charter` + `spec`

## Contexto

[W] 2026-07-17, textual: **"torne requerido, para todos os arquivos"**.

O pedido nasce de um caso real do mesmo dia: o `kb/Index.v2.charter.md` foi mergeado
(#4393/#4396/#4407) com `charter_version: 2.1` e depois `2.2` — **inválidos** (o schema exige
`{type: integer}`; decimal vira float em YAML). O gate `Charter (resources/js/Pages/**/*.charter.md)`
**reprovou e o PR mergeou assim mesmo** — não houve bypass: o check simplesmente **não é required**
(28 required vivos, `enforce_admins: true`; nenhum deles é o memory-schema). Um gate sem dentes
deixou um documento canônico mentir sobre a própria versão, por 3 PRs.

## Decisão

**Promover a REQUIRED apenas as 2 famílias que estão em ZERO violação; as outras 6 esperam backfill.**

Medição que decidiu (2026-07-17, AJV + os schemas do repo, contra a árvore inteira):

| família | total | violam | % | promover? |
|---|---:|---:|---:|---|
| **charter** | 237 | **0** | 0% | ✅ **SIM** |
| **spec** | 59 | **0** | 0% | ✅ **SIM** |
| adr | 346 | 143 | 41% | ❌ append-only (ver abaixo) |
| handoff | 259 | 120 | 46% | ❌ backfill |
| session | 451 | 283 | 63% | ❌ backfill |
| runbook | 152 | 124 | 82% | ❌ backfill |
| briefing | 77 | 66 | 86% | ❌ backfill (já é `grace` na matriz) |
| reference | 124 | 122 | 98% | ❌ backfill (já é `grace`) |

**858 arquivos violam.** O gate é **diff-aware** (valida só arquivos NOVOS/MODIFICADOS), então
promover as 6 sujas não quebraria o CI hoje — **quebraria no primeiro PR que tocasse qualquer um
dos 858**, incluindo os da fila imediata (o BRIEFING do KB e os session logs desta sessão). É
exatamente a lápide de **2026-07-12** (§5): *"tocar um arquivo legado ACORDA os gates diff-aware
que o protegiam por grandfather"* — lá um codemod tecnicamente impecável de 52 SPECs foi barrado
por dívida pré-existente que o toque acordou.

**Os ADRs são caso à parte e mais duro:** 143 violam, e a Constituição é **append-only** — editar
ADR aceita pra consertar schema é proibido (ADR 0095 · Constituição Art. 3). Promover `adr` a
required tornaria 143 decisões **permanentemente intocáveis**: qualquer PR que as tocasse falharia,
e o conserto é vedado. Não há backfill legítimo possível sem decisão estrutural própria.

## Por que isto é exceção legítima à 0314 (e não burla)

A 0314 fixou **required = só Tier-0** (dinheiro/PII/multi-tenant/fiscal). Schema de doc **não é
Tier-0** — é higiene. A 0327 abriu a **1ª exceção formal** (`anchor-content-check`), estabelecendo
a política vigente: **"required = Tier-0 + exceções explicitamente autorizadas via emenda + flip [W]"**.
Esta é a 2ª exceção, pelo mesmo processo: emenda reabrindo a 0314 pro item + flip [W] explícito.

Justificativa que a régua Tier-0 não pesa — **custo zero e reincidência provada**:
- **Custo zero medido:** 0 de 296 arquivos (charter+spec) violam. O flip não acorda dívida nenhuma,
  não bloqueia PR nenhum aberto, não exige backfill. Morde **só o que nascer torto**.
- **Reincidência no mesmo dia:** o `charter_version` decimal atravessou **3 PRs** (#4393, #4396,
  #4407) porque o gate era advisory. Advisory que deixa o erro reincidir é o oposto de gate.
- **Convenção já é unânime:** 195 charters usam versão inteira; os 4 `1.0` são legado. O decimal foi
  invenção do agente — o gate required teria matado na origem.

## Consequências

**Ganho:** documento canônico não mente mais sobre metadado próprio em `charter`/`spec` — o CI
barra na entrada, não na revisão adversarial 3 PRs depois.

**Custo:** zero hoje. Daqui pra frente, charter/spec novo nasce com frontmatter válido (é o que 296
de 296 já fazem).

**Não coberto (honesto):** o schema valida **forma**, não **verdade**. Ele teria pego o `2.2`, mas
**não** pegaria o `3.016 documentos` (número errado no corpo) — isso é a lei *"fato derivado não se
restateia"* (§5, 2026-07-17), que é subtração, não gate.

**As outras 6 famílias:** ficam advisory. Promover cada uma exige (a) backfill da família até 0
violação, (b) **nova emenda** a esta ADR. `adr` exige antes uma decisão estrutural sobre append-only
× schema retroativo. Não há calendário automático: sem backfill, sem promoção.

## Gate de reversão

Rebaixar `charter`/`spec` a advisory se aparecer **falso-positivo** (arquivo válido reprovado) — via
PR editando `governance/required-checks-baseline.json` + emenda a esta ADR (ADR 0275 §5: demoção só
via PR + ADR).

## Aplicação

1. `governance/required-checks-baseline.json` — entram os 2 contexts.
2. Branch protection do `main` — via `gh api --input <arquivo UTF-8 sem BOM>`, **nunca** payload
   inline (proibições §Ambiente: shell Windows re-encoda não-ASCII → mojibake deadlockou o merge em
   2026-07-02).
3. Validação obrigatória pós-PUT: `node scripts/governance/protection-drift.mjs` (contagem via GET
   **não** prova — mojibake mantém a contagem).
