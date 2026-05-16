# ADS — SPEC complementar (User Stories US-ADS-001..012)

> Complementa [`SPEC.md`](SPEC.md) — adiciona granularidade US-XXX-NNN sobre Brain A/B, PolicyEngine, RiskEngine, ConfidenceEngine, DecisionRouter, HITL.
> Status do módulo: **dormant pending qualified signal** ([ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md)) — manter governança, não ativar fluxos prod sem sinal externo.
> Tier 0 IRREVOGÁVEL: multi-tenant `business_id` em toda tabela (ADR 0093), biz=1 em tests (ADR 0101).

## Resumo executivo

ADS = Adaptive Decision System. Meta-camada que decide **qual cérebro (A local / B Anthropic / Humano)** age sobre cada evento, **com qual autoridade (4 níveis HITL)**, e mantém **memória append-only** das decisões pra learning loop (L1 imediato / L2 semanal / L3 mensal). Hoje dormente — só responde a eventos quando sinal qualificado ativa.

## User Stories

### Brain A (Ollama local CT 100)

#### US-ADS-001 — Brain A executa decisão ALLOW_BRAIN_A
**Como** orquestrador ADS, **quero** rotear eventos de baixo risco (lang_file_pt_br, md_link_fix, comment_typo, session_log_creation) pra Brain A local sem custo Anthropic, **pra** economizar tokens.
- AC1: PolicyEngine retorna `ACTION_ALLOW_BRAIN_A` pra event_types listados
- AC2: DecisionRouter direciona `destination=brain_a`
- AC3: `mcp_dual_brain_decisions.brain_used='brain_a'` + `cost_usd=NULL` (Ollama free)
- AC4: Test biz=1 verifica isolamento (cobertura: `MultiTenantDecisionTest`)

#### US-ADS-002 — Brain A fail rate dispara fallback Brain B
**Como** ADS, **quero** detectar Brain A com fail_rate >20% (janela 24h) **pra** auto-rotear pra Brain B até estabilizar.
- AC1: ConfidenceEngine reduz score quando histórico de fails alto
- AC2: DecisionRouter promove pra `destination=brain_b` se score abaixo de threshold

### Brain B (Anthropic Sonnet/Opus)

#### US-ADS-003 — Brain B trace cost/tokens auditável per-business
**Como** Wagner (financeiro/governança), **quero** ver custo Brain B agregado per-business **pra** cobrança IA futura.
- AC1: `cost_usd` salvo com 6 decimais em `mcp_dual_brain_decisions`
- AC2: Query `SUM(cost_usd) WHERE business_id=X` retorna isolado (cobertura: `BrainBIsolationTest`)
- AC3: UI `/ads/admin/metricas` lista per-business
- AC4: Wagner pode modificar instruction antes de execução (`outcome=wagner_modified`, campo `wagner_modified_to`)

#### US-ADS-004 — Brain B REQUIRE_BRAIN_B só pra event_types sensíveis
**Como** ADS, **quero** que PolicyEngine force Brain B em `lgpd_data_handling`, `db_schema_change`, `composer_json_change`, `nfse_fiscal_logic`, `security_rule_change`, `multi_tenant_scope` **pra** não usar Ollama em decisões caras (cobertura: `PolicyEngineTest`).

### PolicyEngine (firewall ARQ-0006)

#### US-ADS-005 — BLOCK_ALWAYS é hard guard final
**Como** sistema, **quero** que 8 event_types (env_production, append_only_table, auth_middleware, pii_direct_exposure, delphi_contract, composer_production, db_trigger_removal, billing_financial_flow) sempre bloqueiem **pra** preservar Tier 0.
- AC1: `PolicyResult::ACTION_BLOCK` retornado
- AC2: `allowsBrainA()=false` mesmo se ConfidenceEngine alto
- AC3: Override impossível sem ADR mãe

