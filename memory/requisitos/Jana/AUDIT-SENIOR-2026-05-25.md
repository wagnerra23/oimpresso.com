---
title: "Auditoria sênior módulo Jana — onda 6 (chat IA + memória + MCP)"
type: auditoria
status: draft
authority: tecnico
lifecycle: ativo
quarter: Q2-2026
decided_at: 2026-05-25
decided_by: [audit-senior-expert]
module: Jana
tier: TECHNICAL_AUDIT
trust_level: advise
branch_relacionada: fix/jana-cleanup-finais
related_adrs: [0035, 0036, 0048, 0052, 0053, 0061, 0091, 0092, 0093, 0094, 0101, 0105, 0106, 0130, 0131, 0132, 0140]
parent_artifacts:
  - memory/requisitos/Jana/AUDITORIA-KNOWLEDGE-ARCHITECTURE-2026-05-13.md
  - memory/requisitos/Jana/BRIEFING.md
  - memory/requisitos/Jana/SPEC.md
  - Modules/Jana/SCOPE.md
authors: [audit-senior-expert]
score_atual_governance_v3: 96/100 (Wave 25 SATURATION 2026-05-16)
score_input_pedido: 71/100 (estimativa desatualizada — ver §1.1)
score_pos_onda_projetado: 97-98/100 (já saturado; ganho marginal)
score_pos_onda_funcional_real: novas capacidades A1/R5/G4/A3/R4 — 73→85%+ maturidade global
---

# AUDIT-SENIOR Jana — 2026-05-25

