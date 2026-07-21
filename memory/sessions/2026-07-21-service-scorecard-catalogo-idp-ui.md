---
date: "2026-07-21"
hour: "23:30 BRT"
topic: "Entrega dos 2 itens 'atrás' da sub-dimensão Catálogo/IDP: scorecard de sinais-vivos por serviço (Cortex) + UI consultável (aba na ModuleGrades). Re-grade honesta 7,5 → at-par."
authors: ["C"]
tags: [catalogo-idp, scorecard, sinais-vivos, backstage, cortex, service-scorecard, module-grades, mwart-hook, reguas, grade]
outcomes:
  - "service-scorecard.mjs: AGREGADOR advisory que junta grade+telas+grafo+briefing por serviço a partir das fontes JÁ derivadas — NÃO recalcula (module-grade é o dono da nota)."
  - "UI consultável: aba 'Catálogo & Sinais' em /governance/module-grades (team-facing, Inertia::defer, tokens semânticos, read-only)."
  - "Hook block-mwart-violation ganhou fallback charter-first (aceita RUNBOOK nome-pela-rota declarado no charter) — conserta falso-positivo em TODA página aninhada."
  - "Re-grade Catálogo/IDP: 7,5 → at-par com Backstage+Cortex nos 2 eixos nomeados. Diferencial = instanciação+recursão, NÃO invenção (§5 falácia-de-composição respeitada)."
---

# Catálogo/IDP: scorecard de sinais-vivos + UI consultável (entrega + re-grade)

## TL;DR

