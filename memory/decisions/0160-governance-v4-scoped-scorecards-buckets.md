---
slug: 0160-governance-v4-scoped-scorecards-buckets
number: 160
title: "module-grade-v4 — Scoped Scorecards (Lens per Module Kind) com 4 buckets + meta por bucket"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: "2026-05-16"
accepted_at: 2026-05-16
review_at: 2026-08-16
module: Governance
quarter: 2026-Q2
tags: [governance, v4, scoped-scorecards, lenses, buckets, meta-por-bucket, anti-gaming, score-as-code]
supersedes: []
supersedes_partially: [0155-module-grade-v3-sub-dimensoes-gate-ci, 0159-module-grade-v3-errata-meta-97-realismo]
superseded_by: []
related: [0155-module-grade-v3-sub-dimensoes-gate-ci, 0156-module-grade-v3-errata-otel-helper-na-justified, 0157-module-grade-v3-d2-detection-hardening, 0158-module-grade-v3-d1-heuristica-hardening, 0159-module-grade-v3-errata-meta-97-realismo, 0093-multi-tenant-isolation-tier-0, 0094-constituicao-v2-7-camadas-8-principios, 0105-cliente-como-sinal-guiar-sem-mandar]
pii: false
review_triggers:
  - Quando 3+ módulos abusarem mudança de bucket sem label aprovação (gaming)
  - Quando AI-driven scorecard (Wave 24) acumular 30 dias baseline (ativar V2)
  - Quando time MCP crescer >5 pessoas (validar se 4 buckets ainda cobrem; pode precisar 5 ou 6)
  - Quando média de algum bucket bater meta+10pts consistente 2 quarters (sinal pra elevar barra do bucket)
---

# ADR 0160 — module-grade-v4: Scoped Scorecards (Lens per Module Kind) com 4 buckets + meta por bucket

## Contexto

A rubrica `module-grade-v3` ([ADR 0155](0155-module-grade-v3-sub-dimensoes-gate-ci.md) + erratas técnicas [0156](0156-module-grade-v3-errata-otel-helper-na-justified.md) / [0157](0157-module-grade-v3-d2-detection-hardening.md) / [0158](0158-module-grade-v3-d1-heuristica-hardening.md) + errata de realismo [0159](0159-module-grade-v3-errata-meta-97-realismo.md)) opera como **régua única de 9 dimensões × 100 pts normalizado** aplicada a todos os 34 módulos do projeto.

**Diagnóstico Wave 18 (2026-05-16):**

- Média atual: ~88/100 (pós-erratas 0156-0159; antes era ~71)
- Teto natural prático: ~88-92 mesmo nos módulos mais maduros (Governance, Jana)
- Meta Wagner ≥97.75: **inalcançável estruturalmente** com régua única — não importa o quanto se aperte D1-D9, módulos cross-cutting/AI/funcionais vão sempre carregar dimensões legitimamente irrelevantes pra sua natureza (cliente real em infra? FSM em consultivo? Inertia::defer em REST puro?)
- Erratas 0159 já compensaram 4 pontos cegos (D5 cross-cutting, D9.b ready-mode, D4.b FSM N/A, D3.b CHANGELOG fresh) — mas são **patches** acumulados sobre uma régua estruturalmente inadequada pra heterogeneidade do projeto

**Estado da arte 2026 (Domain-Specific Maturity Scorecards):**

Pesquisa em `memory/sessions/2026-05-16-arte-domain-specific-scorecards.md` e `memory/sessions/2026-05-16-arte-scorecards-alta-2026-benchmark.md` consolida:

- **Port ($100M Series B 2026):** Internal Developer Portal com scorecards segmentados por tipo de serviço — abandonou métrica única
- **Cortex Wrapped 2026:** dashboards por persona (Eng Manager, Platform Lead, SRE) com lenses próprias
- **OpsLevel case-study:** customer levou média 22→89% em 8 semanas após adotar scorecards segmentados por bucket (era 35→55% travada com régua única)
- **Backstage adoption real:** apenas ~10% de plugins de scorecard ativados em prod nas empresas que adotaram — sintoma clássico de "métrica única não cabe"
- **Jellyfish 2025 (Paired Indicators):** anti-gaming validado — cada métrica de velocidade tem par de qualidade que cap-eia o score se par quebrado

**Experimento prévio aprovado:**

`memory/scorecards/vestuario.yaml` (formato canônico validado) — pivot do bucket `vertical_client_facing` aplicando lens própria pra módulos vendáveis ao cliente final. Wagner aprovou formato em 2026-05-16.

