---
title: "Onda 5 — Dossier executável pré-implementação (95% → 98% maturidade)"
type: dossier
status: draft
authority: tecnico-estrategico
lifecycle: ativo
quarter: Q2-2026
decided_at: 2026-05-13
decided_by: [audit-senior-expert]
module: Jana
tier: STRATEGIC_AUDIT
trust_level: advise
related_adrs: [0037, 0053, 0061, 0062, 0070, 0091, 0093, 0094, 0095, 0104, 0106, 0119, 0130, 0131]
parent_artifacts:
  - memory/requisitos/Jana/GAP-ANALYSIS-91-100-2026-05-13.md
  - memory/requisitos/Jana/COMPARATIVO-MCP-ESTADO-DA-ARTE-2026-05-13.md
  - memory/requisitos/Jana/AUDITORIA-KNOWLEDGE-ARCHITECTURE-2026-05-13.md
  - memory/requisitos/Jana/AUDITORIA-SESSION-HANDOFF-2026-05-13.md
  - memory/reference/aprendizados-onda1-2-3-2026-05-13.md
authors: [audit-senior-expert]
---

# Onda 5 — Dossier executável pré-implementação

> **Auditor:** `audit-senior-expert` (Opus 4.7) — sessão `nervous-mayer-3ff0da`.
> **Missão:** transformar 5 gaps P1 estruturais da Onda 5 (gap-analysis §2.1) em blueprint executável pros 5 agents implementadores Fase 3 do `/audit-and-fix`.
> **Pesquisa profunda:** 31 WebSearch + 4 WebFetch focados (5-7 fontes por gap).
> **Decisão arquitetural por gap:** bibliotecas/frameworks escolhidos com fonte 2025-2026 + alternativas rejeitadas + razão.

---

## TL;DR — pra Wagner ler primeiro

- **Score atual:** 91% global (pós-Onda 3) — projeção pós-Onda 4 (R1+L1+C1) = **~95%**.
- **Score alvo Onda 5:** **~98%** global (MCP 91→97% · Knowledge 97→98% · Handoff 94→96%).
- **5 gaps Onda 5 confirmados** (gap-analysis §2.1 + complemento A1 estrutural):
  1. **K1** — Time-decay weighting recall (2.5d) — `MeilisearchDriver` composite score relevance×recency×importance
  2. **V1** — Roadmap timeline UI (4d) — `/copiloto/admin/roadmap` SVAR Gantt MIT + sub-issues hierarchy
  3. **H1** — Auto-skeleton handoff (1d) — tool MCP `handoff-draft` lê git log + cycles + tasks
  4. **S1** — Schema rígido CI (1.5d) — `remark-lint-frontmatter-schema` + artisan validator
  5. **A1** — Auto-summary docs longos (2d) — map-reduce gpt-4o-mini + cache 24h (estrutural, pós-Langfuse Onda 4)