A sub-dimensão **Catálogo/IDP** (barra = Backstage+Cortex) listava 3 itens "atrás": grafo tipado (✅ entregue #4629), **scorecard de sinais-vivos** e **UI consultável**. Esta sessão entrega os 2 restantes — um **agregador advisory por serviço** (`service-scorecard.mjs` → `service-scorecard.json`) e uma **aba consultável** na tela de Module Grades. Ambos **derivados** (ADR 0256) e **advisory** (ADR 0314/0275). Re-grade honesta: **7,5 → at-par** nos eixos nomeados (não "acima" — Cortex tem service scorecards, Backstage tem catalog UI; o recorte raro é a instanciação dentro de um ERP vertical multi-tenant, não a invenção).

## Contexto

Pedido [W]: fechar a lacuna 7,5→topo da sub-dimensão Catálogo/IDP da grade de réguas, nos 2 itens nomeados. Restrição dura: **integrar, não duplicar** — reusar `catalog.json` (#4629), `module-surface.mjs`, `module:grade` (baseline), `vital-signs.json`; não inventar métrica redundante com module-grade (§5 proibicoes).

## O que foi entregue

### 1. Scorecard de sinais-vivos (`scripts/governance/service-scorecard.mjs`)

**AGREGADOR advisory, não régua nova.** Declaração no cabeçalho (disciplina do `doc-freshness-score.mjs` "AGREGADOR ≠ DENTE"): NÃO inventa nota de qualidade — REUSA a do `module-grade` (baseline `governance/module-grades-baseline.json`, ADR 0155). Junta, por serviço (espinha = os 36 do `catalog.json`):

| Sinal | Fonte (já derivada) | Dono do veredito |
|---|---|---|
| Nota de qualidade | `module-grades-baseline.json` (join por nome, exato) | module:grade (ADR 0155) |
| Telas (nota/charter%/casos%/stale) | `vital-signs.json` (join PAGES_NS + normalização EXATA) | mv-metabolismo / screen-coverage |
| Grafo (depends_on/provides_api/tabelas/adr) | `catalog.json` edges | catalog-graph |
| Frescor do BRIEFING | git %cs de `memory/requisitos/<Mod>/BRIEFING.md` | briefing-code-staleness |

- **Custo por-PR**: marcado `module_attributable:false` — gap honesto (não inventar atribuição; §5 2026-07-17).
- **Join honesto**: grade casa exato; tela casa por PAGES_NS e, só se falhar, por **normalização EXATA** (TeamMcp↔team-mcp — mesmo nome, casing/hífen; NÃO por similaridade, Crm↔Cliente fica órfão honesto). Backend-only = n/a (não falha). 16 namespaces órfãos (core-app: Sells/Produto/Cliente/…) reportados, não escondidos.
- **Checks de maturidade** = presença/conexão de catálogo (Backstage/Cortex maturity), **não** correção (presença ≠ correção, L-24). Nível ouro/prata/bronze só sobre checks aplicáveis.
- Determinístico (data de commit, não age_days volátil) → `--json`/`--check` estáveis. `--write` grava `memory/governance/service-scorecard.json`.
- Teste puro com fixtures sintéticas (`service-scorecard.test.mjs`, 8 casos, registrado no CI `governance-script-tests.yml`). Nightly: `mv-metabolismo.yml` regenera após o vital-signs (mesma cadência de commit = merge [W]).

Retrato atual: **36 serviços · 36 com nota · maturidade 🥇20 🥈15 🥉1**.

### 2. UI consultável — aba "Catálogo & Sinais" (extensão de `/governance/module-grades`)

Decisão [W]: **estender ModuleGrades** (team-facing via `auth`, não Wagner-only como o GovernanceV4Dashboard em `admin.oimpresso.com`) em vez de rota nova. Toggle de visão (Notas | Catálogo & Sinais); a aba lê a prop `catalog` (deferida) que agrega `service-scorecard.json` + relações `depends_on`/`dependents` do `catalog.json`. Read-only, tokens semânticos, cross-link pro drill-down `/governance/module-grades/{name}`. Sem `business_id`/dado de negócio → Tier 0 não morde o dado. Charter + Pest test (cenário 5a/5b/5c) atualizados.

### 3. Fix colateral — hook `block-mwart-violation` (charter-first fallback)

O edit da `ModuleGrades/Index.tsx` foi bloqueado: o hook deriva `RUNBOOK-index.md` (nome pela tela), mas o RUNBOOK real é `RUNBOOK-module-grades.md` (nome pela rota, declarado no charter) — falso-positivo **sistêmico** em página aninhada (`Show.tsx` tinha o mesmo bug latente). [W] escolheu o root-cause: o hook agora aceita **também** o `runbook:` declarado no `<Tela>.charter.md`, validando que o arquivo existe (charter mentiroso NÃO fura o gate — caso-ataque testado). 26 casos verdes.

## Re-grade honesta (Catálogo/IDP · barra Backstage+Cortex)

| Antes (2026-07-21 grade) | Depois (esta entrega) |
|---|---|
| **7,5** · atrás: grafo tipado, scorecard de sinais-vivos, UI consultável | **~8,5 · at-par** nos 3 eixos nomeados |

**Honestidade (§5, não re-inflar):** isto é **at-par**, não "acima". Cortex tem service scorecards; Backstage tem software catalog + UI; ambos têm graph-native relations. **Todo componente tem peer** — o diferencial é **instanciação + recursão** (a pilha montada DENTRO de um ERP vertical multi-tenant BR em produção, derivada dos SCOPE.md, advisory-de-nascença, o agente citando o próprio §5), **não invenção**. Alegar superioridade de categoria reabriria a lápide §5 "claims REFUTADAS".

**Residual honesto (o que ainda separa de surpassar):**
- Sem integração on-call/SLO/incident (Cortex liga scorecard a PagerDuty/alertas; aqui os sinais são de código/doc/tela, não de runtime de produção).
- Sem marketplace de plugins (Backstage); o catálogo é fechado no repo.
- Custo por-módulo é gap honesto (per-PR, não atribuído).
- Os "níveis de maturidade" são presença/conexão — navegabilidade, não qualidade (por design; a qualidade é do module-grade).

## Arquivos

- `scripts/governance/service-scorecard.mjs` + `.test.mjs` + `memory/governance/service-scorecard.json` (gerado)
- `.github/workflows/governance-script-tests.yml` (registra o teste) + `mv-metabolismo.yml` (nightly + auto-PR)
- `Modules/Governance/Http/Controllers/ModuleGradeController.php` (`buildCatalogSignalsPayload`) + `Tests/Feature/ModuleGradeControllerTest.php` (cenário 5)
- `resources/js/Pages/governance/ModuleGrades/Index.tsx` + `Index.charter.md`
- `.claude/hooks/block-mwart-violation.mjs` + `.test.mjs` (fallback charter-first)

## PRs

- **Hook fix** (governance enforcement, isolado): `block-mwart-violation` charter-first fallback.
- **Feature Catálogo/IDP** (data layer + workflows + UI + charter + Pest + este log).

Merge = [W] (R10). Smoke visual real = pós-merge (R1, [W]-gated) — o app governance autenticado não roda local (disciplina CT 100).
