---
slug: 2026-05-16-plano-ondas-governance-v4-scoped-scorecards
title: "Plano de ondas — Governance v4 Scoped Scorecards (meta ≥97 por bucket)"
type: session
date: 2026-05-16
authority: planning
lifecycle: ativo
audience: [W]
related_adrs: [0155, 0156, 0157, 0158, 0159, "0160-proposed"]
related_skills: [evolui, module-grades-gate, capterra-senior, avaliar-modulo]
tags: [governance, v4, scoped-scorecards, lenses, planejamento, ondas, roadmap]
---

# Plano de ondas — Governance v4 Scoped Scorecards

> **Decisão Wagner 2026-05-16:** aprovar Domain-Specific Maturity Scorecards (Lens per Module Kind) com **4 buckets** e meta por bucket — abandonar régua única + meta global 97.75.
>
> **Formato validado:** `memory/scorecards/vestuario.yaml` experimental aprovado (simulação 77/100 confere com rubrica v3 atual).
>
> **Próximo:** executar 6 waves canônicas que migram oimpresso de `module-grade-v3` (rubrica única) pra `module-grade-v4` (Scoped Scorecards).

---

## Resumo executivo

| Wave | Objetivo | Agents | ETA IA-pair | Bloqueia próxima? |
|---|---|---:|---|:---:|
| **W19** | ADR 0160 + 3 YAMLs experimentais restantes (1 por bucket) | 4 | ~2h | Sim |
| **W20** | Bucket assignment 34 módulos + Gate CI extension | 2 | ~1h | Sim |
| **W21** | ModuleGradeService v4 refactor (dual-mode v3+v4) | 1 | ~3h | Sim |
| **W22** | CAPTERRA-FICHA Sprint cross-projeto (V6/IA-Maturity/Governance-Maturity) | 12 | ~6h | Não (paralelo W23) |
| **W23** | Saturação por bucket — atingir meta cada bucket | 15-20 | ~6h | Não (paralelo W22) |
| **W24** | Paired indicators ativos + drift detection cron daily + AI-driven scorecard V2 baseline | 5 | ~3h | — |

**Total estimado:** ~21h IA-pair distribuídas em 6 sessões (1-2 por dia). Resultado esperado: **média projeto ≥92/100 ponderada por bucket** em ~2 semanas calendário.

---

## Wave 19 — Fundação Scoped Scorecards

**Objetivo:** ADR 0160 canônica + 3 YAMLs experimentais (1 por bucket faltante).

**Dependências:** vestuario.yaml já existe (validado Wagner 2026-05-16).

**Agents (4 paralelos, áreas isoladas):**

1. **Agent A — ADR 0160 oficial**
   - Path: `memory/decisions/0160-governance-v4-scoped-scorecards-buckets.md`
   - Conteúdo: Nygard format completo
     - Status proposed → accepted pela mesma sessão
     - supersedes_partially: [0155, 0159] (mantém 0156/0157/0158 erratas técnicas)
     - 4 buckets canônicos definidos
     - Meta por bucket (≥85/90/85/80)
     - Anti-gaming via paired indicators + label gate bucket-change-approved
   - Output: ADR + atualização `memory/decisions/_INDEX-LIFECYCLE.md`

2. **Agent B — governance.yaml** (bucket cross_cutting_infra)
   - Path: `memory/scorecards/governance.yaml`
   - Core: D1 25 + D8 8 = 33pts (igual)
   - Bucket dimensions (67pts):
     - C1 Auto-detection accuracy (12) — rubrica v3/v4 detecção heurística certa
     - C2 Anti-regression CI (12) — gate workflow + label override
     - C3 Reflexivity (10) — governance audita governance (módulo se auto-avalia bem)
     - C4 Docs vivos (10) — ADRs canônicas atualizadas
     - C5 Cobertura cross-projeto (13) — % módulos com baseline + drift detection
     - C6 Adoption time interno (10) — time MCP consome rubrica diariamente

