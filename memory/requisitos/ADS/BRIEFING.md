# BRIEFING — ADS (Adaptive Decision System)

> Estado consolidado · 1 página executiva · atualizado por PR (skill `brief-update`).
> **Status: DORMANT pending qualified signal** ([ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md))

## Identidade

**O que é:** Meta-sistema que orquestra agentes (Brain A local Ollama / Brain B Anthropic / Humano Wagner) sobre eventos do codebase. Decide *quem* age, *com qual autoridade* (HITL 4 níveis), e mantém memória append-only pra learning loop.

**O que NÃO é:** Não executa código de domínio direto. Não substitui Copiloto/Jana. Não fala com cliente final.

## Por que dormente

ADR 0105 — **Cliente como sinal qualificado**: backlog só ativa se cliente paga + reporta dor OU métrica detecta drift. ADS não tem cliente externo reportando; serve hoje como infraestrutura de governança interna (Wagner-only). Mantida com Pest + governança canônica pra não regressar.

**Ativação esperada (~jul/2026):** quando ADS Universal (S5) for ativado para o time MCP (Felipe/Maiara/Eliana/Luiz) submeter eventos reais de evolução de código.

## Capacidades atuais (prod-ready, sem volume)

| Capacidade | Status | Cobertura |
|---|---|---|
| PolicyEngine firewall (4 outcomes: BLOCK/HUMAN/BRAIN_B/BRAIN_A) | ✅ código + 7 tests Unit | ARQ-0006 |
| RiskEngine score 0-1 com priors | ✅ código + Unit | ARQ-0004 |
| ConfidenceEngine janela 30d | ✅ código + Unit | ARQ-0005 |
| DecisionRouter matriz Risk × Confidence | ✅ código + Unit | ARQ-0003 |
| Decision Memory append-only (`mcp_dual_brain_decisions`) | ✅ schema + biz_id FK | ARQ-0009 |
| HITL 4 níveis (Inbox `/ads/admin/decisoes`) | ✅ UI read-only | ARQ-0008 |
| Brain B service (`BrainBService.php` + `laravel/ai`) | ✅ código (sem fluxo prod) | ARQ-0002 |
| Brain A daemon Node.js CT 100 + Ollama | 🟡 código pronto, sem volume | ARQ-0011 |
| Learning Loop L1/L2/L3 | 🟡 schema pronto, sem dados | ARQ-0007 |

## Gaps cobertos por este PR (D1 + D5 do audit 41/100)

- **D1** (cross-tenant tests Unit→Feature): `Feature/MultiTenantDecisionTest`, `Feature/BrainBIsolationTest` — biz=1 vs biz=99
- **D5** (smoke + scaffold sanity): `Feature/SmokeRoutesTest` (13 rotas), `Feature/ScaffoldTest` (Module::find + Install ADR 0024)

## Arquitetura (topologia ARQ-0011)

| Camada | Onde roda |
|---|---|
| App web ADS (UI Inbox, PolicyEngine, RiskEngine, ConfidenceEngine, Router, Decision Memory) | **Hostinger** (shared) |
| Brain A daemon (Node.js + Ollama qwen2.5-coder:14b) | **CT 100 Proxmox** |
| Brain B (Sonnet/Opus via `laravel/ai`) | **Anthropic API** (chamado pelo cron Hostinger) |

## Multi-tenant Tier 0 ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md))

- `mcp_dual_brain_decisions.business_id` NOT NULL + FK → `business.id` ON DELETE CASCADE
- `mcp_confidence_scores`, `mcp_decision_patterns`, `mcp_file_locks` todos com `business_id`
- Tests biz=1 (Wagner) vs biz=99 (fictício) — NUNCA biz=4 (ROTA LIVRE produção, ADR 0101)

## Operação humana

- Wagner é o único usuário hoje (HITL Nível 4 — approve/reject explícito Inbox)
- Time MCP (Felipe/Maiara/Eliana/Luiz) entrará quando ADS Universal ativar
- Comando `ads:process-brain-b` (cron Hostinger) — dormente até sinal

## ADRs governantes

| ADR | Tema |
|---|---|
| ARQ-0001..0011 | Arquitetura Dual-Brain canônica (PolicyEngine/RiskEngine/ConfidenceEngine/Router/HITL/Memory) |
| [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) | Multi-tenant Tier 0 IRREVOGÁVEL |
| [ADR 0094](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md) | Constituição v2 (tiered cost + SoC) |
| [ADR 0101](../../decisions/0101-tests-business-id-1-nunca-cliente.md) | Tests biz=1 (nunca cliente real) |
| [ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md) | **Cliente como sinal — ADS dormente** |

## Quando reativar

Gatilho objetivo: time MCP submete ≥10 eventos/semana via `EvolutionAgent` OU Wagner detecta drift codebase (test failures cross-PR).

Até lá: manter Pest verde, não adicionar features novas (ADR 0105 — feature-wish vira ADR, não US ativa).

## Wave 18 Saturação (2026-05-16)

**Meta:** 97/100 module-grade (D5 +12, D8 +6, D6 +4, D1 +5).

| Δ | Entrega |
|---|---|
| D5 +7→10 | Yaml `module_clients.yaml` ADS: `backlog_hipotese` → `biz_1_wagner_active` (Wagner usa Inbox HiTL diário; ConfidenceEngine aprende com aprovações reais) |
| D8 +0→3 | 8 FormRequests novos: Approve/Reject/Dismiss Decision + Store/Approve/Reject Skill + StoreMetaSkill + TestSkill (ratio 8/15=0.53 ≥0.5) |
| D6 +0→3 | `Inertia::defer` em MetricasController/PatternsController/ConflictsController (10+ aggregations cada — block initial render ~200ms) |
| D1 +5 | Pest `CrossTenantSaturationTest`: confidence_scores + decision_patterns biz=1 vs biz=99 + 6 FormRequest sanity check |
| module.json | `governance.fsm_n_a: true` justificado (ADS é meta-orquestrador, não state machine de domínio) |
| Services | `SkillsService::listAll()` + `findBySlug()` envolvidos em `OtelHelper::span` (D9 measure cache miss/fallback DB→filesystem) |

## Wave 18 RETRY (2026-05-16)

**Meta:** subir D8 8→14 FormRequests + D6 cobertura DecisoesController/LearningController.

| Δ | Entrega |
|---|---|
| D8 +6 FormRequests | `ToggleMetaSkillRequest` + `ValidateMetaSkillRuleRequest` + `MoveSkillLabelRequest` + `PublishSkillVersionRequest` + `ExecuteToolRequest` (timeout 1-120s + dry_run) + `DecomposeProjectRequest` (confirm:accepted custo LLM) — ratio agora 14/15 = 0.93 |
| D6 +2 Controllers | `DecisoesController::index` defer em decisions (50 rows × DecisionPresenter::explain) + kpis (5 COUNTs) · `LearningController::index` defer em stages (11 COUNT) + throughput (24 buckets GROUP BY) + kpis derivados |
| D1 +1 test file | `CrossTenantSaturationRetryTest` — 8 testes (1 cross-tenant em mcp_skills_versions + 7 FormRequest sanity novos) |
| Pest local | 7/7 passed (28 assertions, 2.46s) + 1 skipped por env minimal (mcp_skills_versions ausente local) |