**Princípio:** loop fechado por métrica (Constituição v2 §4 — [ADR 0094](0094-constituicao-v2-7-camadas-8-principios.md)) com **régua adequada ao objeto medido**. Régua única é simplicidade falsa que produz teto artificial.

## Decisão

Aceitar **`module-grade-v4`** como rubrica canônica oficial, **substituindo parcialmente v3 ([ADR 0155](0155-module-grade-v3-sub-dimensoes-gate-ci.md)) e errata realismo ([ADR 0159](0159-module-grade-v3-errata-meta-97-realismo.md))**:

- **Abandona régua única** + meta global única ≥97.75
- **Adota 4 buckets canônicos** + **meta por bucket**
- **Core compartilhado D1+D8 = 33pts universais** (multi-tenant + security são Tier 0 pra qualquer módulo)
- **Bucket dimensions próprias 67pts** cada (lens específica)
- **Paired indicators anti-gaming** (Jellyfish 2025 pattern)
- **Score-as-code:** rubrica vive em `memory/scorecards/<bucket>.yaml` versionada no git

### 4 buckets canônicos

| Bucket | Membros (34 módulos) | Meta | Justificativa |
|---|---|---|---|
| `vertical_client_facing` | Vestuario, ComunicacaoVisual, OficinaAuto, Officeimpresso, Repair | **≥85** | Módulos vendáveis ao cliente final · diferencial competitivo · UX/sinal cliente pesa muito |
| `cross_cutting_infra` | Governance, Auditoria, Admin, Brief, TeamMcp, Superadmin, Connector | **≥90** | Infra crítica · sem cliente externo · Tier 0 multi-tenant + observability mais rígido |
| `ai_central` | Jana, KB | **≥85** | IA com memória persistente · grounding + custo · OTel GenAI obrigatório |
| `functional_horizontal` | Crm, Financeiro, Repair, Ponto, RecurringBilling, NfeBrasil, NFSe, Manufacturing, Cms, Spreadsheet, Arquivos, Accounting, AssetManagement, Essentials, ADS, ConsultaOs, SRS, Whatsapp, Woocommerce, ProductCatalogue, ProjectMgmt (20 módulos) | **≥80** | Núcleo funcional reusável · profundidade variável · meta intermediária realista |

> **Nota:** `Repair` aparece em 2 buckets (vertical client-facing pra Officeimpresso/ComunicacaoVisual + functional horizontal compartilhado). Wave 20 resolve via campo `governance.bucket` em `module.json` declarado por módulo — não é dupla contagem; bucket é decisão única por módulo.

### Mecanismo técnico

1. **`module.json` ganha campo `governance.bucket`** (declarativo, auditável no PR):
   ```json
   {
     "name": "Vestuario",
     "governance": { "bucket": "vertical_client_facing" }
   }
   ```
2. **`ModuleGradeService v4`** lê `module.json` → resolve bucket → carrega `memory/scorecards/<bucket>.yaml` → avalia D1+D8 (core compartilhado) + dims do bucket (67pts próprios)
3. **Gate CI** exige label `bucket-change-approved` em PR que mude `governance.bucket` (anti-gaming Wagner-aprovado)
4. **Dual-mode** `governance.v4_enabled=false` default · canary CT 100 antes de ativar Hostinger (compatibilidade backward com v3 enquanto rollout acontece)

### Meta agregada (substitui meta global única)

- **Abandona** meta global única 97.75 (régua errada)
- **Reporta agregado por bucket:** ex `"Vertical 92% / CrossCutting 88% / AI 86% / Functional 84%"`
- **Meta aspiracional:** ≥95 por bucket em 2 quarters (alinhado com case OpsLevel 22→89% em 8sem documentado em [arte 2026](../sessions/2026-05-16-arte-scorecards-alta-2026-benchmark.md))
- **Dashboard `/admin/governance/scorecards`** (Wave 22) renderiza 4 colunas + delta por sprint

### Anti-gaming

- **Paired indicators** dentro de cada bucket YAML — cap automático em 50% da nota da par se par velocidade/qualidade quebrado (ex: cobertura Pest alta + zero docs CHANGELOG = cap)
- **Gate CI label-required** pra `bucket-change` → mudança de bucket é decisão arquitetural, não setting ad-hoc
- **Score-as-code** → mudança de rule = PR review (não edita Service PHP, edita YAML versionado)
- **Review triggers** ativos no frontmatter (3+ mudanças de bucket por quarter → revisar ADR; média bater meta+10pts → elevar barra)

### Compatibilidade com ADRs anteriores