3. **Agent C — jana.yaml** (bucket ai_central)
   - Path: `memory/scorecards/jana.yaml`
   - Core: D1 25 + D8 8 = 33pts
   - Bucket dimensions (67pts):
     - A1 Hallucination rate <5% (15) — Pest evals contra fixtures
     - A2 LLM cost per request (12) — OTel span `llm_cost_brl`
     - A3 Latency p99 RAG (10) — OTel + Meilisearch hybrid
     - A4 RAG recall@5 ≥80% (10) — golden questions ROTA LIVRE
     - A5 PII redaction antes LLM (10) — PiiRedactor wrap garantido
     - A6 Drift modelo (sentinel canary) (10) — eval semanal

4. **Agent D — crm.yaml** (bucket functional_horizontal)
   - Path: `memory/scorecards/crm.yaml`
   - Core: D1 25 + D8 8 = 33pts
   - Bucket dimensions (67pts):
     - F1 Pest cobertura ratio (15) — tests/Controllers ≥3
     - F2 Module reuse pattern (12) — Crm consumido por ≥2 outros módulos via Service
     - F3 D6 Perf padrão (10) — Inertia::defer + sem N+1
     - F4 D7 LGPD padrão (10) — retention + LogsActivity + PiiRedactor
     - F5 D3 Docs (10) — SPEC + BRIEFING + CHANGELOG
     - F6 D9 Observability (10) — OTel spans + Health command

**Tier 0 Wave 19:** ADR append-only · PT-BR · YAML versionado · zero git ops em agents (parent consolida).

**Output esperado:** 4 arquivos (1 ADR + 3 YAMLs). 1 PR mergeado. Sem código PHP ainda.

---

## Wave 20 — Bucket assignment cross-projeto

**Objetivo:** atribuir `governance.bucket` em `module.json` dos 34 módulos + extender Gate CI.

**Dependências:** Wave 19 mergeada (ADR + 4 YAMLs canon).

**Agents (2 paralelos):**

1. **Agent A — module.json bucket assignment**
   - Edita `Modules/<X>/module.json` adicionando:
     ```json
     {
       "governance": {
         "bucket": "vertical_client_facing",
         "bucket_assigned_at": "2026-05-17",
         "bucket_assigned_by": "[W]"
       }
     }
     ```
   - Distribuição:
     - **vertical_client_facing (5):** Vestuario, ComunicacaoVisual, OficinaAuto, Officeimpresso
     - **cross_cutting_infra (8):** Governance, Auditoria, Admin, Brief, TeamMcp, Superadmin, Connector
     - **ai_central (2):** Jana, KB
     - **functional_horizontal (19):** Crm, Financeiro, Repair, Ponto, RecurringBilling, NfeBrasil, NFSe, Manufacturing, Cms, Spreadsheet, Arquivos, Accounting, AssetManagement, Essentials, ADS, ConsultaOs, SRS, Whatsapp, Woocommerce, ProductCatalogue, ProjectMgmt
   - Output: 34 module.json editados + relatório de assignment com justificativa cada

2. **Agent B — Gate CI extension**
   - Edita `.github/workflows/module-grades-gate.yml`:
     - Detecta mudança em `Modules/<X>/module.json` `governance.bucket`
     - Exige label PR `bucket-change-approved`
     - Sem label → bloqueia merge com mensagem explicando
   - Cria `governance/buckets/_INDEX.md` listando 34 módulos × bucket
   - Output: workflow editado + INDEX

**Tier 0 Wave 20:** PR único · label aprovação obrigatória · audit trail bucket assignments.

---

## Wave 21 — ModuleGradeService v4 refactor

**Objetivo:** Service PHP carrega rubrica dinâmica do bucket via YAML em vez de hardcoded D1-D9 fixo.

**Dependências:** Wave 20 mergeada (todos módulos com bucket atribuído).

**Agent (1 sequencial — refactor complexo):**