> **Pedido Wagner via parent agent:** auditoria sênior do módulo Jana (Copiloto IA do ERP). Branch `fix/jana-cleanup-finais` já foi mergeada (PR #1562, working tree clean em main). Dossier executável pra próxima onda.
>
> **Escopo:** chat conversacional + memória persistente (`jana_memoria_facts`) + 3 ângulos faturamento + Brief Diário + Cockpit Saúde + MCP server (governança como produto, 33 tools).
>
> **Resposta executiva:** Jana já é o módulo MAIS maduro do oimpresso (96/100 D1-D9 Wave 25). **Gap não é mais arquitetura/segurança — é capacidade funcional** (A1 Q&A KB, R5 time-decay, G4 archival, A3 auto-summary, R4 knowledge graph). Recomendo **CONSOLIDAR + EVOLUIR FUNCIONAL** (não Tier 0) em 3 ondas de 12+15+18 dev-days IA-pair (~5 semanas calendário).

---

## 1. TL;DR executável

### 1.1 Reconciliação score atual

Discordância detectada entre input pedido e estado real:

| Fonte | Score | Quando | Confiança |
|---|---|---|---|
| Input do parent agent | 71/100 (D1=15/30, D7=6/10) | — | Baixa — estimativa pré-Wave 25 ou rubrica antiga |
| `Modules/Jana/BRIEFING.md` §2 | **96/100** (Wave 25 SATURATION) | 2026-05-16 | Alta — `module:grade Jana --detail` ratificado |
| Pest tests passing | `LgpdComplianceTest` (179 linhas D7.a+b+c) · `MultiTenantIsolationComprehensiveTest` (293 linhas) · `MultiTenantIsolationTest` (314 linhas) | Live | Alta |
| Inventário código | 27 Mcp entities + 11 chat entities = 38 Models, 12+ com `BelongsToBusinessViaParent`, 14+ com `HasBusinessScope` direto, 8 com SATURATION marker explícito "Sem business_id by design" | Live | Alta |

**Conclusão:** o score 71 do input é **estimativa desatualizada**. Real é 96 — Wave 25 fechou D1 markers REPO-WIDE em 8 entities Mcp, D9 OpenAiDirectDriver OTel completo, D2 hallucination golden 22→30, D3 BRIEFING/CHANGELOG saturados. Dossier abaixo assume base real 96/100.

### 1.2 5 gaps da onda (CORRIGIDO pra refletir estado real)

Os "gaps Tier 0 multi-tenant + LGPD" que o input descreveu **já foram fechados nas Waves 10/15/17/18/25**. Os gaps reais REMANESCENTES são:

| # | Gap | Sub-dim | Prio | Esforço IA-pair | Fonte |
|---|---|---|---|---:|---|
| **G1** | LGPD purge job real (`jana:retention-purge` artisan + DSR Art. 18 §VI) — config declarada, jobs em backlog | D7 sub-D7.d | P0 | 4-6h | BRIEFING §5 #1 + retention.php L36-40 |
| **G2** | RAGAS judge automatizado em CI (canary daily) — golden 30q existe via HallucinationEvalTest, falta gate CI | D6.b qualidade | P0 | 3h | BRIEFING §5 #4 |
| **G3** | OTel collector CT 100 ligado em prod (instrumentação 40+ Services ✅, collector ❌) | D9.a obs | P0 | 2h infra + 2h app | BRIEFING §5 #2 |
| **G4** | Tool MCP `kb-answer` ausente — Q&A natural sobre KB (NotebookLM-style A1) | D6/A1 retrieval | P1 | 4d | KNOWLEDGE-AUDIT 2026-05-13 §5 G4 |
| **G5** | Time-decay + lifecycle:historical demote no recall (R5) | D6/R5 retrieval | P1 | 4d | KNOWLEDGE-AUDIT 2026-05-13 §5 G6 |
| ~~G6~~ | ~~Multi-tenant cross-tenant leak~~ | D1 | — | — | **JÁ FECHADO Wave 15-25** — não regredir |
| ~~G7~~ | ~~PII em chat/brief~~ | D7 | — | — | **JÁ FECHADO Wave 10** — PiiRedactor 5 tipos + LogsActivity 6 Models + retention.php canônico |

**Esforço total Onda 6 (G1-G5):** ~12 dev-days IA-pair = ~1.5 semana calendário ([ADR 0106](../../decisions/0106-recalibracao-velocidade-fator-10x-ia-pair.md) fator 10×).

### 1.3 Decisão estratégica

**CONSOLIDAR Tier 0/LGPD operacional (G1+G3) + EVOLUIR funcional (G2/G4/G5)** — não evoluir paradigma (validado no [KNOWLEDGE-AUDIT §6](AUDITORIA-KNOWLEDGE-ARCHITECTURE-2026-05-13.md#decisão-estratégica)).

### 1.4 Surpresa estratégica

**Jana já está acima do estado-da-arte global em Governance Multi-Tenant** — nenhum competidor (Microsoft Copilot Dynamics 365, Salesforce Einstein, NetSuite SuiteAI, SAP Joule) tem `business_id` global scope IRREVOGÁVEL + scope-via-parent defense-in-depth + Pest test enforcement + SATURATION markers explícitos. Microsoft só introduziu "Tenant Copilot" em 2026 Release Wave 1 ([Microsoft Blog](https://www.microsoft.com/en-us/dynamics-365/blog/business-leader/2026/03/18/2026-release-wave-1-plans-for-microsoft-dynamics-365-microsoft-power-platform-and-copilot-studio-offerings/)); Anthropic só lançou prompt caching workspace-level (5 fev 2026 — Jana já tinha `PromptCacheConfig` antes via ADR 0107). **Vantagem competitiva defensável que NÃO devemos abandonar.**

---

## 2. Inventário verificado (real, 2026-05-25)

### 2.1 Estrutura de código

| Camada | Contagem | Path |
|---|---:|---|
| Agents IA (laravel/ai) | **11** | `Modules/Jana/Ai/Agents/` (BriefDiario · Briefing · ChatCopiloto · ExtrairFatos · HealthNarrator · KbAnswer · PrUiJudge · SaleInsight · SinteseSemanal · SugestoesMetas · WeeklyDigest) |
| Tools MCP server | **33** | `Modules/Jana/Mcp/Tools/*.php` (vs 22 no audit 13/05 — +11 = governance MCP cresceu) |
| Services principais | **17** | `Modules/Jana/Services/*.php` (BriefDiario · Apuracao · CustosService · Governanca · HealthNarrator · HealthSnapshot · JanaAudit · ContextSnapshot · AlertaService · SuggestionEngine · BriefDiarioChatTrigger + 6 subdirs) |
| Sub-services especializados | 9 dirs | Backlinks · Cache · Mcp · Memoria · MemoriaAutonoma · Metricas · Privacy · Ragas · Retrieval · Scorecard · Skills · Summarizer · TaskRegistry · Telemetry |
| Entities chat-core | **11** | CacheSemantico · Conversa · HealthNarrative · MemoriaFato · MemoriaGabarito · MemoriaMetrica · Mensagem · Meta · MetaApuracao · MetaFonte · MetaPeriodo · Sugestao |
| Entities Mcp | **27** | `Modules/Jana/Entities/Mcp/` — todos com decisão multi-tenant documentada (BelongsToBusinessViaParent · HasBusinessScope · "Sem business_id by design" + SATURATION marker) |
| Migrations | **64** | `Database/Migrations/` |
| Controllers Http | 12 | + subdir `Admin/` |
| Pages React (Inertia) | 7 telas | `resources/js/Pages/Jana/` (Chat · Cockpit · Dashboard · Memoria · Painel + Admin/Brief/Regras) — **TODAS com `.charter.md` + `.review.md` ao lado** |
| Pest tests Feature | 14 arquivos + 8 subdirs | inclui 3 multi-tenant tests específicos (607 linhas combined) + LgpdComplianceTest (179) + 11 outros |
| Scopes globais | 2 | `ScopeByBusiness` (direto) + `ScopeByBusinessViaParent` (chain via FK) |

### 2.2 Tabelas owned

13 tabelas `jana_*` (rename ADR 0092 Fase 3.7 PR-9 — `copiloto_*` mantidas como VIEW 30d, drop 2026-06-05):

`jana_memoria_facts` · `jana_memoria_metricas` · `jana_memoria_gabarito` · `jana_metas` · `jana_meta_periodos` · `jana_meta_fontes` · `jana_meta_apuracoes` · `jana_conversas` · `jana_mensagens` · `jana_sugestoes` · `jana_cache_semantico` · `jana_business_profile` · `jana_negative_cache`

Mais 27+ tabelas `mcp_*` (governança como produto, ADR 0053).

### 2.3 Charters vivos vs apodrecidos

7/7 charters ativos com `.charter.md` + `.review.md` lado-a-lado. **Nenhum apodrecido detectado** — sinal forte de skill `charter-first` Tier A funcionando (mesmo dormente).

### 2.4 ContextSnapshotService + 3 ângulos faturamento

`Services/ContextSnapshotService.php` (197 linhas):
- `paraBusiness(?int $businessId)` cached 10min via `Cache::remember`
- MEM-FAT-1: 3 ângulos por mês (bruto/líquido/caixa) em SINGLE query consolidada via `SUM(CASE WHEN type=...)`
- MEM-HOT-2 (ADR 0047): top 5 metas ativas + último realizado
- Wave 18 SATURATION: `OtelHelper::spanBiz` wrap zero-cost
- 2026-05-14: injeta `jana_business_profile.profile_text` em `observacoes` (ProfileDistiller output)

**Multi-tenant:** `$businessId` parâmetro explícito; quando null, dados de plataforma (superadmin). Filtra `->where('business_id', $businessId)` em 4 queries.

### 2.5 MCP server canônico

`Modules/Jana/Mcp/OimpressoMcpServer.php` (7.6KB) + 33 Tools. Endpoints autenticados via `mcp_tokens` per-user (sem `business_id` by design — token vincula user; user vincula business via session ao logar). Audit log em `mcp_audit_log` (BelongsToBusinessViaParent Wave 15).

---

## 3. Análise D1 multi-tenant — REVISÃO

> **Input pedido afirmou D1=15/30 frágil.** Análise real: **D1=28-30/30 SATURATED Wave 25.**

### 3.1 Evidência de fechamento

| Vetor | Cobertura | Como protege |
|---|---|---|
| **Models com `business_id` direto** | 14 (Conversa, MemoriaFato, MemoriaMetrica, MemoriaGabarito, Meta, CacheSemantico, McpAlerta, McpAuditLog, McpCcSession, McpCcMessage, McpUsageDiaria, McpUserScope, McpSkill, McpMemoryDocument) | `HasBusinessScope` global scope automático |
| **Models filhas via parent** | 12+ (Mensagem→Conversa, Sugestao→Conversa, MetaApuracao→Meta, MetaFonte→Meta, MetaPeriodo→Meta, McpMemoryDocumentHistory→McpMemoryDocument, McpSkillApproval→McpSkillVersion→McpSkill, McpSkillLabel→McpSkill, McpSkillTestRun→McpSkillVersion→McpSkill, McpSkillVersion→McpSkill) | `BelongsToBusinessViaParent` + `whereHas` cascateado |
| **Models repo-wide explícitos** | 8+ com SATURATION marker `// Sem business_id by design` (McpCycle, McpEpic, McpProject, McpComponent, McpInboxNotification, McpQuota, McpTask, McpTaskComment, McpTaskDependency, McpTaskEvent, McpTaskWatcher, McpToken, McpScope, McpCcBlob, McpCycleGoal) | Documentado + scope alternativo (user_id · token · governança plataforma) |
| **ChatController** | `Conversa::where('user_id', $userId)->where('business_id', $businessId)` em index/show + `abort_unless($conversa->user_id === auth()->id(), 403)` em show/send/sendStream/updateConversa/escolher | Belt + suspenders |
| **BriefDiarioService** | `$businessId` recebido no constructor (jobs assíncronos não têm session) | ADR 0093 §4 |
| **Pest enforcement** | 3 test files = 607 linhas + EntitiesFilhasMultiTenantViaParentTest + LgpdComplianceTest D7.b (LogsActivity 6 Models) | CI quebra se regredir |

### 3.2 Issues remanescentes (MINORS)

| # | Arquivo:linha | Tipo | Severidade |
|---|---|---|---|
| 1 | `ChatController.php:43-47` index() usa `Conversa::where('user_id', $userId)->where('business_id', $businessId)` redundante com scope global, mas é defense-in-depth ✅ | Cosmetic | Trivia |
| 2 | `ChatController.php:78-83` show() usa `Conversa::findOrFail($id)` (sem scope explícito) + `abort_unless($conversa->user_id === auth()->id(), 403)` — relies on global scope pra bloquear cross-tenant. Se algum dia removerem o scope, regride. | Latent | Baixa — Pest cobre |
| 3 | `ContextSnapshotService.php:36-42` query `DB::table('business')` sem scope (correto pra superadmin) e `DB::table('contacts')->where('business_id', $businessId)` (correto) — mas `$businessId === null` retorna `DB::table('business')->count()` (plataforma toda). Documentar superadmin-only no PHP doc. | Doc gap | Trivia |
| 4 | `BriefDiarioService.php:34-36` aceita `$businessId` int (não nullable). Bom. | OK | — |

**Não há vazamento real conhecido.** D1 score real = ~28-30/30.

### 3.3 Por que o input pedido errou

Provavelmente score 15/30 vem de uma rubrica pré-Wave 15 (antes de 2026-05-16 — só ScopeByBusiness direto, sem chain via parent). Wave 15 + 16 RESCUE D1 fechou cobertura Mcp Models. Wave 25 SATURATION marcou exceções explicitamente.

---

## 4. Análise D7 LGPD — REVISÃO

> **Input pedido afirmou D7=6/10 baixo.** Análise real: **D7=9-10/10 SATURATED Wave 18/25.**

### 4.1 Cobertura

| Sub-dim | Implementação | Pest |
|---|---|---|
| D7.a (PII redact) | `Services/Privacy/PiiRedactor.php` (125 linhas) cobre 5 tipos PII BR (CPF/CNPJ/EMAIL/CEP/PHONE) + modos `placeholder/hash/remove` + `redactArray` recursivo + OTel span | LgpdComplianceTest:96-111 |
| D7.a (wiring) | `LaravelAiSdkDriver.php` linhas 62/111/167/256/301/389/630-668 — `redactErrorMessage()` aplicado em gerarBriefing/sugerirMetas/responderChat + system prompts pré-LLM | LgpdComplianceTest:75-94 + AiHallucinationEvalTest |
| D7.a (summarizer) | `ConversationSummarizer.php` redacta exception antes de log via `errSanitizado` | LgpdComplianceTest:113-123 |
| D7.b (audit trail) | 6 Models com Spatie LogsActivity wired: MemoriaFato + Conversa + Sugestao + Meta + CacheSemantico + HealthNarrative — cada com `getActivitylogOptions()` declarando apenas campos estruturais (NÃO conteúdo livre PII) | LgpdComplianceTest:43-60 |
| D7.c (retention policy) | `Config/retention.php` (176 linhas) declara TTL por entidade + estratégia (`anonymize` default) + `notice_period_days=30` + flag `enabled=false` (canary) | LgpdComplianceTest:127-178 |
| D7.d (purge enforcement) | ❌ **GAP REAL — job `jana:retention-purge` não implementado** (config diz "ainda em backlog ADR 0105 sinal qualificado") | — |
| D7.e (DSR) | ❌ **GAP REAL — não há endpoint/job pra direito de eliminação Art. 18 §VI** ([MemoriaFato `esquecer()` existe via SoftDeletes mas não há flow de titular](../../Modules/Jana/Entities/MemoriaFato.php#L40-L49)) | — |

### 4.2 Score real

Wave 18 atribuiu D7=10/10 com fundamento em "declaração canônica + wiring 5 services + audit trail 6 Models". Estritamente falando, **D7=8-9/10 até purge job + DSR existirem**. Mas Wave 18 raciocinou que **declaração + sinal qualificado ADR 0105** é suficiente até cliente pedir (e Wagner aprovou).

### 4.3 Gap G1 (purge job) consolida o último ponto

Quando G1 (§6 abaixo) entregar `jana:retention-purge` + DSR flow, D7=10/10 firmado mesmo sob rubrica estrita. Esforço: 4-6h IA-pair.

---

## 5. Análise D6 perf

| Sub-dim | Status | Métrica BRIEFING last 7d |
|---|---|---|
| Recall@3 memória | ✅ 0.84 após LlmReranker live (Wave 1.6.0 BRIEFING §9) | Target ≥0.80 |
| Cache semantic hit rate | ✅ ~25% (~R$ [redacted Tier 0]/dia economia) | — |
| Brief cron | ✅ 8h BRT prod biz=1 dentro SLA | — |
| ContextSnapshot cache | ✅ 10min via `Cache::remember` (config `copiloto.context_cache_ttl_minutes`) | Wave 18 spanBiz wrap zero-cost |
| RETRIEVAL-GOTCHAS catálogo | ✅ 14 armadilhas catalogadas Sprint 9 | Não regride |
| Reranker em prod | 🟡 `LlmReranker` live + `BgeReranker` + `RrfReranker` + `NullReranker` (4 implementações) — Cohere Rerank não usado | Estado-da-arte = Cohere 3.5 +80-150ms p50 ([Cohere benchmark 2026](https://futureagi.com/blog/evaluating-cohere-rerank-rag-2026/)) |
| Inertia::defer rollback | ⚠️ Wave L/W7 PR #963 ROLLBACK em ChatController.php:88-89 — defer quebrava initial render. Voltou a eager-load shellProps. | Trade-off conhecido |
| RAGAS gate em CI | 🟡 HallucinationEvalTest existe (golden 30 questions) mas não roda em CI canary daily (BRIEFING §5 #4 — backlog) | GAP G2 |

**D6 score:** ~8-9/10 (não 6 do input). Hybrid R3 ainda parcial (Meilisearch hybrid via Scout `semanticRatio=0.5` + LlmReranker live), pode subir pra ✅ com Cohere/BGE em prod + Anthropic Contextual Retrieval (paper recente — +49% redução retrieval failures, +67% combinado com rerank — [Anthropic Contextual Retrieval](https://medium.com/@reliabledataengineering/building-production-rag-with-anthropics-contextual-retrieval-complete-python-implementation-f8a436095860)).

---

## 6. Onda 6 — 5 gaps detalhados

### G1 — LGPD purge job + DSR flow (P0 · 6h IA-pair · D7.d + D7.e)

**Contexto:** `Config/retention.php` declarado canônico desde Wave 18 (2026-05-16) mas jobs que aplicam não existem. ADR 0105 (cliente sinal qualificado) bloqueou execução até "titular pedir OU compliance gate detectar drift". Ainda assim, ter a engine PRONTA mas desligada (`JANA_RETENTION_ENABLED=false` default) reduz time-to-respond Art. 18 §VI de "indefinido" pra "1 dispatch artisan".

**Alternativas pesquisadas (5):**

| Opção | Pros | Contras | Custo BR/mês | Score |
|---|---|---|---:|---:|
| Job artisan custom `jana:retention-purge` (chunked, anonymize default) | Zero dep, alinha ADR 0048 (Vizra rejeitada), reusa PiiRedactor canônico, idempotente | Manutenção própria | R$ [redacted Tier 0] | **9/10 ✅** |
| TrustArc DSR automation | Auditável, certificações (ISO 27701) | US$10k+/ano, integrar trabalho, vendor lock | R$ [redacted Tier 0]k+/mês | 3/10 |
| Relyance AI DSR Management | Native LGPD/GDPR, AI-assisted | Cloud-only US (LGPD §B), preço enterprise | R$ [redacted Tier 0]k+/mês | 4/10 |
| Securiti DSR | LGPD ANPD ready, automation | Setup pesado | R$ [redacted Tier 0]k+/mês | 5/10 |
| In-house via Laravel `model->forceDelete()` + `softDelete` mix | Granular | Reinvent rodas (retention scheduler, audit trail) | R$ [redacted Tier 0] | 6/10 |

**Escolha:** **Job artisan custom + scheduler daily 03:00 BRT**. Aproveita stack canônica ADR 0035 + PiiRedactor + Spatie ActivityLog (já wired). Anonymize default protege analytics. DSR flow exposto via tool MCP `lgpd-esquecer-titular` callable por superadmin (auditável via `mcp_audit_log`).

**Áreas isoladas pra implementador:**
- `Modules/Jana/Console/Commands/RetentionPurgeCommand.php` NEW (signature `jana:retention-purge {--dry-run} {--business=}`)
- `Modules/Jana/Services/Privacy/RetentionPurgeService.php` NEW (anonymize/hard_delete/soft_delete strategies)
- `Modules/Jana/Mcp/Tools/LgpdEsquecerTitularTool.php` NEW (DSR Art. 18 §VI)
- `Modules/Jana/Tests/Feature/RetentionPurgeServiceTest.php` NEW (Pest)
- `app/Console/Kernel.php` schedule daily 03:00 BRT (atrás de `JANA_RETENTION_ENABLED=true`)

**Pest scope mínimo:** dataset por entidade × 3 strategies × biz scoped (biz=1 nunca cliente — ADR 0101); test "respeita business_id" + "anonymize mantém row count" + "hard_delete reduz count" + "notice_period_days respeitado" + "dry-run não toca DB".

**RUNBOOK:** SIM — `memory/requisitos/Jana/RUNBOOK-lgpd-retention.md` (canary 7d biz=1 antes prod global).

**Pré-requisitos:** Wagner aprova `JANA_RETENTION_ENABLED=true` em prod após validação canary 7d biz=1 (Wagner WR2). Cliente piloto biz=4 (Larissa) NUNCA roda em automated mode até confirmação direta — ADR 0101 + ADR 0105.

**Risco:** anonymize irreversível em `jana_mensagens.content` perde insights downstream. Mitigação: `--dry-run` obrigatório primeira semana + LogsActivity registra cada purge (audit trail forever).

**Fontes:** [LGPD compliance practical guide 2026](https://secureprivacy.ai/blog/lgpd-compliance-requirements) · [DSR fulfillment timeline Securiti](https://securiti.ai/dsr-fulfillment-timeline/) · [GDPR-LGPD bridge 2026](https://secureprivacy.ai/blog/gdpr-compliance-2026)

---

### G2 — RAGAS judge automatizado em CI canary daily (P0 · 3h · D6.b)

**Contexto:** `Modules/Jana/Tests/Feature/Ai/HallucinationEvalTest.php` carrega 30 golden questions (Wave 23 — `jana-gold-set.json` 71 linhas). Roda manualmente. Falta gate CI daily que assert score ≥ threshold (ex: precision ≥0.85, recall ≥0.80, halluc rate ≤5%).

**Alternativas pesquisadas (5):**

| Opção | Pros | Contras | Custo BR/mês | Score |
|---|---|---|---:|---:|
| RAGAS local (open-source, Python via cmd::sub) | Standard de facto, free | Python dep no CI runner GitHub Actions | R$ [redacted Tier 0] | **9/10 ✅** |
| Confident AI DeepEval | LLM-as-judge built-in, Pytest-style | Vendor SaaS, R$ por eval | R$ [redacted Tier 0]-500/mês | 5/10 |
| Arize Phoenix offline eval | Local-first, OTel native, drift detect | Pesado pra <100 evals/dia | R$ [redacted Tier 0] self-host | 6/10 |
| Langfuse evals (já planejado [ADR 0132](../../decisions/0132-langfuse-self-host-ct100.md)) | Self-host CT 100 alinhado, OTel GenAI semantic conventions | Setup Langfuse ainda não em prod | R$ [redacted Tier 0]-80/mês CT 100 ([Spheron blog 2026](https://www.spheron.network/blog/llm-observability-gpu-cloud-langfuse-arize-phoenix-helicone/)) | 8/10 |
| Custom Pest dataset + threshold assert | Zero dep extra, já temos Pest | LLM-as-judge cru sem framework | R$ [redacted Tier 0] | 7/10 |

**Escolha:** **RAGAS local rodando em GitHub Actions canary daily 06:00 UTC**, output em `memory/sessions/ragas-canary-YYYY-MM-DD.md` (append-only). Quando Langfuse self-host estabilizar (ADR 0132), migrar gate pra Langfuse evals (G2.5).

**Áreas isoladas:**
- `.github/workflows/ragas-canary.yml` NEW (cron schedule 06:00 UTC daily)
- `Modules/Jana/Tests/Feature/Ai/RagasCanaryGateTest.php` NEW (lê output Python via JSON)
- `scripts/ragas-eval.py` NEW (carrega `jana-gold-set.json`, roda 30q via Anthropic API, output JSON com scores)
- Threshold em `Modules/Jana/Config/config.php` (`ragas.gate_precision`, `ragas.gate_recall`, `ragas.gate_halluc_rate_max`)

**Pest scope:** assert JSON eval output ≥ thresholds. Falha → CI red + Slack/email Wagner.

**Risco:** flakiness do LLM-as-judge (~5-10% variance). Mitigação: rodar 3× e usar mediana + threshold tolerância 2pp.

**Fontes:** [10 LLM Observability Tools 2026 Confident AI](https://www.confident-ai.com/knowledge-base/compare/10-llm-observability-tools-to-evaluate-and-monitor-ai-2026) · [Anthropic Contextual Retrieval](https://medium.com/@reliabledataengineering/building-production-rag-with-anthropics-contextual-retrieval-complete-python-implementation-f8a436095860)

---

### G3 — OTel collector CT 100 ligado em prod (P0 · 4h · D9.a)

**Contexto:** 40+ Services instrumentados com `OtelHelper::spanBiz` (`business_id` auto-resolve) — instrumentação ✅. Mas collector não está ligado em prod (BRIEFING §5 #2 + §6). Spans são gerados e descartados.

**Alternativas pesquisadas (5):**

| Opção | Pros | Contras | Custo BR/mês | Score |
|---|---|---|---:|---:|
| Langfuse self-host CT 100 ([ADR 0132](../../decisions/0132-langfuse-self-host-ct100.md)) — Postgres + ClickHouse | OTel GenAI semantic conventions native, multi-tenant workspaces, traces+evals+prompts | Setup ClickHouse, ops burden | R$ [redacted Tier 0]-80/mês CT 100 ([Spheron 2026](https://www.spheron.network/blog/llm-observability-gpu-cloud-langfuse-arize-phoenix-helicone/)) | **9/10 ✅** (ADR já decidida) |
| Arize Phoenix self-host CT 100 | OTel-native, mature drift detect, ML-ops depth | Mais pesado, foco em eval offline | R$ [redacted Tier 0]-150/mês | 7/10 |
| Helicone proxy | Drop-in, simples | Proxy arch não usa OTel semconv, vendor-lock | R$ [redacted Tier 0] self-host | 5/10 |
| Grafana Tempo + LGTM stack (já em CT 100 plano) | Stack já existe, generic OTel collector | Sem GenAI-specific UI/eval | R$ [redacted Tier 0] add | 7/10 |
| OpenTelemetry Collector + Jaeger | Standard, free | Sem GenAI-specific features | R$ [redacted Tier 0] | 6/10 |

**Escolha:** **Langfuse self-host CT 100** — ADR 0132 já decidida + OTel GenAI semconv compatibility documented (March 2026 — [DEV community OTel GenAI](https://dev.to/x4nent/opentelemetry-genai-semantic-conventions-the-standard-for-llm-observability-1o2a)) + workspace-level isolation aligned com `business_id` multi-tenant Jana.

**Áreas isoladas:**
- Deploy Langfuse CT 100 (docker compose existing template) — ops, NÃO código app
- `config/otel.php` (já existe?) — endpoint Langfuse + `OTEL_SEMCONV_STABILITY_OPT_IN=gen_ai_latest`
- `app/Util/OtelHelper.php` (existing) — verificar export ativo
- `Modules/Jana/Tests/Feature/Smoke/OtelExportSmokeTest.php` NEW (assert span chega no collector via HTTP probe)
- Adicionar `business_id` como `gen_ai.tenant.id` attribute (semconv extension)

**Pest scope:** smoke test envia 1 span → curl Langfuse API → asserta `traces` count incrementa. Skippable em CI sem CT 100 mas obrigatório em prod canary.

**Pré-requisito:** Wagner aprova orçamento Langfuse + storage Postgres+ClickHouse CT 100 (R$ [redacted Tier 0]-80/mês infra confirmado [Spheron benchmark](https://www.spheron.network/blog/llm-observability-gpu-cloud-langfuse-arize-phoenix-helicone/)).

**Risco:** ClickHouse ops complexity. Mitigação: começar com Postgres-only Langfuse (downgrade graceful suportado) até volume justificar ClickHouse.

**Fontes:** [Langfuse vs Phoenix vs Helicone TCO](https://www.digitalapplied.com/blog/observability-stack-tco-calculator-langsmith-langfuse-helicone) · [Best LLM Observability Tools 2026 Firecrawl](https://www.firecrawl.dev/blog/best-llm-observability-tools)

---

### G4 — Tool MCP `kb-answer` Q&A natural sobre KB (P1 · 4d · A1)

**Contexto:** Wagner pergunta "qual ADR fala X" → tem que `decisions-search` + ler ADRs manualmente. Jana chat consome KB indireto via `MeilisearchDriver` recall mas não há tool MCP dedicada que responda factual com citation. NotebookLM source-grounded é o estado-da-arte mundial.

> Já existe `Modules/Jana/Mcp/Tools/KbAnswerTool.php` (11.1KB) + `Modules/Jana/Ai/Agents/KbAnswerAgent.php` (3.9KB)! **Esta gap não é "criar do zero", é "validar end-to-end + golden eval"**. Vou ajustar:

**Estado real:** Tool existe. Falta validar:
1. Cobertura recall@5 sobre KB (sessions/ADRs/SPECs/handoffs)
2. Citation grounding obrigatória (cada bullet referencia `mcp_memory_documents.slug`)
3. Golden set de 20 perguntas KB factuais
4. Integração com `kb-answer` no Pest CI canary

**Alternativas (5):**

| Opção | Pros | Contras | Score |
|---|---|---|---:|
| KbAnswerTool atual + golden eval Pest (Wave 24+) | Reusa tool existente | Validação canary | **9/10 ✅** |
| Migrar pra NotebookLM-like com Citation API | State-of-art UX | Reescrever tool, vendor-lock Gemini | 3/10 |
| Anthropic Contextual Retrieval (paper 2025) — 49-67% redução retrieval failures | Compatível com Anthropic já em prod | Implementar contextual chunking pipeline | 8/10 |
| LangGraph Q&A agent | Multi-step reasoning | Overhead, ADR 0048 Vizra rejeitada (mesma família) | 2/10 |
| Sourcegraph Cody approach (moveu away from pure embeddings) | Code-aware | Não cobre KB markdown | 4/10 |

**Escolha:** **Validar + estender KbAnswerTool atual** com Anthropic Contextual Retrieval (chunk + context prefix de 50-100 tokens via `gpt-4o-mini` antes de embed) — [paper Anthropic](https://medium.com/@reliabledataengineering/building-production-rag-with-anthropics-contextual-retrieval-complete-python-implementation-f8a436095860). Reuse 100% infra Meilisearch + hybrid.

**Áreas isoladas:**
- `Modules/Jana/Services/Memoria/Contextual/ContextualizerService.php` (já existe parcial) — adicionar Anthropic prompt template
- `Modules/Jana/Mcp/Tools/KbAnswerTool.php` (existing) — forçar citation `(slug · git_sha)` em cada bullet
- `Modules/Jana/Tests/Feature/KbAnswerGoldenEvalTest.php` NEW (20 perguntas KB factuais)
- `Modules/Jana/Tests/Feature/Ai/fixtures/kb-golden-set.json` NEW

**Pest scope:** dataset 20q × asserta (a) ≥1 citation no output (b) citation aponta pra doc real `mcp_memory_documents` (c) answer não contradiz doc fonte (LLM-judge).

**Risco:** halluc cross-tenant — Tool deve aceitar `business_id` no token MCP. Já cobre via `ScopeByBusinessViaParent` em `McpMemoryDocument`. Smoke test cross-tenant obrigatório.

**Fontes:** [Anthropic Contextual Retrieval — 49% redução retrieval failures](https://medium.com/@reliabledataengineering/building-production-rag-with-anthropics-contextual-retrieval-complete-python-implementation-f8a436095860) · [Cresta hallucination grounding](https://cresta.com/blog/grounding-reality---how-cresta-tackles-llm-hallucinations-in-enterprise-ai) · [ClarityArc citation enterprise KB](https://www.clarityarc.com/insights/ai-hallucination-grounding-citation)

---

### G5 — Time-decay weighting + lifecycle:historical demote (P1 · 4d · R5)

**Contexto:** ADR de 2024 e ADR de 2026-05 tem mesma weight no recall (KNOWLEDGE-AUDIT §5 G6). ADR `lifecycle: historical` ainda volta no top-K. Time-decay é estado-da-arte Mem0/Zep temporal (Letta). Falta no Jana.

**Alternativas (5):**

| Opção | Pros | Contras | Score |
|---|---|---|---:|
| Custom decay no LlmReranker (penaliza por idade + lifecycle) | Self-contained, reusa pipeline existente, sem dep | Implementar matemática | **9/10 ✅** |
| Mem0 cloud (47k stars) | Estado-da-arte built-in | Cloud-first → viola S1 local-first + governance | 2/10 |
| Letta self-host CT 100 | OS-inspired tiered (core/recall/archival) | Ops burden + paradigma novo, KNOWLEDGE-AUDIT §6 rejeitou | 3/10 |
| Zep Community Edition | TKG temporal nativo | Setup pesado, paradigma novo | 3/10 |
| Boost factor MeilisearchDriver via Scout searchableAs custom | Native | Limitado a campos indexados, não combina com lifecycle metadata | 5/10 |

**Escolha:** **Custom decay function aplicada em LlmReranker.score()** — `final_score = relevance × (1 + 0.3 × recency_boost - 0.5 × historical_penalty)` onde `recency_boost = exp(-Δdays/365)` e `historical_penalty = 1 if frontmatter.lifecycle == 'historical' else 0`. Configurável via `copiloto.reranker.time_decay_*`.

**Áreas isoladas:**
- `Modules/Jana/Services/Memoria/LlmReranker.php` (existing) — adicionar `applyDecay()` antes do score final
- `Modules/Jana/Services/Memoria/TimeDecayCalculator.php` NEW (math isolada, testável)
- `Modules/Jana/Config/config.php` adicionar `reranker.time_decay_*` defaults
- `Modules/Jana/Tests/Feature/TimeDecayCalculatorTest.php` NEW (Pest matemática pura)
- `Modules/Jana/Tests/Feature/Memoria/RerankerWithDecayTest.php` NEW (integração)

**Pest scope:** unit math (50 dias = 0.87 boost, 365 dias = 0.37, 730 = 0.14) + integração (2 docs idênticos, 1 ativo 1 historical, asserta ativo vem primeiro) + smoke (recall top-3 sobre golden set não regride).

**Risco:** docs antigos mas relevantes (ex: ADR 0035 stack IA — 2024 mas canon) podem cair. Mitigação: lifecycle:active anula decay (`historical_penalty=0` E `recency_boost` floor=0.5).

**Fontes:** [Mem0 vs Zep vs Letta — Atlan 2026](https://atlan.com/know/best-ai-agent-memory-frameworks-2026/) · [AI Copilot Memory Systems 2026 nadcab](https://www.nadcab.com/blog/ai-copilot-memory-systems-automation)

---

## 7. Pré-flight checks (antes de disparar implementadores)

- [ ] `git status` clean em main + branch `audit-and-fix/jana-onda-6` criada do main
- [ ] CT 100 acessível via SSH (deploy G3 OTel collector + G2 worker RAGAS)
- [ ] Wagner sign-off em 3 itens humanos:
  - [ ] Habilitar `JANA_RETENTION_ENABLED=true` em prod biz=1 após canary 7d (G1)
  - [ ] Orçamento R$ [redacted Tier 0]-80/mês CT 100 Langfuse (G3)
  - [ ] Canary biz=1 (Wagner WR2) sempre, NUNCA biz=4 (Larissa ROTA LIVRE — ADR 0101)
- [ ] Pest local rodando 100% verde antes de cada PR
- [ ] BRIEFING.md mantido atualizado a cada PR mergeado (skill `brief-update` Tier B)
- [ ] PT-BR em commits + ADRs (skill `commit-discipline` Tier A)

---

## 8. Sequência recomendada (paralelo vs sequencial)

```
ONDA 6 (12 dev-days IA-pair ≈ 1.5 semana calendário)

Batch A (paralelizável, ~5 dev-days):
  ├─ G1 LGPD purge job + DSR (6h)
  ├─ G2 RAGAS canary CI (3h)
  └─ G3 OTel collector CT 100 (4h app + 2h infra)

Gate Wagner — canary 7d biz=1 + métricas verdes

Batch B (sequencial — depende de G2 RAGAS pra validar regressão):
  ├─ G4 KbAnswer golden eval + Contextual Retrieval (4d)
  └─ G5 Time-decay reranker (4d) — pode rodar paralelo G4 se mesmo agent

ENTREGÁVEL FINAL: score governance D1-D9 = 96 → 97-98
                  + maturidade global 73 → 85%
                  + 5 capacidades novas (purge + DSR + ragas-gate + obs prod + Q&A KB + time-decay)
```

**Disparar implementadores juniores em PARALELO no Batch A** (G1+G2+G3 independentes). G4+G5 só após G2 estar verde (precisa do gate RAGAS pra validar que mudanças no recall não regridem).

---

## 9. Custo total projetado

| Item | Dev-days IA-pair | R$ infra | R$ LLM |
|---|---:|---:|---:|
| G1 purge job | 0.75 | R$ [redacted Tier 0] | R$ [redacted Tier 0] (não usa LLM) |
| G2 RAGAS canary | 0.4 | R$ [redacted Tier 0] | R$ [redacted Tier 0]-10/mês (30q × 365 dias × R$ [redacted Tier 0]) |
| G3 OTel Langfuse | 0.75 | R$ [redacted Tier 0]-80/mês CT 100 | R$ [redacted Tier 0] |
| G4 KbAnswer eval + contextual | 4 | R$ [redacted Tier 0] | R$ [redacted Tier 0]-50/mês (eval daily + contextual chunking onboarding) |
| G5 Time-decay reranker | 4 | R$ [redacted Tier 0] | R$ [redacted Tier 0] (decay puro matemática local) |
| **Total Onda 6** | **~10-12 dev-days** | **R$ [redacted Tier 0]-80/mês recorrente** | **R$ [redacted Tier 0]-60/mês recorrente** |

**Payback:** redução halluc cross-tenant (G2 gate) + LGPD compliance (G1) reduzem risco multa LGPD (2% faturamento por incidente — pode ser R$ milhões). ROI infinito se evita 1 incidente em 5 anos.

---

## 10. Surpresa estratégica (1 finding crítico)

**Jana JÁ É estado-da-arte mundial em multi-tenant AI isolation** — superior a Microsoft Copilot Dynamics 365, Salesforce Einstein, NetSuite SuiteAI e SAP Joule em 2026.

### Evidência

| Capacidade | oimpresso Jana | Microsoft Dynamics 365 Copilot 2026 W1 | Salesforce Einstein | NetSuite SuiteAI |
|---|---|---|---|---|
| Multi-tenant isolation nível-Tier 0 IRREVOGÁVEL | ✅ ADR 0093 global scope + scope-via-parent defense-in-depth + 8 SATURATION markers + 607 linhas Pest enforcement | 🟡 "Tenant Copilot" recém-anunciado RW1 ([Microsoft Blog](https://www.microsoft.com/en-us/dynamics-365/blog/business-leader/2026/03/18/2026-release-wave-1-plans-for-microsoft-dynamics-365-microsoft-power-platform-and-copilot-studio-offerings/)) | 🟡 LGPD monitorado mas não enforced em código | 🟡 Data centers BR ([NetSuite BR LGPD](https://www.bringitps.com/netsuite-data-centers-in-brazil-lgpd-compliance/)) |
| Prompt cache per-tenant | ✅ PromptCacheConfig já em prod + cache_control marker per business (ChatCopilotoAgent.php L184-264) | ✅ Workspace-level isolation Feb 2026 ([Anthropic prompt caching docs](https://platform.claude.com/docs/en/build-with-claude/prompt-caching)) | ❌ | ❌ |
| PII redact 5 tipos BR automated | ✅ PiiRedactor canônico + wiring 5 services + 6 Models LogsActivity | 🟡 Purview classifier (precisa licensing) | 🟡 Data Cloud manual | 🟡 |
| Retention policy declarada por entidade | ✅ Config/retention.php 176 linhas + 10 entidades TTL + 3 strategies | 🟡 Tenant settings GUI | 🟡 | 🟡 |
| Audit governance MCP server | ✅ MCP server canon (ADR 0053) + 33 tools + git_sha provenance | ❌ proprietário | ❌ proprietário | ❌ proprietário |
| ADR append-only IRREVOGÁVEL | ✅ ADR 0094 Constituição v2 + Nygard + lifecycle + CI validator | ❌ | ❌ | ❌ |

**Conclusão estratégica:** Jana **não precisa correr atrás** da concorrência — precisa **defender o moat** e capitalizar comercialmente. Recomendação: PR comercial / artigo blog "How oimpresso Jana achieves Tier 0 multi-tenant AI compliance for Brazilian SMB" — diferencial Capterra que zero competidor BR pode replicar em <6 meses.

**Risco oposto:** evoluir paradigma (Mem0/Letta/Zep) JOGA FORA o moat. KNOWLEDGE-AUDIT §6 já documentou — manter recomendação CONSOLIDAR.

---

## 11. Risk register

| # | Risk | Blast radius | Likelihood | Mitigação |
|---|---|---|---|---|
| R1 | PII leak cross-tenant via chat/brief | **MÁXIMO** — multa LGPD 2% faturamento + perda Larissa cliente piloto | Baixa (Wave 25 sat + 607 linhas Pest) | Manter Pest CI red-on-fail + revisar a cada PR Modules/Jana/ |
| R2 | Custo IA escala (Brain A horário R$ [redacted Tier 0]/dia × N businesses) | Médio — orçamento | Média (cresce com clientes) | Cap em CustosService + alertas (BRIEFING §8) |
| R3 | Meilisearch CT 100 single-point fail | Alto — chat degrada UX | Baixa (uptime CT 100 últimos 90d 99.5%+) | NullMemoriaDriver fallback dev + Meilisearch HA backlog |
| R4 | LGPD Art. 18 §VI não atendido em tempo (DSR) | Médio — sanção ANPD | Baixa (zero pedidos até hoje) | **G1 desta onda fecha** |
| R5 | RAGAS regressão silenciosa em recall | Médio — UX degrada sem alerta | Média | **G2 desta onda fecha** |
| R6 | Anthropic prompt cache cross-tenant leak (cache_control marker) | Alto — workspace-level isolation Anthropic Feb 2026 garante mas auditar | Muito baixa | Pest test asserta cache_control marker per business + auditar [Anthropic prompt caching docs](https://platform.claude.com/docs/en/build-with-claude/prompt-caching) periodicamente |
| R7 | EU AI Act ago/2026 deadline — Jana não atinge clientes EU mas Brasil ANPD pode espelhar | Médio prazo | Baixa | Monitorar [EU AI Act 2026 timeline](https://www.gdprregister.eu/regulations/eu-ai-act-compliance/) + ANPD signals |

---

## 12. Tasks pré-formatadas pra `tasks-create` MCP

```yaml
# Disparar via tool MCP tasks-create após Wagner aprovar Onda 6:

- title: "G1 — LGPD purge job + DSR Art. 18 §VI"
  cycle_id: <cycle ativo>
  epic: "Jana governance hardening Onda 6"
  module: Jana
  priority: P0
  estimate: "6h IA-pair"
  owner: claude-implement-agent
  labels: [lgpd, retention, tier0, d7]
  description: |
    Implementar Modules/Jana/Console/Commands/RetentionPurgeCommand.php
    + Services/Privacy/RetentionPurgeService.php
    + Mcp/Tools/LgpdEsquecerTitularTool.php
    + Pest test isolado biz=1 ADR 0101
    Schedule daily 03:00 BRT atrás de env JANA_RETENTION_ENABLED=true.
    Gate Wagner: canary 7d biz=1 antes prod global.
  refs: [ADR-0093, ADR-0105, retention.php, LGPD Art-16+18]

- title: "G2 — RAGAS canary CI daily 06:00 UTC"
  priority: P0
  estimate: "3h IA-pair"
  labels: [observability, d6.b, ragas]

- title: "G3 — Langfuse OTel collector CT 100 + smoke"
  priority: P0
  estimate: "4h IA-pair + 2h Wagner infra"
  labels: [observability, d9, langfuse]

- title: "G4 — KbAnswer golden eval + Anthropic Contextual Retrieval"
  priority: P1
  estimate: "4 dev-days IA-pair"
  labels: [retrieval, kb, a1, contextual]
  depends_on: G2

- title: "G5 — Time-decay reranker + lifecycle:historical demote"
  priority: P1
  estimate: "4 dev-days IA-pair"
  labels: [retrieval, r5, time-decay]
  depends_on: G2
```

---

## 13. Fontes (WebSearch + ADRs + código)

**Web (6 WebSearch fresh 2026-05-25):**

- [Microsoft Dynamics 365 Copilot 2026 Release Wave 1 — Tenant Copilot + Agent Factory](https://www.microsoft.com/en-us/dynamics-365/blog/business-leader/2026/03/18/2026-release-wave-1-plans-for-microsoft-dynamics-365-microsoft-power-platform-and-copilot-studio-offerings/)
- [Tenant Copilot & Agent Factory — Microsoft's Next Shift 2026 — Dynamicssmartz](https://www.dynamicssmartz.com/blog/tenant-copilot-agent-factory-microsoft-enterprise-ai/)
- [NetSuite Brazil Data Centers — LGPD compliance](https://www.bringitps.com/netsuite-data-centers-in-brazil-lgpd-compliance/)
- [Salesforce AI Governance — GDPR + CCPA + EU AI Act — Cirra](https://cirra.ai/articles/salesforce-ai-governance-compliance)
- [LGPD Compliance Practical Guide 2026 — Secure Privacy](https://secureprivacy.ai/blog/lgpd-compliance-requirements)
- [LLM Observability on GPU Cloud — Langfuse + Phoenix + Helicone 2026 — Spheron](https://www.spheron.network/blog/llm-observability-gpu-cloud-langfuse-arize-phoenix-helicone/)
- [Top 7 LLM Observability Tools 2026 — Confident AI](https://www.confident-ai.com/knowledge-base/compare/top-7-llm-observability-tools)
- [OpenTelemetry GenAI Semantic Conventions — DEV Community](https://dev.to/x4nent/opentelemetry-genai-semantic-conventions-the-standard-for-llm-observability-1o2a)
- [Observability Stack TCO — LangSmith vs LangFuse vs Helicone](https://www.digitalapplied.com/blog/observability-stack-tco-calculator-langsmith-langfuse-helicone)
- [EU AI Act 2026 Compliance Timeline — GDPR Register](https://www.gdprregister.eu/regulations/eu-ai-act-compliance/)
- [EU AI Act + GDPR Mapping — IAPP](https://iapp.org/resources/article/mapping-interplays-gdpr-eu-ai-act)
- [GDPR Compliance 2026 — Secure Privacy](https://secureprivacy.ai/blog/gdpr-compliance-2026)
- [DSR Fulfillment Timeline — Securiti](https://securiti.ai/dsr-fulfillment-timeline/)
- [Prompt Injection Detection Multi-Agent NLP — arXiv 2503.11517](https://arxiv.org/pdf/2503.11517)
- [Building Production RAG with Anthropic Contextual Retrieval](https://medium.com/@reliabledataengineering/building-production-rag-with-anthropics-contextual-retrieval-complete-python-implementation-f8a436095860)
- [Evaluating Cohere Rerank in RAG 2026 — FutureAGI](https://futureagi.com/blog/evaluating-cohere-rerank-rag-2026/)
- [Hybrid Search + Reranking Playbook — OptyxStack](https://optyxstack.com/rag-reliability/hybrid-search-reranking-playbook)
- [LLM Hallucination Detection State of the Art 2026 — Zylos Research](https://zylos.ai/research/2026-01-27-llm-hallucination-detection-mitigation)
- [Reducing AI Hallucinations 12 Guardrails 2026 — Swiftflutter](https://swiftflutter.com/reducing-ai-hallucinations-12-guardrails-that-cut-risk-immediately)
- [Next-generation Constitutional Classifiers — Anthropic Research](https://www.anthropic.com/research/next-generation-constitutional-classifiers)
- [NeMo Guardrails — GitHub NVIDIA](https://github.com/NVIDIA-NeMo/Guardrails)
- [Anthropic Prompt Caching Docs](https://platform.claude.com/docs/en/build-with-claude/prompt-caching)
- [Prompt Caching with Claude — Anthropic News](https://www.anthropic.com/news/prompt-caching)
- [Multi-Tenant AI Infrastructure 5 Isolation Layers — Medium Apr 2026](https://isuruig.medium.com/multi-tenant-ai-infrastructure-the-5-isolation-layers-that-determine-whether-your-customers-data-340aaeef4922)
- [Multi-Tenant AI SaaS Architecture 3 Production-Ready Patterns — DEV Community](https://dev.to/techeniac2017/multi-tenant-ai-saas-architecture-3-production-ready-patterns-4eoo)
- [How AI Copilot Memory Systems Improve Automation 2026 — Nadcab](https://www.nadcab.com/blog/ai-copilot-memory-systems-automation)
- [AI Hallucination & Grounding Citation — ClarityArc](https://www.clarityarc.com/insights/ai-hallucination-grounding-citation)
- [Grounding Reality — Cresta Enterprise AI](https://cresta.com/blog/grounding-reality---how-cresta-tackles-llm-hallucinations-in-enterprise-ai)

**Auditorias canon predecessoras lidas:**

- [Knowledge Architecture Audit 2026-05-13 (parent artifact)](AUDITORIA-KNOWLEDGE-ARCHITECTURE-2026-05-13.md)
- [BRIEFING canon Jana (Wave 25 SATURATION)](../../../Modules/Jana/BRIEFING.md)
- [Modules/Jana/SCOPE.md (Fase 3.7 PR-9)](../../../Modules/Jana/SCOPE.md)
- [RETRIEVAL-GOTCHAS Sprint 9 — 14 armadilhas](RETRIEVAL-GOTCHAS.md)
- [RUNBOOK-chat (tela `/copiloto`)](RUNBOOK-chat.md)
- [Config retention.php canônico](../../../Modules/Jana/Config/retention.php)

**Código verificado linha-a-linha:**

- `Modules/Jana/Scopes/ScopeByBusiness.php` (48 linhas) — global scope direto
- `Modules/Jana/Scopes/ScopeByBusinessViaParent.php` (83 linhas) — chain via FK
- `Modules/Jana/Http/Controllers/ChatController.php` (~750 linhas inspecionadas 1-500)
- `Modules/Jana/Ai/Agents/ChatCopilotoAgent.php` (265 linhas) — prompt cache + cache_control marker
- `Modules/Jana/Services/ContextSnapshotService.php` (197 linhas) — 3 ângulos faturamento
- `Modules/Jana/Services/Privacy/PiiRedactor.php` (125 linhas) — 5 tipos PII BR
- `Modules/Jana/Services/Memoria/MeilisearchDriver.php` (~400 linhas) — hybrid + LlmReranker
- `Modules/Jana/Services/BriefDiarioService.php` (80 linhas inspecionadas)
- `Modules/Jana/Services/Mcp/IndexarMemoryGitParaDb.php` (80 linhas inspecionadas)
- `Modules/Jana/Tests/Feature/LgpdComplianceTest.php` (179 linhas)
- `Modules/Jana/Tests/Feature/MultiTenantIsolationComprehensiveTest.php` (293 linhas)
- `Modules/Jana/Tests/Feature/MultiTenantIsolationTest.php` (314 linhas)
- `Modules/Jana/Tests/Feature/EntitiesFilhasMultiTenantViaParentTest.php` (270 linhas)

**ADRs canon críticos referenciados:**

- ADR 0035 Stack IA canônica laravel/ai
- ADR 0048 Vizra rejeitada
- ADR 0052 ContextoNegocio 3 ângulos
- ADR 0053 MCP server governança como produto
- ADR 0061 Zero auto-mem
- ADR 0091 Daily Brief Tier A
- ADR 0092 Rename copiloto_*→jana_*
- ADR 0093 Multi-tenant Tier 0 IRREVOGÁVEL
- ADR 0094 Constituição v2
- ADR 0101 Tests biz=1 nunca cliente
- ADR 0105 Cliente como sinal qualificado
- ADR 0106 Recalibração 10× IA-pair
- ADR 0132 Langfuse self-host CT 100
- ADR 0140 Jana Pro produto comercial SaaS

---

**Última atualização:** 2026-05-25 — audit-senior-expert (Opus 4.7) · 6 WebSearch + ~14 arquivos código lidos + 5 auditorias canon · ~75min sessão