#### US-ADS-006 — REQUIRE_HUMAN_REVIEW vai pra fila Wagner
**Como** Wagner, **quero** que 5 event_types (new_module_creation, new_adr_proposal, threshold_change, pattern_hardcode, production_deploy) caiam em fila HITL **pra** decisão humana.
- AC1: `destination=pending_wagner`
- AC2: UI `/ads/admin/decisoes` lista filtrado por business_id (cobertura: `MultiTenantDecisionTest`)

### RiskEngine + ConfidenceEngine (ARQ-0004 / ARQ-0005)

#### US-ADS-007 — RiskEngine combina priors + sinais
**Como** ADS, **quero** que RiskEngine calcule `risk_score 0.000-1.000` combinando priors estáticos por domain + sinais dinâmicos (files_affected, event_metadata) **pra** alimentar DecisionRouter.

#### US-ADS-008 — ConfidenceEngine usa janela móvel 30d
**Como** ADS, **quero** que ConfidenceEngine consulte `mcp_confidence_scores` com janela 30 dias **pra** score atualizado por event_type per-business.
- AC1: Scores per-business isolados (Tier 0)

### DecisionRouter (ARQ-0003)

#### US-ADS-009 — Router aplica matriz Risk × Confidence
**Como** ADS, **quero** que router decida destino com base em matriz 4x4 (risco alto + conf baixa → wagner; risco baixo + conf alta → brain_a; etc) **pra** auto-rotear (cobertura: `DecisionRouterTest` existente).

### HITL (ARQ-0008 — 4 níveis)

#### US-ADS-010 — Nível 1 fully autonomous (Brain A direto)
**Como** ADS, **quero** que decisões ALLOW_BRAIN_A com confidence>0.9 + risk<0.2 sejam Nível 1 **pra** zero fricção Wagner.

#### US-ADS-011 — Nível 4 require Wagner approval
**Como** Wagner, **quero** que decisões BLOCK_ALWAYS-near, deploy production, mudança schema sejam Nível 4 (approve/reject explícito via UI Inbox).

### Audit / Memory (ARQ-0009)

#### US-ADS-012 — Decisões append-only com isolation biz Tier 0
**Como** auditoria/LGPD, **quero** que `mcp_dual_brain_decisions` seja append-only + isolated per-business **pra** prova forense.
- AC1: NUNCA `UPDATE/DELETE` direto (apenas insert + `resolved_at` timestamp)
- AC2: Cross-tenant impossível (cobertura: `MultiTenantDecisionTest`, `BrainBIsolationTest`)
- AC3: FK `business_id → business.id ON DELETE CASCADE`

## Coverage matrix

| US | Test file | Test name |
|---|---|---|
| US-ADS-001 | `Unit/PolicyEngineTest` | "permite Brain A para ALLOW_BRAIN_A" |
| US-ADS-003 | `Feature/BrainBIsolationTest` | "Brain B trace biz=1 não contribui pra cost agregado biz=99" |
| US-ADS-004 | `Unit/PolicyEngineTest` | "exige Brain B para REQUIRE_BRAIN_B" |
| US-ADS-005 | `Unit/PolicyEngineTest` | "bloqueia event_types em BLOCK_ALWAYS" |
| US-ADS-006 | `Feature/MultiTenantDecisionTest` | "REQUIRE_HUMAN_REVIEW biz=1 não aparece em fila pending biz=99" |
| US-ADS-009 | `Unit/DecisionRouterTest` | (existente) |
| US-ADS-012 | `Feature/MultiTenantDecisionTest`, `Feature/BrainBIsolationTest` | (todos) |

## Pendências (depende de sinal qualificado — ADR 0105)

- L1/L2/L3 Learning Loop (ARQ-0007) — só vale ativar com decisões reais entrando
- Governance Rules DSL (ARQ-0010 / cobertura `GovernanceRulesDslTest`) — usable hoje, sem volume
- PatternLearning Wilson score — sem dados suficientes