- Edita `Modules/Governance/Services/ModuleGradeService.php`:
  - Novo método `loadScorecardForModule(string $module): array`:
    1. Lê `Modules/<X>/module.json` → pega `governance.bucket`
    2. Lê `memory/scorecards/<bucket>.yaml` (4 YAMLs canon)
    3. Merge core dims + bucket dims
  - Novo método `evaluateScorecard(string $module, array $scorecard): array`:
    1. Itera `core.D1, D8` + `bucket_dimensions.V1..V6`
    2. Cada rule executa `detect.tipo` (ast_scan, grep, ratio, file_exists, yaml_lookup, pest_pattern, etc)
    3. Acumula score + breakdown
  - Novo método `applyPairedIndicators(array $scores, array $pairs): array`:
    1. Aplica cap 50% se par velocidade/qualidade gameado
  - Backward-compat **dual-mode**:
    - Se `governance.v4_enabled=true` (config) → usa v4
    - Senão → usa v3 atual
    - Permite migração gradual módulo-por-módulo
- Cria `Modules/Governance/Tests/Feature/ModuleGradeServiceV4ScopedTest.php`:
  - Testa load YAML
  - Testa eval rules (mock fs)
  - Testa paired indicators
  - Testa dual-mode v3↔v4 sem regressão

**Tier 0 Wave 21:** dual-mode preserva v3 default · CI continua verde no caminho v3 · gate `governance.v4_enabled=false` em prod até validação completa.

---

## Wave 22 — CAPTERRA-FICHA Sprint cross-projeto (paralelo W23)

**Objetivo:** entregar FICHA por bucket — bucket vertical/AI/governance ganha FICHA própria.

**Dependências:** Wave 20 mergeada (buckets atribuídos).

**Agents (12 paralelos — 1 por módulo crítico):**

- **5 verticais** → CAPTERRA-FICHA.md (skill `capterra-senior` canon) — concorrentes top BR/global:
  - Vestuario vs Vendizap/Linx Microvix/ProMoz
  - ComunicacaoVisual vs Mubisys/Zênite/Calcgraf
  - OficinaAuto vs Mecânico/Auto Manager/Lokoz
  - Officeimpresso vs Bling/Tiny/Omie (sob ângulo gráfica)
  - Repair vs Lokoz/ConsertaTudo
- **2 AI central** → IA-MATURITY-FICHA.md (nova) — comparação Jana vs Vellum/OpenAI Custom GPT/LangSmith:
  - Jana vs reference assistants
  - KB vs Pinecone/Weaviate/Chroma (gestão conhecimento)
- **5 cross-cutting** → GOVERNANCE-MATURITY-FICHA.md (nova) — comparação vs Backstage/Port/OpsLevel pattern:
  - Governance vs Port (próprio módulo se auto-avalia)
  - Auditoria vs OpenAudit/AuditBoard
  - Admin vs InfoSec dashboards
  - Brief vs Notion/Linear (briefing inteligente)
  - TeamMcp vs Backstage Tech Insights

**Output esperado por agent:** 1 FICHA.md + nota 0-100 + top 5 gaps priorizados + roadmap CONSOLIDAR vs EVOLUIR.

**Tier 0 Wave 22:** áreas isoladas (cada agent 1 módulo) · sem código (só docs) · sem PII real em comparações.

---

## Wave 23 — Saturação por bucket (paralelo W22)

**Objetivo:** atingir meta de cada bucket. Cross-projeto.

**Dependências:** Wave 21 mergeada (Service v4 ativo dual-mode).

**Agents (15-20 paralelos por bucket):**

- **Bucket vertical_client_facing meta 85:**
  - 5 agents (1 por módulo) saturação V1-V6 conforme YAML
  - Foco: V6 Capterra gap (depende W22 entrega) + V3 Perf + V4 LGPD
- **Bucket cross_cutting_infra meta 90:**
  - 7 agents — saturação C1-C6
- **Bucket ai_central meta 85:**
  - 2 agents — saturação A1-A6 (Jana hallucination, RAG recall)
- **Bucket functional_horizontal meta 80:**
  - 6-10 agents (batched) — saturação F1-F6