- **ADR 0155** (v3 base teórica): mantém status `accepted` — base conceitual das 9 dimensões e auto-detect heurísticos PERMANECE válida. Supersedida **parcialmente** em (a) régua única → buckets, (b) meta global → meta por bucket, (c) pesos fixos → pesos por lens YAML
- **ADRs 0156/0157/0158** (erratas técnicas): **permanecem 100% válidas** — detecção heurística OtelHelper / D2 hardening / D1 recursivo segue ativa no Service v4 (core D1+D8 universal usa essas heurísticas)
- **ADR 0159** (errata realismo): **3 dos 4 hacks ficam redundantes** com v4:
  - D5 `internal_governance_active` → resolvido por bucket próprio `cross_cutting_infra` (lens não exige cliente externo)
  - D4.b `fsm_n_a` via `module.json` → resolvido por lens bucket-aware (cross-cutting/AI não pontuam FSM; functional/vertical sim)
  - D3.b CHANGELOG ≤7d → resolvido por dim própria de freshness em cada YAML de bucket
  - **Único hack que permanece:** D9.b `query_failed_jobs` ready-mode default `true` — será resolvido Wave 24 quando OTel collector estiver ativo em prod (D9.b vira hard check real)

## Roadmap (6 waves canônicas)

Plano completo em `memory/sessions/2026-05-16-plano-ondas-governance-v4-scoped-scorecards.md`. Resumo:

| Wave | Entrega | Quando |
|---|---|---|
| **Wave 19** (HOJE) | ADR 0160 oficial + 4 YAMLs canônicos (vertical/cross-cutting/AI/functional) + Service skeleton + module.json schema | 2026-05-16 |
| **Wave 20** | Popular `governance.bucket` em 34 `module.json` · 7 cross-cutting + 5 vertical + 2 AI + 20 functional | 2026-05-17 |
| **Wave 21** | `ModuleGradeService v4` completo + Pest + gate CI label-required + paired indicators 1ª onda | 2026-05-18 |
| **Wave 22** | Dashboard `/admin/governance/scorecards` 4 colunas + delta sprint + drill-down por bucket | 2026-05-19 |
| **Wave 23** | Canary CT 100 v4_enabled=true · validar 5 módulos · ajustar lens YAMLs | 2026-05-20 |
| **Wave 24** | Rollout Hostinger v4_enabled=true · sunset v3 metric (mantém ADRs canônicas) · ativar AI-driven scorecard baseline | 2026-05-23 |

## Consequências

### Positivas

- **Meta atingível por bucket** (85-90 realista) substitui meta global inatingível (97.75 inflacionada)
- **Heterogeneidade respeitada** — infra não é medida com régua de produto vendável; AI não é medida com régua de CRUD
- **Anti-gaming desde dia 1** — paired indicators + gate CI + score-as-code formam 3 camadas defensivas
- **Alinhado com estado da arte 2026** — Port/Cortex/OpsLevel/Backstage convergiram em scorecards segmentados; oimpresso adota pattern validado por mercado
- **ADRs canônicas anteriores preservadas** — append-only respeitado · v3 e erratas seguem como base teórica de auto-detect
- **Score-as-code** — mudança de standard é PR no YAML, time vê no diff (não bate "mudou nota porque Service mudou")

### Negativas (mitigadas)

- **Comparabilidade cross-bucket quebrada por design** — não dá pra dizer "Vestuario 88 > Brief 85" (são réguas diferentes). **Mitigação:** agregado por bucket vira métrica primary; comparações intra-bucket são canônicas
- **Manutenção 4 YAMLs vs 1 rubrica** — 4× superficie de manutenção. **Mitigação:** core D1+D8 compartilhado é DRY (33pts universais); lens são chunks pequenos 67pts cada
- **Time MCP precisa entender 4 lenses** — onboarding mais complexo. **Mitigação:** skill `bucket-assign-helper` Tier C explica decisão em 1 prompt; cada YAML tem header `## Como usar essa lens`
- **Gaming via bucket-change** — módulo medíocre tenta mudar de bucket pra escapar barra alta. **Mitigação:** gate CI label `bucket-change-approved` + review_trigger ADR 0160 (3+ mudanças sem aprovação → revisar)

### Neutras

- Backward-compat parcial — v3 Service continua funcionando enquanto `governance.v4_enabled=false`; rollout gradual
- v3 ADRs (0155/0156/0157/0158/0159) **não viram superseded** (status `accepted` mantido) — apenas `supersedes_partially` registra evolução
- Wave 24 ativa AI-driven scorecard baseline (30 dias) — V2 futura (ADR 016x) pode promover lens learned

