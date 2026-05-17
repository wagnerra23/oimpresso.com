---
sessao: como-integrar-scorecard
data: 2026-05-17
autor: agent `como-integrar` (Opus 4.7, INTROSPECTIVO)
solicitante: Wagner
escopo: pre-flight Modules/Scorecard (KPIs comerciais cliente + cross-tenant)
status: PRE-CODE — sem scaffold, sem PR, sem task MCP
---

# Como integrar — `Modules/Scorecard` (KPIs comerciais)

> **TL;DR (1 parágrafo):** ~70% dos KPIs comerciais candidatos JÁ existem calculados (Financeiro.Dashboard tem 4 KPIs com Inertia::defer canônico; Repair.Dashboard tem 5 charts; Crm.DealPipeline tem `pipelineSummary` + `forecastFechamento`; Jana.HealthSnapshotService agrega 4 fontes superadmin cross-tenant; Whatsapp tem `MetricsSnapshotBuilder.snapshotOutbound`; UltimatePOS `TransactionUtil.getSellsCurrentFy` cobre faturamento; HomeController atual renderiza Sells last 30 days em Blade). **O que NÃO existe** é (a) um agregador unificado que junte essas fontes per-business pra entregar 1 dashboard "pulso do negócio" pro cliente externo (Larissa biz=4) e (b) view cross-business pra Wagner. Recomendação: **criar `Modules/Scorecard` como AGREGADOR THIN** que consome services existentes via dependency injection — NÃO re-implementar KPIs. Pattern de referência canônico: `Modules/Jana/Services/HealthSnapshotService::snapshot()` (já agrega 4 fontes, retorna shape estável). Risco maior: cross-tenant LGPD se Wagner versão agregar dados business-identificáveis (precisa k-anonymity ≥5 ou ficar restrito a superadmin sem export).

---

## Fase 1 — INVENTÁRIO (já existe? quanto?)

### 1.1 Modules existentes — não há `Modules/Scorecard`

```
ls Modules/ → 34 módulos. Não há Scorecard.
ls memory/decisions/proposals/ → não há scorecard-*.md
ls memory/requisitos/Scorecard/ → não existe
```

### 1.2 Confusão potencial com Governance.ScopedScorecardEvaluator (RESOLVIDA)

| Aspecto | `Governance.ScopedScorecardEvaluator` | `Modules/Scorecard` proposto |
|---|---|---|
| Domínio | **Técnico** — rubrica D1-D9 por módulo (multi_tenant, pest, lgpd, perf) | **Comercial** — KPIs do negócio (faturamento, AR, MRR, OS) |
| Fonte | YAML curated `memory/governance/scorecards/*.yaml` + scan filesystem | Service calls aggregating live DB queries per-business |
| Audiência | Wagner / time MCP | Cliente externo (Larissa) + Wagner cross-business |
| Schema | `mcp_scorecard_runs` (repo-wide, business_id=1 superadmin meta) | `scorecard_snapshots` (tenant-scoped, FK business_id) |
| Paired indicators | Sim (anti-gaming velocidade × qualidade) | N/A (KPI comercial não tem gaming técnico) |

