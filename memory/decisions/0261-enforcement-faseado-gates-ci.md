---
slug: 0261-enforcement-faseado-gates-ci
number: 261
title: "Enforcement faseado dos gates de CI: required-checks por níveis + skip-as-pass + enforce_admins"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: "2026-06-07"
accepted_at: "2026-06-07"
accepted_via: "Wagner autorizou na sessão (reavaliação do IA-os, Fase 0 + deep-dive A). Alavanca 1 aplicada na mesma sessão via gh api."
module: governance
quarter: 2026-Q2
tags: [governance, ci, enforcement, branch-protection, ratchet, gates, tier-0]
supersedes: []
supersedes_partially: []
superseded_by: []
related: ["0256-knowledge-survival-meia-vida-catraca-sentinela", "0155-module-grade-v3-sub-dimensoes-gate-ci", "0160-governance-v4-scoped-scorecards-buckets", "0093-multi-tenant-isolation-tier-0"]
pii: false
---

# ADR 0261 — Enforcement faseado dos gates de CI

## Contexto

A reavaliação do IA-os (sessão 2026-06-07, ver [Fase 0 + deep-dive A](../sessions/2026-06-07-reavaliacao-iaos-fase0-enforcement.md)) achou a **falha-mãe** do aparato de governança: dos **40 workflows de CI**, apenas **1** era *required status check* no branch protection do `main` — e o único era `ADR frontmatter` (o mais trivial). Além disso `enforce_admins: false` (admin fura até esse). Na prática: dava pra mergear no `main` com **Pest quebrado, build quebrado, multi-tenant violado, segredo vazado** — só não com frontmatter de ADR malformado.

**Causa-raiz (não é desleixo):** 25 dos 28 gates de PR são **path-scoped** (correto, por velocidade/custo). No GitHub, um required-check que **não roda** (path não bateu) nunca reporta status e **trava o PR eternamente** em "Expected — waiting". Exigir os path-scoped congelaria PRs não-relacionados. A saída do time foi exigir quase nada — jogando fora o enforcement junto com o deadlock.

Isso colide com o **princípio duro #4 (loop fechado por métrica)** e enfraquece toda a maquinaria de catraca (ADR 0256 Knowledge Survival, ADR 0155/0160 module grades): a catraca existe, mas a **tranca da catraca** estava solta. O sistema "evolui sempre" por **disciplina**, não por **mecanismo** — frágil quando o time MCP (Felipe/Maiara/Eliana/Luiz) entrar sem o mesmo reflexo.

## Decisão

Enforcement em **3 alavancas faseadas**, por esforço×risco crescente:

**Alavanca 1 — promover os gates always-run (risco zero, aplicada 2026-06-07).**
Workflows sem path-filter sempre reportam status → seguros como required sem deadlock. Promovidos a required no `main`:
`PHP / Pest (Unit)` · `Frontend / Vite build` · `module-grades-gate` (somados ao `ADR frontmatter` pré-existente → **1 → 4**).

**Alavanca 2 — converter os Tier-0 path-scoped pra "always-run + skip-as-pass", aí exigir.**
Padrão: trocar o `paths:` de nível-workflow por always-run + 1º step que detecta "nenhum arquivo relevante mudou → exit 0 verde" (o filtro de path vira filtro in-job). Assim o check **sempre reporta** → seguro pra required. Ordem por raio-de-dano:
1. Multi-tenant gate (Tier 0 — isolamento de dados)
2. Secrets Governance (LGPD — vazamento de credencial)
3. Memory schema gate (determinístico, barato)
4. PHPStan/Larastan (determinístico pós-#2294/#2300)
5. Governance Gate + Modules Pest / Financeiro Pest

**Alavanca 3 — `enforce_admins: true` (por último).**
Só depois das Alavancas 1-2 estáveis (~1 semana sem flaky), pra não trancar o L0 fora por gate piscando. Até lá, admin-bypass é a válvula de emergência. Break-glass documentado: toggle admin temporário via `gh api`.

**Nunca exigir (não-determinístico por design):** `PR UI Judge` (LLM Brain B), `Jana RAGAS Eval/Gate` (LLM eval), `Charter Gate (soft)`, `MWART Gate (soft)`. Virariam falso-vermelho bloqueando merge legítimo — ficam advisory.

## Consequências

**Positivas.** A catraca de qualidade (`module-grades-gate`) e os testes viram **lei mecânica**, não disciplina — "evolui sempre" deixa de depender de boa-vontade. Time MCP entra num trilho que **proíbe** regressão Tier-0, não só recomenda. Fecha a decisão-pendente de enforcement.

**Negativas / riscos.** (a) Gate flaky promovido a required trava **todos** os merges → só promover gates com histórico estável; LLM-eval nunca. (b) Bug no skip-as-pass → falso-verde → o step de skip reusa exatamente o filtro de path existente. (c) `enforce_admins` trancando hotfix → por último + break-glass. (d) `strict: true` (já ligado) + mais required = mais rebase — aceitável.

**Autoridade.** Branch protection é admin-only (L0 soberano). Esta decisão é Tier 0 e só Wagner aprova/reverte. Reversível em 1 comando (`gh api -X PATCH .../required_status_checks`).

## Alternativas consideradas

- **Agregador único (`CI` job com `needs:` todos os gates) → exigir só "CI".** Elegante, mas `ci.yml` hoje tem 2 jobs soltos sem `needs:`; reescrever pra agregador é refactor maior e cria ponto único de falha. Preterido em favor do faseamento incremental (cada gate promovido isoladamente, reversível um a um).
- **Exigir tudo de uma vez.** Rejeitado: deadlock dos path-scoped + risco de flaky travar o time inteiro sem rodagem.
- **Manter como estava (status quo).** Rejeitado: é a falha-mãe; sem tranca, a catraca é decorativa.