## Alternativas consideradas

1. **Manter v3 + apertar mais 4 erratas pra fechar 97.75** — Wagner rejeitou (caminho de patches infinitos sem resolver raiz; teto natural ~88-92 confirmado empiricamente Wave 18)
2. **Rebaixar meta global 97.75 → 88** — não resolve heterogeneidade · módulos cross-cutting continuam medidos com régua errada (cliente externo, FSM, defer) · vira "média baixa intencional" sem signal de saúde
3. **Adotar Backstage scorecards plugin completo (vendor)** — 10% adoption real em mercado (sintoma de bloat) · Backstage exige infra Node própria · oimpresso já tem Service PHP + dashboard Inertia · não compensa
4. **OpsLevel SaaS** — 200USD/dev/mês × time MCP futuro (5 pessoas) = ~12k/ano · score-as-code próprio sai por zero dólar de SaaS (apenas custo dev de Service v4)
5. **5 ou 6 buckets em vez de 4** — over-engineering pra 34 módulos · review_trigger ativa se precisar evoluir (>5 pessoas time MCP ou drift detectado)
6. **AI-driven scorecard direto (skip v4 manual)** — Wave 24 ativa baseline · pular régua humana → AI sem ground truth = ruído · V4 manual gera baseline pra V2 AI calibrar contra

## Implementação (Wave 19 — 2026-05-16)

Esta ADR é o **gatilho oficial**. Artefatos paralelos da Wave 19 (4 agents):

- **Agent A (este ADR)** — `memory/decisions/0160-governance-v4-scoped-scorecards-buckets.md` + entry em `_INDEX-LIFECYCLE.md`
- **Agent B** — 4 YAMLs canônicos:
  - `memory/scorecards/_core.yaml` (D1+D8 compartilhado 33pts)
  - `memory/scorecards/vertical_client_facing.yaml` (lens 67pts)
  - `memory/scorecards/cross_cutting_infra.yaml` (lens 67pts)
  - `memory/scorecards/ai_central.yaml` (lens 67pts)
  - `memory/scorecards/functional_horizontal.yaml` (lens 67pts)
- **Agent C** — `ModuleGradeService v4` skeleton + dual-mode `governance.v4_enabled` flag + carregador YAML + Pest baseline (sem rollout ainda)
- **Agent D** — `module.json` schema doc + skill `bucket-assign-helper` (Tier C) + RUNBOOK migração v3→v4

Waves 20-24 ([roadmap acima](#roadmap-6-waves-canônicas)) cobrem populate, Service completo, dashboard, canary, rollout.

## Sources

### ADRs relacionadas

- [ADR 0155](0155-module-grade-v3-sub-dimensoes-gate-ci.md) — v3 base (9 dimensões × 100 pts normalizado)
- [ADR 0156](0156-module-grade-v3-errata-otel-helper-na-justified.md) — D9.a OtelHelper + na_justified backward-compat (permanece válida)
- [ADR 0157](0157-module-grade-v3-d2-detection-hardening.md) — D2 hardening (permanece válida)
- [ADR 0158](0158-module-grade-v3-d1-heuristica-hardening.md) — D1 hardening recursivo (permanece válida)
- [ADR 0159](0159-module-grade-v3-errata-meta-97-realismo.md) — errata realismo (3/4 hacks ficam redundantes com v4)
- [ADR 0093](0093-multi-tenant-isolation-tier-0.md) — multi-tenant Tier 0 IRREVOGÁVEL (core D1)
- [ADR 0094](0094-constituicao-v2-7-camadas-8-principios.md) — Constituição v2 mãe (princípio 4 loop fechado por métrica)
- [ADR 0105](0105-cliente-como-sinal-guiar-sem-mandar.md) — cliente como sinal qualificado (base de lens `vertical_client_facing`)

### Estado da arte 2026 (research canônica)

- `memory/sessions/2026-05-16-arte-domain-specific-scorecards.md` — Port $100M / Cortex Wrapped / Backstage 10% adoption / OpsLevel positioning
- `memory/sessions/2026-05-16-arte-scorecards-alta-2026-benchmark.md` — case OpsLevel 22→89% em 8sem / Jellyfish 2025 paired indicators / score-as-code Spotify Backstage

### Artefato experimental aprovado

- `memory/scorecards/vestuario.yaml` — pivot bucket `vertical_client_facing` (formato canônico Wagner-aprovado 2026-05-16)

### Plano operacional 6 waves

- `memory/sessions/2026-05-16-plano-ondas-governance-v4-scoped-scorecards.md` — Waves 19-24 detalhadas