**Conclusão:** nomes colidem na palavra "scorecard" mas domínios são ortogonais. Para evitar confusão futura, considerar renomeação na ADR de gênese (alternativas: `Modules/Pulso`, `Modules/Metrics`, `Modules/Insights`). **Wagner aprovou `Scorecard` neste prompt** — colisão de naming é só semântica, não conflito de namespace (PHP namespace `Modules\Scorecard\` vs `Modules\Governance\Services\ScopedScorecardEvaluator` não colidem).

### 1.3 KPIs candidatos — tabela DE ONDE JÁ CALCULA

| KPI | Onde já calcula | Como expõe | Cache | Multi-tenant scoped? |
|---|---|---|---|---|
| **Faturamento period (FY/30d)** | `app/Utils/TransactionUtil.php:2880` `getSellsCurrentFy($businessId, $start, $end)` | método util legacy UltimatePOS | sem cache | ✅ business_id explícito |
| **Sells last 30d chart** | `app/Http/Controllers/HomeController.php` linhas 80-127 | Blade legacy `home.index` (renderiza CommonChart) | sem cache | ✅ session('user.business_id') |
| **A Receber / A Pagar / Recebido mês / Pago mês** | `Modules/Financeiro/Http/Controllers/DashboardController.php:103` `calcularKpis()` | Inertia route `/financeiro` 4 cards | Inertia::defer (lazy on demand) | ✅ where business_id explicit |
| **DRE + Fluxo Caixa Projetado + Resumo** | `Modules/Financeiro/Http/Controllers/RelatoriosController.php:36` `index()` | 3 tabs em `/financeiro/relatorios` | sem cache (eager — anti-pattern, deveria defer) | ✅ BusinessScope |
| **Saldo total contas bancárias** | `DashboardController.php:73` `saldo_cached` ContaBancaria | Inertia::defer prop `saldo_total` | DB-cached via observer | ✅ business_id |
| **OS abertas / por status / por staff** | `Modules/Repair/Utils/RepairUtil.php:253` `getRepairByStatus`, `:278` `getRepairByServiceStaff`, `:299` `getTrendingRepairBrands`, `:350` `getTrendingDevices`, `:384` `getTrendingDeviceModels` | `Repair/Http/Controllers/DashboardController.php:35` `index()` Inertia | sem cache | ✅ business_id arg |
| **OS Kanban production view** | `Modules/Repair/Services/KanbanProductionService` + `Repair/Http/Controllers/ProducaoOficinaController.php` | route `/repair/producao` | sem cache (Collection já scoped) | ✅ Caller passa Collection scoped |
| **CRM Pipeline summary + Forecast weighted** | `Modules/Crm/Services/DealPipelineService.php:116` `pipelineSummary($businessId)`, `:167` `forecastFechamento($businessId, $periodEnd)` | service público stateless | sem cache | ✅ business_id 1º arg + paranoid where |
| **CRM Dashboard total customers/leads/sources/birthdays/follow-ups** | `Modules/Crm/Http/Controllers/CrmDashboardController.php:37` `index()` | route legacy Blade | sem cache | ✅ session business_id |
| **NFe stats (emitidas/canceladas/rejeitadas 30d)** | NÃO existe service exposto. Tabela `nfe_emissoes` consultável. | direta via Eloquent NfeEmissao em testes Wave23-28 | sem cache | ✅ HasBusinessScope (assumido) |
| **WhatsApp outbound 24h sucesso/falha/taxa** | `Modules/Whatsapp/Services/Metrics/MetricsSnapshotBuilder.php:38` `snapshotOutbound($businessId, $janelaHoras)` + `:78` `snapshotPorDriver($businessId)` | service stateless puro | sem cache (OtelHelper span) | ✅ business_id 1º arg |
| **Jana custo LLM 24h + tokens** | `Modules/Jana/Services/HealthSnapshotService.php:108` `brainBStats()` agrega `jana_mensagens.tokens_in/out` | método interno do snapshot | sem cache | ❌ **cross-tenant** (superadmin only — by design) |
| **Jana health 5 checks** | `app/Console/Commands/JanaHealthCheckCommand.php` via `jana:health-check --json` | artisan command exec | sem cache (real-time) | ❌ cross-tenant (superadmin) |
| **Queue failed jobs 24h** | `HealthSnapshotService:71` `queueStats()` | superadmin snapshot | sem cache | ❌ cross-tenant |
| **MCP requests/erros/custo 24h** | `HealthSnapshotService:86` `mcpStats()` | superadmin snapshot | sem cache | ❌ cross-tenant |
| **MRR / Churn / Cobranças recorrentes** | NÃO existe service exposto. `RecurringBilling/Services/AssinaturaService.php`, `AssinaturaCobrancaService.php` têm CRUD mas sem getMrr/getChurn método agregador | tabela `assinaturas` consultável direto | sem cache | ✅ business_id (assumido HasBusinessScope) |
| **Estoque valor / produtos baixo mínimo / parados** | NÃO inventariado este pre-flight (tempo) — espalhado em `app/Utils/ProductUtil.php` e Modules/ProductCatalogue | suspeita: existe parcial em ReportController | parcial | ✅ business_id |
| **Inadimplência aging buckets** | `Modules/Financeiro/Models/Titulo::agingBucket()` em row level + filter `vencido` em DashboardController KPIs | método de model + KPI já calc | Inertia::defer | ✅ business_id |
| **Brief consolidado narrativa** | `Modules/Brief/Services/BriefGeneratorService::generateNow()` chama OpenAI gpt-4o-mini, lê `mcp_brief_inputs_cache` | tool MCP `brief-fetch` | DB-cached `mcp_briefs` 4h cron | ❌ cross-tenant (superadmin Wagner) |

**Estatística:** dos ~18 KPIs candidatos, **~13 (72%)** já calculados em algum service/util/controller. **~5** precisam criar do zero (NFe agregado, MRR, churn, estoque parado, ticket médio nominal por vendedor).

### 1.4 ADRs e proposals relevantes

| Doc | Status | Relevância |
|---|---|---|
| [ADR 0091 — Daily Brief](../decisions/0091-daily-brief.md) | aceito 2026-05-06 LIVE prod | Pattern de **agregador narrativa cross-tenant** — Scorecard imita topologia (cron + service + tool MCP) |
| [ADR 0160 — Governance v4 Scoped Scorecards](../decisions/0160-governance-v4-scoped-scorecards-buckets.md) | aceito 2026-05-16 | Domínio **técnico** — não conflita, esclarece naming |
| [proposal feature-financial-snapshot-multi-cliente](../decisions/proposals/feature-financial-snapshot-multi-cliente.md) | proposed 2026-05-09 | **Produto pago** Tier 1-3 R$ [redacted Tier 0]-599/m baseado em Firebird OfficeImpresso legacy. Domínio adjacente — Scorecard SaaS interno pode virar base do "Financial Pro Tier 2" futuro |
| [requisitos/Dashboard.md](../requisitos/Dashboard.md) | módulo legado **PERDIDO na 6.7-react migration** | Scorecard pode absorver este vazio (ressuscitar como módulo novo) |
| [requisitos/BI/COMPARATIVO_CONCORRENCIA.md](../requisitos/BI/COMPARATIVO_CONCORRENCIA.md) | atualizado 2026-04-25 | Posicionamento vs Metabase/Power BI/Looker — Scorecard NÃO é BI custom (sem ETL drag-drop) — é DASHBOARD pré-built fixo |
| [ADR 0121 — Modular especializado por vertical](../decisions/0121-oimpresso-modular-especializado-por-vertical.md) | aceito | Scorecard é **núcleo comum** (KPIs aplicam a qualquer vertical) — bucket `cross_cutting_infra` ou novo `core_dashboards` |

### 1.5 Sessions recentes relacionadas

- `2026-05-16-arte-domain-specific-scorecards.md` (Wagner estudou estado da arte)
- `2026-05-16-arte-scorecards-alta-2026-benchmark.md`
- `2026-05-16-plano-ondas-governance-v4-scoped-scorecards.md`
- 7 sessões `2026-05-17-arte-bucket-*.md` (estado-da-arte por bucket — feito hoje)

**Sinal:** Wagner estudou scorecard há 24-72h pra contexto Governance V4. Conceito está fresco. Nenhuma sessão sobre Scorecard comercial.

---

## Fase 2 — PEGADINHAS APLICÁVEIS (filtradas)

| # | Pegadinha | Aplicação Scorecard |
|---|---|---|
| 1 | **Multi-tenant Tier 0** [ADR 0093](../decisions/0093-multi-tenant-isolation-tier-0.md) | KPIs cliente: `business_id` global scope obrigatório nos models que tocar (assinaturas, transactions, nfe_emissoes). KPIs cross-business: `withoutGlobalScopes` com comentário `// SUPERADMIN: scorecard cross-tenant Wagner` |
| 2 | **Job cron passa `$businessId` no constructor** | `ScorecardSnapshotPerBusinessJob::__construct(int $businessId)` — `session()` não funciona em fila |
| 3 | **Inertia::defer DEFAULT** ([RUNBOOK canônico](../requisitos/_DesignSystem/RUNBOOK-inertia-defer-pattern.md)) | **TODA** prop com aggregated query, COUNT, paginate, service call DB DEVE ser `Inertia::defer(fn () => ...)`. Scorecard tem 15-20 props desse tipo. `FinanceiroDashboardController` já é o template canônico — IMITAR |
| 4 | **PII em logs/alerts NEVER** | KPI alerta "Inadimplência R$ X com cliente Y CNPJ Z" → CNPJ proibido em log/PR/commit. Use `PiiRedactor` se mostrar cliente identificado |
| 5 | **LGPD opt-in cross-business benchmarking** | Se Wagner ver superadmin agregação cross-tenant identificável (ex: "biz=4 R$ Y, biz=7 R$ Z") → ok só pra Wagner superadmin. **Benchmark anonimizado** (ex: "seu MRR está acima de 60% dos similares") exige k-anonymity ≥5 — ver draft `Modules/Insights/.../BenchmarkAggregatorKAnonymityTest.php` |
| 6 | **Roles Spatie `#{biz}` suffix** ([proibicoes §FSM](../proibicoes.md)) | Permission `scorecard.view#{biz}` (cliente vê próprio) vs `scorecard.admin.cross-tenant` (Wagner global). Não criar role sem suffix per-biz |
| 7 | **`Inertia::render` com 15+ props lazy = controller fica feio** | Extrair `BusinessMetricsAggregator::snapshot(int $businessId): array` shape estável + Controller só faz `Inertia::defer(fn () => $agg->snapshot($biz))` — pattern `HealthSnapshotService` |
| 8 | **MWART canônico** [ADR 0104](../decisions/0104-processo-mwart-canonico-unico-caminho.md) | Page Inertia `/scorecard` Index.tsx **EXIGE** `memory/requisitos/Scorecard/RUNBOOK-index.md` antes de Edit/Write — hook `block-mwart-violation.ps1` bloqueia em runtime |
| 9 | **Charter ao lado da Page** | `resources/js/Pages/Scorecard/Index.charter.md` (S4+ skill `charter-first` auto-ativa) |
| 10 | **Mobile-first 1280px Larissa monitor** | Layout Tailwind responsive `grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4` — Sells PR #1015 PageHeaderActions é referência canônica |
| 11 | **Cron schedule timezone BRT** | `app/Console/Kernel.php` daily 03:00 BRT (não UTC) pra snapshot agregado per-biz |
| 12 | **Cache invalidation Sales/Repair atualiza** | Observer em `Transaction::saved` invalida `Cache::tags(['scorecard:biz:'.$businessId])` — ou aceitar stale 5min como Brief faz |
| 13 | **Identificadores MySQL ≤64 chars** | `scorecard_snapshots_business_id_metric_key_date_unique` é 53 chars — ok. `scorecard_business_metrics_aggregator_cache_idx` é 47 — ok. Sempre nome explícito |
| 14 | **NÃO duplicar HomeController legacy Sells 30d** | HomeController existe e renderiza Blade. Decidir: **(a)** Scorecard absorve+deprecates HomeController dashboard ou **(b)** coexiste link "Scorecard" no menu sem mexer em /home. Recomendação: **(b)** primeira release, **(a)** depois canary 7d. |
| 15 | **Não duplicar Brief** | Brief é narrativa 7-seções IA-summarized 4h cron — Scorecard NÃO chama OpenAI, é puro SQL. Co-existe |
| 16 | **format_date +3h shift** [ADR 0066](../decisions/0066-format-date-shift-3h-preservado-legacy-clientes.md) | KPIs "últimos 30 dias" use `format_now_local()` pra "agora", não `format_date()` (que tem shift legacy de cliente preservado) |
| 17 | **Pest biz=1 nunca cliente real** [ADR 0101](../decisions/0101-tests-business-id-1-nunca-cliente.md) | Smoke biz=1 em CI; biz=4 (ROTA LIVRE) só em canary prod opt-in |
| 18 | **PowerShell UTF-8 BOM crash PHP** ([proibicoes §Código](../proibicoes.md)) | Não relevante a Scorecard especificamente, mas se algum arquivo PHP for criado via PS5.1 lembrar `[System.IO.File]::WriteAllText` |

**Não-pegadinhas observadas (mas merece atenção, não documentado canon):**
- Volume de dados ROTA LIVRE biz=4 (99% volume) pode tornar agregação síncrona >2s — preferir snapshot cron + Inertia::defer reading cached row
- Wagner cross-tenant view pode crescer pra 50+ biz futuro — paginar/filtrar
- Custo Brain B se Scorecard quiser narrativa IA (default: NÃO ter narrativa, é tabela numérica)

---

## Fase 3 — PLUG-POINTS (decisões arquiteturais)

### 3.1 Agregador thin vs cada módulo expõe snapshot — **DECISÃO**

**Recomendação:** **HÍBRIDO**, mas tendendo a "agregador thin lê services existentes".

Justificativa:
- 13 KPIs já têm service/util/controller calculando. Re-implementar é violação Tier 0 "não duplicar info".
- Mas chamar 13 services no Controller é boilerplate ruim — Scorecard precisa de **1 ponto de orquestração**.

**Estrutura proposta:**

```
Modules/Scorecard/
├── Services/
│   ├── BusinessMetricsAggregator.php  ← orquestrador thin per-business
│   ├── PlatformMetricsAggregator.php  ← orquestrador thin cross-tenant (superadmin)
│   └── Contracts/
│       └── MetricSourceContract.php   ← interface opcional pra módulos exporem
├── ...
```

`BusinessMetricsAggregator::snapshot(int $businessId): array` chama:
- `app(\App\Utils\TransactionUtil::class)->getSellsCurrentFy(...)` → faturamento
- `(new \Modules\Financeiro\Http\Controllers\DashboardController)->calcularKpis(...)` ← **anti-pattern**: melhor extrair `FinanceiroKpiService::calcular($businessId)` ANTES de Scorecard consumir (sub-task)
- `app(\Modules\Crm\Services\DealPipelineService::class)->pipelineSummary($businessId)`
- `app(\Modules\Whatsapp\Services\Metrics\MetricsSnapshotBuilder::class)->snapshotOutbound($businessId)`
- `(new \Modules\Repair\Utils\RepairUtil)->getRepairByStatus($businessId)` (legacy util — refactor opcional pra service)

**Pré-requisito SoC brutal (D4):** alguns KPIs estão presos em Controllers ou Utils legacy. **Antes** de Scorecard consumir, extrair:
1. `Modules/Financeiro/Services/FinanceiroKpiService.php` (extrai `calcularKpis()` de `DashboardController`)
2. `Modules/RecurringBilling/Services/MrrChurnService.php` (CRIAR — não existe)
3. NfeBrasil: `Modules/NfeBrasil/Services/NfeMetricsService.php` (CRIAR — não existe)

### 3.2 Schema DB — **snapshot table sim**

`scorecard_snapshots` tabela com 1 row por business per day:

```
scorecard_snapshots
├── id (PK)
├── business_id (FK businesses, indexed, NOT NULL)
├── snapshot_date (date, indexed)
├── metrics_json (json — shape estável agregado)
├── source_hash (varchar 64 — invalidation tracking)
├── generated_at (timestamp)
├── elapsed_ms (smallint — observability D9.a)
└── UNIQUE (business_id, snapshot_date)
```

Por quê snapshot vs realtime:
- 30+ businesses × 13 KPIs × dezenas-de-milhares rows = ~3-8s realtime por business → UX ruim
- Snapshot cron daily 03:00 BRT calc todos businesses; Page reads 1 row + Inertia::defer

**Realtime fallback (opt-in):** botão "Atualizar agora" no Page que dispara `ScorecardRefreshNowJob($businessId)` queued — caso Larissa queira ver state-of-the-moment.

### 3.3 URL + permission

| Rota | Audiência | Permission Spatie | Render |
|---|---|---|---|
| `/scorecard` | cliente (Larissa biz=4) | `scorecard.view#{biz}` | `Inertia::render('Scorecard/Index', [...])` com `business_id = session()` |
| `/admin/scorecard` | Wagner superadmin | `scorecard.admin.cross-tenant` | `Inertia::render('Scorecard/AdminIndex', [...])` lista 30+ biz |
| `/admin/scorecard/{businessId}` | Wagner superadmin | `scorecard.admin.cross-tenant` | drill-down per business (reusa Index com `withoutGlobalScopes` SUPERADMIN) |

**Não** usar `/scorecard?biz=N` único — risca leak (request param manipulável). Routes separadas + permission middleware.

### 3.4 UX components

Reusar:
- `<KpiCard>` se existe (Wave 24 Governance V4 — checar `resources/js/Components/Kpi*.tsx`). Se não existe, criar em `resources/js/Components/Scorecard/KpiCard.tsx` (compartilhável).
- Charts: já existe Recharts em uso em `Modules/Crm` Wave 27/28 — imitar
- `<Deferred data="..." fallback={<KpiSkeleton/>}>` — pattern canônico

### 3.5 Cron schedule

```php
// app/Console/Kernel.php
$schedule->command('scorecard:snapshot-all-business')
    ->dailyAt('03:00')
    ->timezone('America/Sao_Paulo')
    ->onOneServer()
    ->withoutOverlapping(30); // mutex 30min
```

Comando dispara `dispatch(new ScorecardSnapshotPerBusinessJob($bizId))` per business → processa em fila Horizon CT 100.

### 3.6 NÃO reusar `ScopedScorecardEvaluator`

Domínio técnico vs comercial — sem overlap funcional. Apenas **inspirar pattern** (snapshot daily + drift detection + alerts).

Drift detection **adaptado** pra Scorecard comercial:
- MRR queda >10% MoM → alerta superadmin Wagner
- Inadimplência subiu >R$ [redacted Tier 0]k semana → alerta cliente + Wagner

---

## Fase 4 — CHECKLIST PRÉ-CÓDIGO

```markdown
## Pré-código checklist — Modules/Scorecard (KPIs comerciais)

### Antes de scaffold (decisões críticas — Wagner aprova)

- [ ] **Nome final**: `Modules/Scorecard` (Wagner já aprovou) — registrar em ADR gênese pra futuro evitar confusão com Governance.ScopedScorecardEvaluator
- [ ] **Bucket `module.json`**: `cross_cutting_infra` (proposto) — ou novo `core_dashboards`? Wagner decide
- [ ] **ADR gênese**: SIM — escopo é grande, multi-tenant, multi-fonte. Criar `memory/decisions/NNNN-modulo-scorecard-kpis-comerciais.md` ANTES de scaffold
- [ ] **Pré-reqs SoC extract** (sub-tasks ANTES de Scorecard consumir):
  - [ ] `Modules/Financeiro/Services/FinanceiroKpiService` — extrair `calcularKpis()` de DashboardController:103
  - [ ] `Modules/RecurringBilling/Services/MrrChurnService` — CRIAR método agregador
  - [ ] `Modules/NfeBrasil/Services/NfeMetricsService` — CRIAR (emitidas/canceladas/rejeitadas 30d)
- [ ] **Decidir realtime vs snapshot**: snapshot daily + refresh-now botão (recomendado) — ou só realtime cache 5min?
- [ ] **Decidir HomeController coexistência**: link novo "Scorecard" no menu primeiro; deprecate /home dashboard depois canary 7d
- [ ] **Decisão narrativa IA**: Scorecard tem ou não tem narrativa OpenAI? Recomendação: **NÃO** (é tabela numérica; Brief já narra). Se SIM → custo Brain B +R$ X/m
- [ ] **Decisão k-anonymity benchmark**: cross-tenant agregado só Wagner identificável OU criar pilar Insights anonimizado ≥5? Recomendação: **identificável só Wagner** primeira release; benchmark anonimizado vira ADR feature-wish

### Antes de Edit/Write (Pré-flight 3 fases mexeu-registra)

- [ ] Ler skill `criar-modulo` Tier B + `memory/requisitos/Infra/RUNBOOK-criar-modulo.md` (8 peças canon)
- [ ] Ler `Modules/Brief/` como referência de orquestração (ADR 0091 imita topologia)
- [ ] Ler `Modules/Jana/Services/HealthSnapshotService.php` como referência de agregador
- [ ] Ler `Modules/Financeiro/Http/Controllers/DashboardController.php` como referência de Inertia::defer

### Pegadinhas Tier 0 críticas a respeitar

- [ ] **Multi-tenant**: business_id global scope em `scorecard_snapshots`; Wagner superadmin com comentário `// SUPERADMIN`
- [ ] **Job constructor `$businessId`**: `ScorecardSnapshotPerBusinessJob::__construct(int $businessId)`
- [ ] **Inertia::defer DEFAULT**: TODA prop com KPI deferida — controller fica skinny
- [ ] **PII redactor**: alertas que mencionem cliente individual passam por `PiiRedactor`
- [ ] **LGPD**: cross-tenant agregado fica restrito superadmin; benchmark anonimizado fica pra V2 com k-anonymity ≥5
- [ ] **Roles Spatie**: `scorecard.view#{biz}` (cliente) + `scorecard.admin.cross-tenant` (Wagner) — sufixo per-biz
- [ ] **MWART**: criar `memory/requisitos/Scorecard/RUNBOOK-index.md` ANTES de `resources/js/Pages/Scorecard/Index.tsx`
- [ ] **Charter**: `resources/js/Pages/Scorecard/Index.charter.md` ao lado
- [ ] **format_now_local**: KPIs "agora/30d" usa `format_now_local`, não `format_date` (+3h shift)
- [ ] **Identifiers ≤64 chars**: índices compostos com nome explícito

### Pontos de plugue (ordem sugerida — sprint mapeada)

#### Sprint 0 (SoC extract — pré-req)
- [ ] PR 1: `FinanceiroKpiService::calcular($businessId): array` (extrai DashboardController:103-145)
- [ ] PR 2: `MrrChurnService` (RecurringBilling, novo)
- [ ] PR 3: `NfeMetricsService` (NfeBrasil, novo)

#### Sprint 1 (Scorecard scaffold)
- [ ] Module skeleton (8 peças canon): `php artisan module:make Scorecard` + manual hooks
- [ ] Migration `scorecard_snapshots` table
- [ ] `BusinessMetricsAggregator::snapshot(int $businessId): array`
- [ ] `PlatformMetricsAggregator::snapshot(): array` (cross-tenant superadmin)
- [ ] Pest cross-tenant test (biz=1 vs biz=99 isolamento)

#### Sprint 2 (cron + jobs)
- [ ] `scorecard:snapshot-all-business` command
- [ ] `ScorecardSnapshotPerBusinessJob`
- [ ] Schedule daily 03:00 BRT
- [ ] Drift detection >10% MRR queda → mcp_alertas
- [ ] Pest snapshot job idempotente + multi-tenant

#### Sprint 3 (UI)
- [ ] RUNBOOK `memory/requisitos/Scorecard/RUNBOOK-index.md`
- [ ] Page `resources/js/Pages/Scorecard/Index.tsx` + `Index.charter.md`
- [ ] Page `resources/js/Pages/Scorecard/AdminIndex.tsx` (cross-tenant)
- [ ] Componente `<KpiCard>` shared (se não existe) + `<KpiSkeleton>`
- [ ] Inertia::defer em todas props aggregated
- [ ] Mobile 1280px Larissa monitor responsive grid

#### Sprint 4 (Polish + docs)
- [ ] BRIEFING `memory/requisitos/Scorecard/BRIEFING.md` (skill `brief-update` Tier B)
- [ ] SPEC `memory/requisitos/Scorecard/SPEC.md`
- [ ] Permissions Spatie seeder
- [ ] Sidebar entry
- [ ] Pest E2E smoke routes

### Smoke pós-deploy

- [ ] **biz=1 (test)** — Pest: snapshot calcula sem erro, retorna shape estável
- [ ] **biz=1 (test)** — Pest cross-tenant: biz=99 NUNCA vê biz=1 (Tier 0 IRREVOGÁVEL)
- [ ] **biz=4 (ROTA LIVRE canary)** — smoke prod opt-in: Larissa abre `/scorecard`, vê KPIs próprios, **NÃO** vê biz=1 ou outros
- [ ] **superadmin Wagner** — abre `/admin/scorecard`, vê lista 30+ biz, drill-down funciona

### Estimativa total (IA-pair fator 10x — ADR 0106)

| Sprint | Escopo | IA-pair wallclock |
|---|---|---|
| Sprint 0 (SoC extract) | 3 services novos/extraídos + tests | ~1.5 dias |
| Sprint 1 (scaffold + aggregator) | 8 peças canon + 2 services + migration + Pest cross-tenant | ~2 dias |
| Sprint 2 (cron + jobs) | command + job + schedule + drift detection + Pest | ~1 dia |
| Sprint 3 (UI) | RUNBOOK + 2 Pages + charter + components + defer | ~2 dias |
| Sprint 4 (polish + docs) | BRIEFING + SPEC + permissions + smoke prod canary 7d | ~1 dia (humano-limitado) |
| **Total** | | **~7.5 dias wallclock IA-pair + 7d canary humano** |

Margem 2x = 15 dias wallclock IA-pair worst case.
```

---

## Resumo executivo (anexo Wagner)

### Recomendação principal

**CRIAR `Modules/Scorecard` como AGREGADOR THIN — não re-implementar KPIs.** Pattern de referência: `Modules/Jana/Services/HealthSnapshotService` (já agrega 4 fontes superadmin) + `Modules/Brief` (topologia cron + service + tool MCP).

### Top 5 KPIs já existentes (reusar)

1. **Faturamento period** — `app/Utils/TransactionUtil::getSellsCurrentFy($businessId, $start, $end)`
2. **AR/AP/Recebido/Pago mês** — `Modules/Financeiro/Http/Controllers/DashboardController::calcularKpis()` (extrair pra service)
3. **OS Repair stats** — `Modules/Repair/Utils/RepairUtil::getRepairByStatus($businessId)` + 4 outros
4. **CRM pipeline + forecast weighted** — `Modules/Crm/Services/DealPipelineService::pipelineSummary($businessId)` + `forecastFechamento`
5. **WhatsApp outbound metrics** — `Modules/Whatsapp/Services/Metrics/MetricsSnapshotBuilder::snapshotOutbound($businessId)`

### Top 5 KPIs a CRIAR do zero

1. **MRR/Churn** — `Modules/RecurringBilling/Services/MrrChurnService` (novo)
2. **NFe metrics 30d** — `Modules/NfeBrasil/Services/NfeMetricsService` (novo)
3. **Estoque parado / abaixo mínimo** — inventariar `Modules/ProductCatalogue` (este pre-flight não cobriu — sub-task descoberta)
4. **Ticket médio por vendedor** — extender `TransactionUtil` ou criar `Modules/Sells/Services/SalespersonKpiService`
5. **Snapshot drift detection** — MRR queda >10% MoM, inadimplência >R$ [redacted Tier 0]k semana → mcp_alertas

### 3 pegadinhas Tier 0 críticas

1. **Inertia::defer DEFAULT** — Scorecard tem 15-20 props aggregated; sem defer Controller fica lento e UX percebe "carregando página inteira" (D-14 incident pattern)
2. **Multi-tenant Tier 0 IRREVOGÁVEL** — Larissa biz=4 só vê biz=4. `withoutGlobalScopes` exige comentário `// SUPERADMIN`. Job cron passa `$businessId` constructor
3. **MWART canônico** — Page Inertia `/scorecard` EXIGE `memory/requisitos/Scorecard/RUNBOOK-index.md` antes; hook bloqueia em runtime

### Decisão arquitetural principal

**Agregador thin (`BusinessMetricsAggregator`) consome services existentes via DI** — não re-implementa cálculos. Pré-requisito: 3 SoC extracts (FinanceiroKpiService, MrrChurnService, NfeMetricsService) antes do scaffold Scorecard.

### Próximo passo concreto pro Wagner

1. **Aprovar checklist 4 decisões críticas Sprint 0** (nome, bucket, ADR gênese sim, narrativa IA não)
2. **Decidir prioridade vs outras iniciativas** (Vestuario/ComVis/OficinaAuto verticais; OfficeImpressoSnapshot pago R$ [redacted Tier 0]-599/m)
3. **Quando aprovado**: invocar `criar-modulo` skill + spawn 3 PRs Sprint 0 (SoC extracts) em paralelo via `coordenador-paralelo`
4. **NÃO scaffoldar Scorecard antes** dos 3 SoC extracts mergeados — drift entre HomeController/DashboardController e Scorecard agregador é inevitável se pular

---

**Fim do pre-flight.** Sem código gerado, sem ADR criada, sem task MCP. Doc canônico ~330 linhas.