**Tier 0 Wave 23:** áreas isoladas · OtelHelper canônico · biz=99 fake · ADR 0093.

---

## Wave 24 — Paired indicators + Drift detection + AI-driven baseline

**Objetivo:** ativar mecanismos canon que faltam.

**Dependências:** Wave 23 mergeada (todos buckets ≥ meta).

**Agents (5 paralelos):**

1. **Agent A — Paired indicators enforcement**
   - ModuleGradeService aplica `paired_indicators` cap 50%
   - Pest tests cobrindo cada par
2. **Agent B — Drift detection cron**
   - Comando artisan `governance:scorecard-snapshot --bucket=<X>` daily 07h BRT
   - Tabela `mcp_scorecard_runs` (sha, module, bucket, score, breakdown)
   - Alert se módulo cair >5pts vs snapshot anterior
3. **Agent C — Dashboard ranking intra-bucket**
   - `/copiloto/admin/governance/v4` mostra ranking por bucket + percentile
   - Trend line últimos 30 dias
4. **Agent D — AI-driven scorecard V2 baseline**
   - Skill `oimpresso-scorecard-ai` (Tier B) — LLM lê código + YAML + sugere ajuste de score
   - Roda em modo READ-ONLY primeiro (não bloqueia)
   - Coleta 30 dias baseline antes de virar source-of-truth
5. **Agent E — Aposentar 3 dos 4 hacks ADR 0159**
   - `internal_governance_active` virou bucket cross_cutting_infra → hack redundante
   - `fsm_n_a:true` via module.json → continua válido mas integrado
   - CHANGELOG ≤7d → vira regra V5.c bucket-aware
   - Cria ADR 0161 retirando erratas conforme bucket cobre

**Tier 0 Wave 24:** AI READ-ONLY 30d antes de bloquear · drift alert via mcp_alertas (canal existente) · dashboard com filtro por bucket.

---

## Cronograma sugerido (calendário real)

| Dia | Wave | Duração | Resultado |
|---|---|---|---|
| **Hoje noite (2026-05-16)** | W19 | 2h | ADR 0160 + 3 YAMLs canon |
| **2026-05-17 manhã** | W20 | 1h | 34 módulos com bucket + Gate CI |
| **2026-05-17 tarde** | W21 | 3h | Service v4 dual-mode |
| **2026-05-18 manhã** | W22 | 6h | 12 FICHAs (CAPTERRA + IA-MATURITY + GOVERNANCE-MATURITY) |
| **2026-05-18-19** | W23 | 6h | Saturação cross-projeto (15-20 agents) |
| **2026-05-19-20** | W24 | 3h | Paired + Drift + AI baseline |

**~7 dias calendário pra rubrica v4 LIVE com média ≥92 por bucket.**

---

## Riscos catalogados pré-execução

| Risco | Mitigação |
|---|---|
| Wagner muda escopo bucket mid-flight | ADR 0160 versionada — mudança = ADR errata, não silent |
| Agent assigna bucket errado (W20) | Wagner review PR W20 — 1 review pra aprovar/ajustar 34 assignments |
| Service v4 dual-mode bug em prod | Gate `governance.v4_enabled=false` default · canary só prod CT 100 antes Hostinger |
| CAPTERRA-FICHA Sprint demora >6h | Batch reduzir scope (só 5 verticais primeiro, deixar AI/Cross-cutting Wave 25) |
| Saturação W23 trava em alguma dim | Paired indicators cap 50% impede inflação · Wagner aceita 80% bucket em vez de 100% |
| AI-driven (W24) alucina score | READ-ONLY 30d antes de bloquear merge — Wagner valida |

---

## Próxima ação (esperando Wagner aprovar)

Wagner pode aprovar:

- **(A)** "Começa W19 agora" → disparo 4 agents Opus paralelos pra ADR 0160 + 3 YAMLs
- **(B)** "Pausa, quero ajustar bucket X" → aguardo ajuste antes W19
- **(C)** "Mudar ordem — W22 antes W21" → re-calendário

Default: opção A se Wagner não responder diferente.