- **Esforço total:** **11 dev-days IA-pair** (~6d calendário, fator 10x [ADR 0106](../../decisions/0106-recalibracao-velocidade-fator-10x-ia-pair.md))
- **Custo infra adicional:** R$ [redacted Tier 0] (tudo CT 100 existente, gpt-4o-mini A1 ~R$ [redacted Tier 0]/mês)
- **Sequência:** paralelo total 5 agents (zero overlap de paths) — pré-requisito CRÍTICO Onda 4 fechada
- **Surpresa estratégica:** **Prompt caching Anthropic destrava 90% custo cache hits em A1** ([Prompt Caching Guide 2026](https://tokenmix.ai/blog/prompt-caching-guide)) — não estava no gap-analysis original; muda economics de A1 de "custo+latência" pra "praticamente grátis após 1º hit"

---

## 1. Sequência recomendada — paralelo total

Os 5 gaps Onda 5 têm áreas isoladas confirmadas (zero overlap entre paths) → spawn paralelo seguro, padrão validado nas Ondas 1-3 (17 agents, 0 conflitos). Parent consolida 5 PRs no final.

```
spawn paralelo (1 worktree, 5 agents):
  ├─ agent-K1: Modules/Jana/Services/Memoria/* (driver + score function)
  ├─ agent-V1: Modules/Copiloto/Http/Controllers/Admin/Roadmap* + resources/js/Pages/Admin/Roadmap/*
  ├─ agent-H1: Modules/Jana/Mcp/Tools/HandoffDraftTool.php + Services/Handoff/HandoffDrafterService.php
  ├─ agent-S1: scripts/validate-frontmatter.* + .github/workflows/memory-schema-lint.yml + memory/decisions/_SCHEMA.md
  └─ agent-A1: Modules/Jana/Mcp/Support/DocSummarizer.php (decorator) + Modules/Jana/Ai/Services/ChunkedSummarizerService.php

ZERO arquivos compartilhados editados (shared files apenas LIDOS por todos: CLAUDE.md, ADRs, módulos vizinhos pra mimetizar)
```

**Pré-requisito CRÍTICO:** Onda 4 (R1+L1+C1) precisa estar **mergeada** antes de Onda 5 começar.
- **R1 Reranker** habilita K1 medição NDCG real (sem reranker, time-decay melhora *recall* mas não NDCG@10 da camada hybrid)
- **L1 Langfuse** habilita instrumentação K1+A1 — sem Langfuse, ganho time-decay e ROI auto-summary ficam em fé (princípio 4 Constituição v2)
- **C1 Charters S4** independente dos demais (não bloqueia Onda 5)

---

## 2. K1 — Time-decay weighting recall

### Contexto + sintoma

`MeilisearchDriver` ([Modules/Jana/Services/Memoria/MeilisearchDriver.php](../../../Modules/Jana/Services/Memoria/MeilisearchDriver.php)) hoje retorna score **puramente semântico/lexical** (Meilisearch hybrid 60/40 vector+BM25 com RRF Onda 3). Documentos antigos (`lifecycle: historical`, ADRs supersedidas) competem com canônicos recentes em pé-de-igualdade. Recall de queries multi-dia tende a misturar regras vigentes com revogadas.

[AUDITORIA-KNOWLEDGE](AUDITORIA-KNOWLEDGE-ARCHITECTURE-2026-05-13.md) §R5 mede esta dimensão em **0%**. [Towards Data Science — RAG is Blind to Time](https://towardsdatascience.com/rag-is-blind-to-time-i-built-a-temporal-layer-to-fix-it-in-production/) demonstra padrão de "stale recall" idêntico em produção: docs de 2024 vencendo docs de 2026 só porque cosine bate melhor.

### 5 alternativas pesquisadas

| Alternativa | Paradigma | Pros | Contras | Custo |
|---|---|---|---|---|
| **A. Half-life decay custom dentro do MeilisearchDriver** | `final = (1-w)*hybrid + w*exp(-ln(2)*age/half_life)` w=0.4 | Zero infra nova; respeita ADR 0061; controle total; per-doc-type half_life | Manutenção próprio (testes Pest); precisa Langfuse pra medir delta | 0 infra |
| **B. Migrar pra Zep/Graphiti temporal KG** | Bi-temporal edges (`t_valid`/`t_invalid`) + community subgraph | LongMemEval 63.8% (vs 49% Mem0); fact supersession automática ([Zep paper 2501.13956](https://arxiv.org/abs/2501.13956)) | Refactor 15-50d; perde git canon; Python-only; viola ADR 0061 + CONSOLIDAR rejeitado | 50d + R$ [redacted Tier 0]/mês |
| **C. Meilisearch native custom ranking rules** | Adicionar regra `desc(decay_score)` na lista de ranking rules | Native engine; sem código PHP | Meilisearch [não tem função decay built-in](https://www.meilisearch.com/docs/learn/relevancy/custom_ranking_rules) — só `asc/desc` em campo numérico, precisa pre-compute via cron + reindex (caro) | 0 infra + 4h cron |
| **D. Ragie-style recency_boost API** | Boost configurable per-query (multiplicador score) | Simples toggle no caller | Requer pre-armazenar timestamp + lib externa; já fizemos hybrid in-house | R$ [redacted Tier 0]/mês |
| **E. Hippo-memory biologically-inspired** | Retrieval strengthening + consolidation | Zero dependencies; decay automático | Repo experimental sem produção; arquitetura completamente diferente | refactor total |

### Escolha técnica + razão

**Opção A — Half-life decay custom dentro do MeilisearchDriver** (rejeitar B/C/D/E).

**Razão:**
1. **Respeita CONSOLIDAR** (gap-analysis §5 + [ADR 0061](../../decisions/0061-conhecimento-canonico-git-mcp-zero-automem.md)) — zero migração paradigma
2. **Fonte oficial:** [TDS Temporal Layer 2026](https://towardsdatascience.com/rag-is-blind-to-time-i-built-a-temporal-layer-to-fix-it-in-production/) prescreve `decay = 0.5^(age/half_life)` + `temporal_weight=0.4` (40% time, 60% meaning) — provado em produção com NDCG ranking shifts qualitativos
3. **Per-doc-type half_life** (configurável via `lifecycle_decay_map.php`):
   - `accepted` ADR: half_life=∞ (decay_rate=0)
   - `historical`/`superseded` ADR: half_life=90d (decay_rate=0.5/90)
   - SPEC/session: half_life=365d
   - Handoff: half_life=30d (estado vivo, decai rápido pós-30d)
4. **Composite score formal:**
   ```php
   final_score = (1 - $w) * $hybrid_score
               + $w * ($decay_score * $importance_multiplier);
   // onde $w = config('jana.recall.temporal_weight', 0.4)
   //       $decay_score = pow(0.5, $age_days / $half_life_days)
   //       $importance_multiplier = match($doc->lifecycle) {
   //         'accepted' => 1.2, 'historical' => 0.5, ...
   //       }
   ```

### Áreas isoladas (paths exatos pra agent K1)

- **Edit:** [Modules/Jana/Services/Memoria/MeilisearchDriver.php](../../../Modules/Jana/Services/Memoria/MeilisearchDriver.php) — adicionar método `applyTemporalDecay(array $hits): array`
- **Create:** `Modules/Jana/Services/Memoria/TemporalDecayCalculator.php` — service pura (testável)
- **Create:** `config/jana-recall.php` — `lifecycle_decay_map`, `temporal_weight`, `enable_decay`
- **Create:** `tests/Feature/Jana/Memoria/TemporalDecayTest.php` — Pest fixtures cross-tenant biz=1 vs biz=99
- **Migration (se needed):** `mcp_memory_documents` já tem `published_at`/`updated_at` — confirmar índice composto `(lifecycle, updated_at)` pra performance

### Pré-requisitos

- ✅ R1 Reranker Onda 4 mergeado (sem reranker, TempDecay melhora recall mas não NDCG@10 da camada hybrid)
- ✅ L1 Langfuse Onda 4 mergeado (precisa medir NDCG delta antes/depois com 50 queries dataset Wagner)
- ⚠️ **Wagner aprova multipliers:** `accepted=1.2`, `historical=0.5`, half-life days por type — config files ficam parametrizados, mas defaults exigem ok inicial

### Pest scope mínimo

```php
// tests/Feature/Jana/Memoria/TemporalDecayTest.php
it('boosts recent ADR accepted vs historical superseded')
it('respects business_id global scope cross-tenant biz=1 vs biz=99')  // Tier 0
it('returns hybrid_score when temporal_weight=0 (feature flag off)')
it('handles missing published_at gracefully (fallback created_at)')
it('mock: half_life=30d → 30d-old doc score = 0.5 × full_score')
```

### RUNBOOK

NÃO necessário (refactor interno service, não nova tela). Mas atualizar [RETRIEVAL-GOTCHAS.md](RETRIEVAL-GOTCHAS.md) com gotcha #15: "Eloquent `updated_at` muda em saves não-canônicos — usar `published_at` se houver, fallback `created_at`".

### Risco/mitigação

- **Risco:** decay agressivo (half_life muito curto) afoga ADRs estruturais antigas (ex: ADR 0011 padrão Jana, ano+ de idade mas canônica). **Mitigação:** `lifecycle: accepted` recebe `decay_rate=0` (preservação infinita) — só `historical`/`superseded` decaem.
- **Risco:** Wagner não tem dataset de 50 queries pra medir NDCG delta. **Mitigação:** L1 Langfuse Onda 4 captura queries reais 14d → usar essas pra baseline.

---

## 3. V1 — Roadmap timeline UI

### Contexto + sintoma

[COMPARATIVO-MCP](COMPARATIVO-MCP-ESTADO-DA-ARTE-2026-05-13.md) §3.5 mede Viz em **5%**. Listas de tasks são markdown via tools MCP — sem visualização cronológica, sem dependency graph, sem sub-issues nested. Linear/Plane/GitHub Projects vão na frente em viz há 5+ anos. Wagner mencionou múltiplas vezes "queria ver o cycle como Gantt".

[GitHub Projects Hierarchy GA mar/2026](https://github.blog/changelog/2026-03-19-hierarchy-view-in-github-projects-is-now-generally-available/) virou padrão de mercado: sub-issues 8 níveis + drag-drop reparent.

### 5 alternativas pesquisadas

| Lib | Licença | Bundle | React 19 | Drag-drop | Sub-issues | Preço |
|---|---|---|---|---|---|---|
| **SVAR React Gantt** | MIT (core) | ~80 KB | ✅ nativo TypeScript | ✅ tasks + dependencies | 🟡 via parent_id manual | Free ([svar.dev](https://svar.dev/react/gantt/)) |
| **DHTMLX Gantt Standard** | GPLv2 | ~200 KB | 🟡 wrapper | ✅ | ✅ tasks tree | Free GPL / PRO comercial |
| **Frappe Gantt** | MIT | ~50 KB SVG | 🟡 sem wrapper React oficial | 🟡 básico | ❌ | Free |
| **Bryntum Gantt** | Comercial | ~400 KB | ✅ | ✅ tudo | ✅ enterprise | $900/dev |
| **react-timeline-gantt (guiqui)** | MIT | ~100 KB | 🟡 React 16-18 testado | ✅ | 🟡 virtual rendering 100k records | Free |

### Escolha técnica + razão

**SVAR React Gantt MIT** ([SVAR Gantt 2.4](https://medium.com/@SvarWidgets/svar-gantt-2-4-a-modern-gantt-chart-library-for-react-svelte-under-the-mit-license-ae62f36a5dde)).

**Razão:**
1. **MIT (core)** — única opção React-nativa MIT em 2026; permite self-host CT 100/Hostinger sem royalty
2. **React 19 nativo** ([Top 5 React Gantt 2026 SVAR Blog](https://svar.dev/blog/top-react-gantt-charts/)) — stack oimpresso já em React 19 (CLAUDE.md what-oimpresso)
3. **Performance:** SVAR vence loading speed/CRUD/live updates no benchmark independente ([gantt-performance benchmark](https://github.com/svar-widgets/gantt-performance)); demo 10k tasks
4. **Drag-drop dependencies built-in** — End-to-start / Start-to-start / End-to-end / Start-to-end
5. **Rejeita DHTMLX:** GPLv2 viral propaga pra módulos comerciais oimpresso (cliente pago precisa receber código fonte se distribuir)
6. **Rejeita Bryntum:** $900/dev = R$ [redacted Tier 0]/dev × 5 devs = R$ [redacted Tier 0] upfront (viola [ADR 0094 §4](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md) custo)
7. **Rejeita Frappe:** sem React wrapper oficial; sub-issues não nativo
8. **Rejeita react-timeline-gantt (guiqui):** não testado React 19 + manutenção esporádica

### Áreas isoladas (paths exatos pra agent V1)

- **Create:** `Modules/Copiloto/Http/Controllers/Admin/RoadmapController.php` — `@index()` lê `mcp_tasks` + `mcp_cycles` + `mcp_task_links` (blocked_by[])
- **Create:** `Modules/Copiloto/Http/Resources/RoadmapTaskResource.php` — shape compatível com SVAR Gantt task object
- **Create:** `resources/js/Pages/Admin/Roadmap/Index.tsx` — page Inertia consumindo Resource
- **Create:** `resources/js/Pages/Admin/Roadmap/_components/RoadmapGantt.tsx` — wrapper SVAR
- **Create:** `resources/js/Pages/Admin/Roadmap/_components/SubIssuesPanel.tsx` — hierarchy view (parent_task_id)
- **Create:** `resources/js/Pages/Admin/Roadmap/Index.charter.md` — charter MWART canon
- **Edit:** `Modules/Copiloto/Resources/views/sidebar.blade.php` ou DataController hook — adicionar entry sidebar "Roadmap" ([sidebar-menu-arch](../../../.claude/skills/sidebar-menu-arch/SKILL.md))
- **Edit:** `routes/admin.php` (Copiloto) — `Route::get('/copiloto/admin/roadmap', RoadmapController::class)->middleware(['web','auth',...])`
- **Migration:** `mcp_tasks` JÁ tem `parent_task_id` ou similar? Auditar — se não, ADD COLUMN nullable
- **Composer/npm:** `npm i @svar-widgets/react-gantt`

### Pré-requisitos

- ✅ Onda 4 (independente, mas C1 charter ajuda template)
- ⚠️ **Wagner aprova rota `/copiloto/admin/roadmap`** vs `/admin/tasks/roadmap` (consistência rotas Copiloto)
- ⚠️ **Wagner aprova npm dep @svar-widgets/react-gantt** — primeira lib Gantt no projeto
- ⚠️ **Schema sub-issues:** confirmar `mcp_tasks.parent_task_id` existe ou criar migration

### Pest scope mínimo

```php
// tests/Feature/Copiloto/Admin/RoadmapTest.php
it('renders roadmap with tasks of current business_id only')  // Tier 0
it('respects cross-tenant biz=99 cannot see biz=1 tasks')
it('groups tasks by cycle and sorts by due_date')
it('serializes blocked_by[] as dependencies for SVAR Gantt format')
it('handles tasks without due_date (placement: backlog lane)')
```

### RUNBOOK

**SIM** — `memory/requisitos/Copiloto/RUNBOOK-roadmap.md` seguindo template Cockpit Pattern V2 ([ADR 0110](../../decisions/0110-cockpit-layout-v2-padrao.md)). Skill `cockpit-runbook` gera.

### Risco/mitigação

- **Risco:** SVAR 2.4 é recente (2026-Q1) — bugs não-descobertos. **Mitigação:** começar com flat view (sem nested sub-issues); ativar hierarchy iterativamente; flag `feature.roadmap_hierarchy_enabled` default false.
- **Risco:** 100+ tasks viram cluttered no Gantt monitor 1280px (Larissa). **Mitigação:** filtro padrão "current cycle" + lazy load cycles passados.

---

## 4. H1 — Auto-skeleton handoff-draft

### Contexto + sintoma

[AUDITORIA-SESSION-HANDOFF](AUDITORIA-SESSION-HANDOFF-2026-05-13.md) §3 dim #4 mede auto-capture em **30%**. Wagner escreve handoff manual ~10-20min × várias/dia = ~1h/dia gasto em narrativa estado. Anthropic Claude Code compaction nativa faz isso, mas é lossy e sobrescreve. Mercado: [AgentDiff (Sunil Mallya)](https://github.com/sunilmallya/agentdiff) gera "reasoning summaries from actual diffs at session end"; [Session Handoff skill (softaworks)](https://github.com/softaworks/agent-toolkit/blob/main/skills/session-handoff/README.md) cria "handoff documents that enable fresh AI agents to seamlessly continue work".

### 5 alternativas pesquisadas

| Abordagem | Como funciona | Pros | Contras | Custo |
|---|---|---|---|---|
| **A. Tool MCP `handoff-draft` (Laravel + git CLI + gpt-4o-mini)** | tool MCP lê git log `origin/main..HEAD` + `cycles-active` + `tasks-list status:doing` + diff vs último handoff via [HandoffDiffTool](../../../Modules/Jana/Mcp/Tools/HandoffDiffTool.php) → 1 chamada LLM rascunha `.md` template canônico ADR 0130 | Zero infra nova; reusa H3 (Onda 3); ADR 0130 append-only respeitado; mock mode | Manutenção rascunho qualidade (Wagner revisa antes de commit) | ~R$ [redacted Tier 0]/handoff |
| **B. Pre-commit hook Git auto-handoff** | `pre-commit` hook captura diff + chama LLM + cria `memory/handoffs/...md` automaticamente | Zero ação Wagner | Hook bloqueia commits comuns; viola "Wagner revisa" princípio | ~R$ [redacted Tier 0]/commit (caro!) |
| **C. PreCompact hook Claude Code custom** | [Claude Code PreCompact hooks](https://medium.com/@porter.nicholas/claude-code-post-compaction-hooks-for-context-renewal-7b616dcaa204) salvam contexto antes de compactar | Captura context FULL (não só git); Anthropic native | Só funciona DENTRO da Claude Code session; não acessível como tool MCP externa (Eliana/Felipe não usam Claude Code igual) | 0 (Anthropic prompt caching) |
| **D. AgentDiff-style** | [AgentDiff](https://github.com/sunilmallya/agentdiff) gera reasoning de diffs Git Blame for AI agents | Provado mercado | Python-only; integration Laravel exige bridge | 0 self-host |
| **E. CrewAI Memory cross-session + Mem0** | [CrewAI + Mem0 production setup](https://mem0.ai/blog/crewai-memory-production-setup-with-mem0) gerencia handoff entre agents nativamente | Framework completo | Refactor agentes Jana (BriefDiarioAgent etc) → viola CONSOLIDAR | 50d + R$ Mem0 |

### Escolha técnica + razão

**Opção A — Tool MCP `handoff-draft`** (rejeitar B/C/D/E).

**Razão:**
1. **MCP-first** alinha com [ADR 0053](../../decisions/0053-mcp-server-governanca-como-produto.md) (governança como produto) — tool exposed pra TODOS devs (Wagner, Eliana, Felipe, Maiara, Luiz) via Claude Code OU WhatsApp slash
2. **Append-only respeitado** ([ADR 0130](../../decisions/0130-handoff-append-only-mcp-first.md)) — tool RASCUNHA mas Wagner Write final; nunca sobrescreve
3. **Reusa H3 Onda 3** (`handoff-diff` já existe) — só falta o tool wrapper que orquestra + chama LLM
4. **Custo:** ~R$ [redacted Tier 0]/handoff (gpt-4o-mini, input ~3k tokens git log + ~2k tasks + ~1k diff = 6k input; output ~800 tokens template) — alinha com [ADR 0094 §4](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md)
5. **Mock mode obrigatório** (lição §7 aprendizados): `HandoffDrafterService::enableMock($skeleton)` pra Pest local sem chave OpenAI
6. **Template ADR 0130:** tool emite estrutura YAML frontmatter + seções `## Estado MCP no momento do fechamento` + `## Próximos passos` + `## Bloqueios`

### Áreas isoladas (paths exatos pra agent H1)

- **Create:** `Modules/Jana/Mcp/Tools/HandoffDraftTool.php` — JSON-RPC schema input: `cycle_id?`, `since_hours?` (default 24), `format?` (default md)
- **Create:** `Modules/Jana/Services/Handoff/HandoffDrafterService.php` — orquestra `git log --oneline origin/main..HEAD` (via Process) + `CyclesActiveTool::handle()` + `TasksListTool::handle(filters: {status:doing})` + `HandoffDiffTool::handle()` → monta prompt LLM
- **Edit:** `Modules/Jana/Providers/OimpressoMcpServer.php` — registrar `HandoffDraftTool::class` em `$tools` array (CONFLITO POTENCIAL: outros agents NÃO podem editar este arquivo — agent H1 é único que toca)
- **Create:** `tests/Feature/Jana/Mcp/HandoffDraftToolTest.php` — Pest com `HandoffDrafterService::enableMock(skeleton: [...])`
- **Edit:** `memory/decisions/0130-handoff-append-only-mcp-first.md` — APPEND-ONLY: criar ADR nova `NNNN-handoff-draft-tool-mcp.md` referenciando 0130 (ADR canon append-only — não editar)

### Pré-requisitos

- ✅ Onda 3 H3 `handoff-diff` mergeado (já está em prod desde 2026-05-13)
- ⚠️ **`OimpressoMcpServer.php` shared file conflict:** apenas agent H1 edita; demais 4 agents NÃO mexem (S1, V1, K1, A1 não precisam registrar tool nova)

### Pest scope mínimo

```php
// tests/Feature/Jana/Mcp/HandoffDraftToolTest.php
it('renders frontmatter ADR 0130 compliant (title, type, decided_at, ...)')
it('includes "## Estado MCP no momento do fechamento" section')
it('respects business_id global scope when querying mcp_tasks')  // Tier 0
it('handles empty git log gracefully (no commits since last handoff)')
it('mock: returns deterministic skeleton without LLM call')  // RAGAS_FORCE_MOCK pattern
it('caps git log to last 100 commits (cost guard)')
```

### RUNBOOK

NÃO necessário (tool MCP backend, sem UI). Atualizar [how-trabalhar.md §Ao terminar uma sessão](../../how-trabalhar.md) adicionando passo "0. (opcional) `handoff-draft` rascunha esqueleto — você revisa + completa + Write".

### Risco/mitigação

- **Risco:** rascunho LLM hallucina commits que não existem. **Mitigação:** prompt force "ONLY use facts from git log + tools output below; do NOT infer"; Wagner revisa antes de Write final.
- **Risco:** custo escala se Wagner abusa (1 chamada/15min). **Mitigação:** cache 5min por (business_id, since_hours) — chamadas repetidas retornam cached.

---

## 5. S1 — Schema rígido CI validation

### Contexto + sintoma

[AUDITORIA-KNOWLEDGE](AUDITORIA-KNOWLEDGE-ARCHITECTURE-2026-05-13.md) §S4 mede schema validation em **40%** (só ADR via [.github/workflows/adr-lint.yml](../../../.github/workflows/adr-lint.yml)). SPEC.md, RUNBOOK*.md, session/handoff `.md` SEM validação — drift de frontmatter é silencioso. Exemplo real: ADR sem `lifecycle:` field passa, depois `decisions-search` MCP devolve doc malformado.

[adr-architecture-kit (egallmann)](https://github.com/egallmann/adr-architecture-kit) é referência canônica 2026 — "machine-verifiable ADRs with Pydantic models". [Letta Memory schema validation](https://docs.letta.com/letta-code/memory/) afirma "Mem0 has no schema on extracted facts. No structural validation. No provenance chain" como anti-pattern.

### 5 alternativas pesquisadas

| Abordagem | Stack | Pros | Contras | Custo |
|---|---|---|---|---|
| **A. `remark-lint-frontmatter-schema` + AJV + GitHub Actions matrix** | Node.js workflow lê JSON Schema per-type, valida cada `.md` | Maduro; AJV padrão JSON Schema 2020-12; remark plugin ecosystem | Adiciona Node toolchain a um projeto PHP-first (mas CI já tem) | 0 R$ + ~30s CI |
| **B. `frontmatter-json-schema-action` (mheap)** | GitHub Action dedicada já wrap remark | Setup mais rápido (1 step YAML) | Menos flexível que A; manutenção menor (single-author repo) | 0 R$ |
| **C. Artisan command `php artisan jana:validate-memory`** | PHP custom usando `mnapoli/FrontYAML` + JSON Schema validator `opis/json-schema` | Integra com pipeline `jana:health-check` existente; PHP-first | Reinventar roda; AJV via Node é mais maduro | 0 R$ |
| **D. Pre-commit hook local (yamllint + custom)** | `.pre-commit-config.yaml` roda yamllint + script local antes commit | Falha cedo (dev side) | Não bloqueia PR; depende dev instalar pre-commit | 0 R$ |
| **E. `cassarco/markdown-tools` Laravel** | Package PHP `cassarco/markdown-tools` valida markdown com Laravel Validation rules | Sintaxe Laravel familiar; usa `Validator::make()` | Package menor manutenção; sem JSON Schema oficial | Composer dep |

### Escolha técnica + razão

**Híbrido A + C** (rejeitar B/D/E como exclusivos).

**Razão:**
1. **A (`remark-lint-frontmatter-schema` GitHub Actions)** é gate BLOQUEADOR PR — não passa = merge bloqueado. AJV é padrão indústria JSON Schema 2020-12 ([remark-lint-frontmatter-schema](https://github.com/JulianCataldo/remark-lint-frontmatter-schema)).
2. **C (`artisan jana:validate-memory`)** é gate LOCAL pré-push + integra `jana:health-check` (cron daily 06:00 BRT) — captura drift criado via DDL/manual fora do PR. PHP-first respeita stack.
3. **Schemas declarados em `memory/schemas/*.schema.json`:**
   - `adr.schema.json` (já existe implicitamente em workflow atual — formalizar)
   - `spec.schema.json` — campos `module`, `version`, `status`, `last_updated`, `us_count`
   - `runbook.schema.json` — campos `title`, `module`, `tela`, `last_updated`, `status`
   - `session.schema.json` — `title`, `date`, `duration`, `authors`, `outcomes[]`
   - `handoff.schema.json` — `title`, `decided_at`, `decided_by`, `cycle?`, etc
   - `charter.schema.json` — `title`, `page`, `status` (draft|live|deprecated), `mission`, `goals[]`, `non_goals[]`, `ux_targets[]`, `anti_hooks[]`
4. **Rejeita B isolado:** `frontmatter-json-schema-action` é wrapper magro; integração custom matrix é mais auditável
5. **Rejeita D isolado:** pre-commit não enforce em devs externos (Eliana usa Cursor sem pre-commit?)
6. **Rejeita E:** `cassarco/markdown-tools` é PHP-only mas não usa JSON Schema oficial — perde portabilidade

### Áreas isoladas (paths exatos pra agent S1)

- **Create:** `memory/schemas/adr.schema.json` (extract do workflow atual + formalizar)
- **Create:** `memory/schemas/spec.schema.json`
- **Create:** `memory/schemas/runbook.schema.json`
- **Create:** `memory/schemas/session.schema.json`
- **Create:** `memory/schemas/handoff.schema.json`
- **Create:** `memory/schemas/charter.schema.json`
- **Create:** `memory/schemas/README.md` — guia de manutenção schemas + mapa file_glob→schema
- **Create:** `.github/workflows/memory-schema-lint.yml` — matrix strategy: cada schema valida seu glob (`memory/decisions/*.md` → adr, `memory/requisitos/**/SPEC.md` → spec, etc)
- **Create:** `package.json` (root, se não existir) com `remark-lint-frontmatter-schema` dev-dep + npm scripts
- **Create:** `app/Console/Commands/Jana/ValidateMemorySchemas.php` (artisan) — usa `mnapoli/FrontYAML` + `opis/json-schema`
- **Edit:** `app/Console/Kernel.php` — `$schedule->command('jana:validate-memory')->daily()->at('06:30')` (depois jana:health-check 06:00)
- **Create:** `tests/Feature/Jana/Console/ValidateMemorySchemasTest.php` — fixtures válida/inválida

### Pré-requisitos

- ⚠️ **Wagner aprova SCHEMA per-type** — fields obrigatórios vs opcionais (sensível: bloquear merge é forte)
- ⚠️ **Wagner aprova grace period:** flag `JANA_VALIDATE_MEMORY_STRICT=false` default — emite warning sem bloquear merge nos primeiros 14d pra dar tempo de migrar docs antigos malformados
- ⚠️ **`Kernel.php` shared file conflict:** apenas agent S1 toca registro do schedule entry; agent H1 NÃO mexe

### Pest scope mínimo

```php
// tests/Feature/Jana/Console/ValidateMemorySchemasTest.php
it('flags missing required field in ADR frontmatter')
it('passes valid ADR with all required fields')
it('flags invalid lifecycle enum value (must be: accepted, historical, ...)')
it('respects --strict flag (warning vs exit 1)')
it('runs across all memory/*.md without throwing on unknown type (graceful)')
```

### RUNBOOK

NÃO necessário (CI workflow + artisan). Mas atualizar [memory/decisions/_SCHEMA.md](../../decisions/_SCHEMA.md) e [memory/sessions/_INDEX.md](../../sessions/_INDEX.md) referenciando schemas formais.

### Risco/mitigação

- **Risco:** schema strict quebra histórico (sessions antigas malformadas). **Mitigação:** flag grace period 14d + `--exclude` glob pra docs `lifecycle: historical`.
- **Risco:** PR bloqueado por field opcional faltando vira fricção. **Mitigação:** schema declara `required` minimalista (só `title`, `type`, `decided_at`); demais campos opcionais com defaults documentados.

---

## 6. A1 — Auto-summary docs longos (complemento estrutural)

### Contexto + sintoma

[AUDITORIA-KNOWLEDGE](AUDITORIA-KNOWLEDGE-ARCHITECTURE-2026-05-13.md) §A3 mede auto-summary em **40%**. SPEC.md 49KB lido inteiro = ~12k tokens × N chamadas; `decisions-fetch <slug>` retorna ADR FULL (alguns 20KB) — caro repetir. [AWS ML Blog — Summarization techniques](https://aws.amazon.com/blogs/machine-learning/techniques-for-automatic-summarization-of-documents-using-language-models/) padrão: map-reduce divide doc em chunks, summariza em paralelo, reduce final. [Zep layered summarization](https://arxiv.org/abs/2501.13956) faz comunidade label propagation — paradigma similar.

### 5 alternativas pesquisadas

| Estratégia | Pattern | Pros | Contras | Custo/doc |
|---|---|---|---|---|
| **A. Map-reduce gpt-4o-mini + cache 24h MySQL** | Chunk 2KB → map summary 200 tok → reduce final 800 tok | Paralelo (latência baixa); custo previsível; mock mode reusa H4 RAGAS pattern | Quality menor que refine em narrative coherence | ~R$ [redacted Tier 0]/doc primeiro hit; R$ [redacted Tier 0] cache hit |
| **B. Refine sequential** | Chunk 2KB → 1ª summary → próximo chunk + accumulated context → ... | Quality narrative alta | Latência proporcional (120-page doc = horas em PoC AWS); custo 2-3x map-reduce | ~R$ [redacted Tier 0]/doc |
| **C. Anthropic prompt caching long-doc** | Cache_control 1h sobre doc completo → query múltiplas vezes | 90% cost reduction cache hit; sem chunking; alta qualidade | Doc precisa caber em context (Sonnet 1M ok); cache 1h só (re-cache after) | ~R$ [redacted Tier 0]/hit cache, R$ [redacted Tier 0]/cold |
| **D. Stuffing direto context** | Manda doc inteiro no prompt | Zero infra | Caro pra cada chamada; bate token limit > 1M | ~R$ [redacted Tier 0]/call |
| **E. Pre-compute embeddings + RAG-extract** | Mongo store summary embedding → retrieval semântico | Reusa stack RAG existente | Não é "summary" — é trecho relevante | ~R$ [redacted Tier 0]/call |

### Escolha técnica + razão

**Híbrido A + C** (rejeitar B/D/E).

**Razão:**
1. **A (map-reduce gpt-4o-mini + cache 24h)** é default tools MCP retorno longo — `decisions-fetch`, `tasks-detail`, `kb-answer` ganham auto-summary se doc > 8KB
2. **C (Anthropic prompt caching)** é otimização **automática quando tool já está dentro Claude Code session** — não precisa código nosso; Anthropic SDK Claude Code aplica cache_control nativamente em system prompts longos ([Prompt Caching 2026 Guide TokenMix](https://tokenmix.ai/blog/prompt-caching-guide) — 90% cost reduction cached input)
3. **Map-reduce performance:** [LangChain benchmark](https://medium.com/@abonia/summarization-with-langchain-b3d83c030889) mostra map-reduce vence refine em latência/custo previsível (refine took hours on 120-page doc vs map-reduce stable); paralelo nativo
4. **Cache 24h MySQL** ([`mcp_doc_summaries` table]): `(doc_hash, model, summary, created_at)` — TTL 24h porque ADR canon não muda mais, SPEC muda diário
5. **gpt-4o-mini:** $0.15 in / $0.60 out per 1M tokens = R$ [redacted Tier 0] per 1K tokens — extremamente barato vs Sonnet (R$ [redacted Tier 0]/1K) ([gpt-4o-mini pricing 2026](https://langcopilot.com/gpt-4o-mini-token-calculator))
6. **Mock mode obrigatório:** `ChunkedSummarizerService::enableMock($summary)` Pest pattern proven
7. **Rejeita B:** latência sequential mata UX
8. **Rejeita D:** custo explode em loop tools MCP
9. **Rejeita E:** RAG extrai chunks; auto-summary precisa coerência narrativa

### Áreas isoladas (paths exatos pra agent A1)

- **Create:** `Modules/Jana/Ai/Services/ChunkedSummarizerService.php` — `summarize(string $text, int $targetTokens = 1500): string` com map-reduce gpt-4o-mini via `laravel/ai`
- **Create:** `Modules/Jana/Mcp/Support/DocSummarizer.php` — decorator usado pelos tools `decisions-fetch`, `tasks-detail`, `kb-answer` quando response > N chars
- **Create:** `database/migrations/YYYY_MM_DD_create_mcp_doc_summaries_table.php` — `(id, doc_hash sha256, model, summary text, tokens_input, tokens_output, cost_brl, created_at, expires_at)`. **NOTA:** tabela cross-business (não tem `business_id`) porque summaries de ADRs canon são globais — documentar decisão no comment migration ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) §exceções repo-wide)
- **Edit:** `Modules/Jana/Mcp/Tools/DecisionsFetchTool.php` — adicionar `?summarize=true` query param que dispara `DocSummarizer::summarize($response)` se response > 8KB (CONFLITO POTENCIAL: outros agents NÃO editam tools)
- **Edit:** `config/jana.php` — adicionar `'summarizer' => ['cache_ttl_hours' => 24, 'threshold_chars' => 8000, 'model' => env('JANA_SUMMARIZER_MODEL', 'gpt-4o-mini')]`
- **Create:** `tests/Feature/Jana/Ai/ChunkedSummarizerTest.php` — Pest com mock mode + cross-tenant teste de cache não-vazamento (mesmo doc_hash = mesmo summary cross-business OK porque ADR é canon global)

### Pré-requisitos

- ✅ **L1 Langfuse Onda 4** instrumentado — `ChunkedSummarizerService` chama LLM via `laravel/ai` que já tem OTel hooks; Langfuse captura cost+latência automático
- ✅ `laravel/ai` ^0.6.3 instalado ([ADR 0035](../../decisions/0035-stack-ai-canonica-wagner-2026-04-26.md))
- ⚠️ **Wagner aprova `OPENAI_API_KEY` quota mensal:** R$ [redacted Tier 0]/mês max default (config `jana.summarizer.max_monthly_cost_brl`) — circuit breaker quando excedido

### Pest scope mínimo

```php
// tests/Feature/Jana/Ai/ChunkedSummarizerTest.php
it('returns cached summary when same doc_hash + model + within TTL')
it('respects cross-business cache hit (doc é canon global)')
it('chunks doc in ~2KB pieces using markdown headers as natural break')
it('mock mode returns deterministic summary without LLM call')
it('falls back to truncated original when LLM circuit breaker open (cost cap)')
it('tracks Langfuse trace via laravel/ai integration')  // se L1 mergeado
```

### RUNBOOK

NÃO necessário (decorator backend). Atualizar [Mcp/Tools/_INDEX.md](../../../Modules/Jana/Mcp/Tools/) (se existir) documentando `?summarize=true` param suportado.

### Risco/mitigação

- **Risco:** map-reduce perde nuance em ADR estrutural (ex: §risco-mitigação fica truncado). **Mitigação:** chunking respeita markdown headers (`##`, `###`) como natural breakpoints — chunk inteiro = seção inteira; reduce preserva títulos.
- **Risco:** custo explode se ADR/SPEC mudam diariamente e cache invalida. **Mitigação:** TTL 24h só pra SPEC; ADR `lifecycle:accepted` TTL=∞ (não muda); circuit breaker em R$ [redacted Tier 0]/mês.
- **Risco:** summary contém PII vazada de ADR (CPF cliente em exemplo). **Mitigação:** rodar `PiiRedactor` pós-summary antes de cache (skill `commit-discipline` pattern).

---

## 7. Pré-flight checks (antes de spawn 5 agents)

| # | Check | Como verificar | Ação se ❌ |
|---|---|---|---|
| 1 | Onda 4 mergeada (R1+L1+C1) | `gh pr list --state merged --search "Onda 4"` retorna 3 PRs | NÃO disparar Onda 5 |
| 2 | Langfuse self-host CT 100 online | `curl -s https://langfuse.oimpresso.com/api/public/health` retorna 200 | Esperar L1 deploy |
| 3 | Branch `main` clean | `git status` clean | Stash/commit pendentes |
| 4 | Worktree `nervous-mayer-3ff0da` válida | `composer dump-autoload` OK | Recovery junction vendor |
| 5 | Wagner sign-off K1 multipliers | confirmar `accepted=1.2`, `historical=0.5`, half_life por type | Esperar |
| 6 | Wagner sign-off V1 npm dep SVAR | `@svar-widgets/react-gantt` MIT OK | Esperar |
| 7 | Wagner sign-off S1 strict mode 14d grace | `JANA_VALIDATE_MEMORY_STRICT=false` default | Esperar |
| 8 | Wagner sign-off A1 cost cap | `JANA_SUMMARIZER_MAX_COST_BRL=10` mensal | Esperar |
| 9 | Charter framework S4 ativo | tool MCP `charter-fetch` operacional + skill `charter-first` Tier A | Onda 4 C1 fechou? |
| 10 | `mcp_tasks.parent_task_id` existe | Schema audit | Migration extra V1 |

---

## 8. Custo total projetado

| Item | Esforço | Custo R$ infra | Custo R$ LLM/mês |
|---|---:|---:|---:|
| K1 Time-decay | 2.5d IA-pair | 0 | 0 |
| V1 Roadmap timeline | 4d IA-pair | 0 | 0 |
| H1 Auto-skeleton handoff | 1d IA-pair | 0 | ~R$ [redacted Tier 0] (~250 handoffs/mês × R$ [redacted Tier 0]) |
| S1 Schema rígido CI | 1.5d IA-pair | 0 | 0 |
| A1 Auto-summary docs | 2d IA-pair | 0 | ~R$ [redacted Tier 0] (cap em R$ [redacted Tier 0]) |
| **TOTAL Onda 5** | **11 dev-days IA-pair** | **R$ [redacted Tier 0]** | **~R$ [redacted Tier 0]/mês** |
| Calendário (fator 10x ADR 0106) | **~6d real** | — | — |

> Compare com Onda 6 (gap-analysis §3): 27 dev-days + R$ desconhecido (Letta tier-2 condicional implica nova infra Postgres+vector store).

---

## 9. Surpresa estratégica — Prompt caching Anthropic destrava economics A1

O gap-analysis original estimou A1 ROI marginal (~2d/0.8pp). **Pesquisa profunda revelou:** [Prompt Caching 2026 — Anthropic](https://tokenmix.ai/blog/prompt-caching-guide) garante **100% cache hit quando `cache_control` está set** (vs OpenAI 50% automatic), reduzindo custo cached input 90% e latência 85%.

**Implicação concreta:**
- Quando Claude Code (Wagner local) chama `decisions-fetch slug:0094` com `?summarize=true`, primeira chamada custa ~R$ [redacted Tier 0] (gpt-4o-mini summary). Próximas chamadas dentro de 24h = R$ [redacted Tier 0] (cache MySQL hit).
- **Quando agente Jana (BriefDiarioAgent) faz prompt longo Sonnet com 50KB de ADR canon no system prompt**, Anthropic cache_control breakpoint mantém doc em cache 1h, custo cai 90% por chamada (de R$ [redacted Tier 0] → R$ [redacted Tier 0] por brief diário).
- **Multiplicador:** o cache 1h coincide com window típica de uma sessão de trabalho Wagner — Brief 06:00 + retomar 07:00 + meeting prep 10:00 = 3 hits cached do mesmo doc canon = **economia R$ [redacted Tier 0]/dia ou ~R$ [redacted Tier 0]/mês** num cenário conservador.

**Recomendação:** agente A1 deve **EXPLICITAR `cache_control` breakpoints** ao chamar Anthropic via `laravel/ai` — não confiar em automático. Adicionar config `jana.summarizer.anthropic_cache_breakpoints: true` default e documentar pattern em RUNBOOK Sonnet/Haiku.

Esta otimização **eleva A1 de gap "marginal P2"** (gap-analysis §2.1) **pra gap P1 estrutural** com economia mensurável imediata pós-Langfuse — justifica antecipação pra Onda 5.

---

## 10. Pós-Onda 5 — onde parar de subir

Score projetado pós-Onda 5: **~98% global** (MCP 97% · Knowledge 98% · Handoff 96%).

**Métricas de saturação (gap-analysis §6):**
- Onda 5 custo médio: 11d / 3pp = **3.7 d/pp** — ROI marginal (borderline)
- Onda 6 custo: 27d / 2pp = **13.5 d/pp** — ROI ruim (NÃO entrar sem sinal qualificado)

**Recomendação pós-Onda 5:**
1. **Rodar Langfuse 14d** com gaps fechados — medir NDCG@10 delta (K1) + token economia (A1) + tempo retomada Wagner (H1)
2. **Re-auditar com dados reais** — agente `maturity-gap-expert` recalibra GAP-ANALYSIS com métricas instrumentadas (não estimativas)
3. **PARAR EM 98%** (saturação) — Onda 6 vira ADR feature-wish hibernada até sinal qualificado externo ([ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md))
4. **Letta-tier-2 condicional** ([gap-analysis §5 Cenário B]): se Langfuse RAGAS faithfulness < 0.75 em 3 medições consecutivas → ativar ADR 0144 (hibernada)

---

## 11. Restrições TIER 0 IRREVOGÁVEIS — preservadas em TODAS escolhas

✅ **`business_id` global scope** ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)) — K1/V1/H1 todos respeitam scope; A1 doc summary cross-business OK (ADRs canon globais, documentado em migration)
✅ **Zero auto-mem privada** ([ADR 0061](../../decisions/0061-conhecimento-canonico-git-mcp-zero-automem.md)) — Nenhuma das 5 escolhas grava em `~/.claude/projects/*/memory/`
✅ **Hostinger ≠ CT 100** ([ADR 0062](../../decisions/0062-separacao-runtime-hostinger-ct100.md)) — A1 LLM chamadas pelo Hostinger Laravel ok (HTTP outbound); Langfuse (Onda 4) é CT 100
✅ **ADRs append-only** ([ADR 0094](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md)) — H1 não edita ADR 0130; cria ADR nova `supersedes: []` se necessário
✅ **Custo IA tracking** ([ADR 0094 §4](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md)) — tabela 8 declara custo per-feature R$/mês; circuit breaker A1
✅ **PT-BR em tudo** — schemas, comentários, RUNBOOK, ADRs novas

---

## 12. Fontes (31 WebSearch + 4 WebFetch deep-dive)

### Gap K1 — Time-decay (5 buscas)
- [Solving Freshness in RAG (arxiv 2509.19376)](https://arxiv.org/html/2509.19376)
- [RAG Is Blind to Time — Temporal Layer (Towards Data Science 2026)](https://towardsdatascience.com/rag-is-blind-to-time-i-built-a-temporal-layer-to-fix-it-in-production/) ← WebFetch deep-dive
- [Zep paper 2501.13956 temporal KG](https://arxiv.org/abs/2501.13956)
- [LongMemEval benchmark (Emergent Mind)](https://www.emergentmind.com/topics/longmemeval)
- [Mem0 vs Zep Graphiti comparison 2026 (Atlan)](https://atlan.com/know/zep-vs-mem0/)
- [Meilisearch ranking rules docs](https://www.meilisearch.com/docs/learn/relevancy/custom_ranking_rules)
- [Ragie recency-bias API](https://docs.ragie.ai/docs/retrievals-recency-bias)

### Gap V1 — Roadmap UI (5 buscas)
- [SVAR React Gantt MIT 2.4 (Medium)](https://medium.com/@SvarWidgets/svar-gantt-2-4-a-modern-gantt-chart-library-for-react-svelte-under-the-mit-license-ae62f36a5dde)
- [Top 5 React Gantt Libraries 2026 (SVAR Blog)](https://svar.dev/blog/top-react-gantt-charts/) ← WebFetch deep-dive
- [Best JS Gantt Libraries 2026 (DHTMLX)](https://dhtmlx.com/blog/top-8-javascript-gantt-chart-libraries-2026/)
- [GitHub Projects hierarchy GA mar/2026](https://github.blog/changelog/2026-03-19-hierarchy-view-in-github-projects-is-now-generally-available/)
- [Linear roadmap timeline changelog 2021](https://linear.app/changelog/2021-05-27-linear-preview-roadmap-timeline)
- [Bryntum Gantt 2026](https://bryntum.com/blog/top-5-javascript-gantt-chart-libraries/)

### Gap H1 — Auto-skeleton handoff (5 buscas)
- [Autosys git diff AI agent reasoning (UC Berkeley 2026)](https://www.ischool.berkeley.edu/projects/2026/autosys-git-diff-ai-agent-reasoning)
- [Session Handoff skill (softaworks agent-toolkit)](https://github.com/softaworks/agent-toolkit/blob/main/skills/session-handoff/README.md)
- [CrewAI + Mem0 production memory](https://mem0.ai/blog/crewai-memory-production-setup-with-mem0)
- [Claude Code PreCompact hooks (Porter Medium)](https://medium.com/@porter.nicholas/claude-code-post-compaction-hooks-for-context-renewal-7b616dcaa204)
- [gpt-4o-mini pricing 2026](https://langcopilot.com/gpt-4o-mini-token-calculator)
- [PagerDuty shift handoff](https://ownership.pagerduty.com/on-call/)
- [OpenCommit AI commit messages](https://github.com/di-sukharev/opencommit)

### Gap S1 — Schema validation (5 buscas)
- [remark-lint-frontmatter-schema (JulianCataldo)](https://github.com/JulianCataldo/remark-lint-frontmatter-schema)
- [frontmatter-json-schema-action (mheap)](https://github.com/mheap/frontmatter-json-schema-action)
- [adr-architecture-kit (egallmann)](https://github.com/egallmann/adr-architecture-kit)
- [Letta typed memory production schema validation](https://docs.letta.com/letta-code/memory/)
- [Laravel cassarco/markdown-tools](https://packagist.org/packages/cassarco/markdown-tools)
- [yamllint pre-commit GitHub Actions](https://yamllint.readthedocs.io/en/stable/integration.html)

### Gap A1 — Auto-summary docs (5 buscas)
- [AWS ML — Techniques summarization documents LLM](https://aws.amazon.com/blogs/machine-learning/techniques-for-automatic-summarization-of-documents-using-language-models/)
- [Zep temporal KG arxiv 2501.13956](https://arxiv.org/html/2501.13956v1)
- [LangChain map-reduce vs refine (Medium Abonia)](https://medium.com/@abonia/summarization-with-langchain-b3d83c030889)
- [Laravel SmartCache caching](https://iazaran.github.io/smart-cache/)
- [Prompt Caching Guide 2026 (TokenMix)](https://tokenmix.ai/blog/prompt-caching-guide)
- [Anthropic prompt caching cost 90% reduction (ngrok)](https://ngrok.com/blog/prompt-caching)
- [Long Document Summarization arxiv 2410.05903](https://arxiv.org/html/2410.05903)

### Complementares — cruzamento (6 buscas)
- [Agent Charter governance framework 2026 (IA Magazine)](https://www.iamagazine.com/2026/05/12/agent-charter-creating-an-ai-governance-framework-to-ensure-operational-reliance/) ← WebFetch deep-dive
- [Langfuse self-hosting docs](https://langfuse.com/self-hosting) ← WebFetch deep-dive
- [Langfuse v3 + ClickHouse acquisition 2026 (devops.gheware)](https://devops.gheware.com/blog/posts/langfuse-tracing-evaluation-tutorial-2026.html)
- [Laravel AI SDK 13.x docs](https://laravel.com/docs/13.x/ai-sdk)
- [GitHub Actions monorepo 2026 (DEV)](https://dev.to/pockit_tools/github-actions-in-2026-the-complete-guide-to-monorepo-cicd-and-self-hosted-runners-1jop)
- [Singapore IMDA Model AI Governance Framework Agentic AI](https://www.imda.gov.sg/-/media/imda/files/about/emerging-tech-and-research/artificial-intelligence/mgf-for-agentic-ai.pdf)

---

**Última atualização:** 2026-05-13 — audit-senior-expert (Opus 4.7) · sessão `nervous-mayer-3ff0da`
