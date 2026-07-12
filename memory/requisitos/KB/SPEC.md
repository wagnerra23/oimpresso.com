---
module: KB
status: ativo
version: "1.0"
last_updated: "2026-06-13"
owners: [W]
---

# KB — SPEC canônico (Knowledge Base unificada)

> Especificação técnica do **Módulo IA Central** `Modules/KB/`.
> Owner: [W] Wagner · Status: ONDA 0+1+2+4+5(parcial) LIVE prod (PR #934 2026-05-16) · ONDA 3+5(restante)+6 em execução.
>
> Decisão arquitetural mãe: **[ADR 0150](../../decisions/0150-kb-unificado-grafo-conhecimento-modulo-ia-central.md)** (ACEITA 2026-05-16).
> Schema técnico: **[SCHEMA-DB-V1.md](SCHEMA-DB-V1.md)**.
> Estado consolidado 1-pager: **[BRIEFING.md](BRIEFING.md)**.
> Benchmark de mercado: **[CAPTERRA-FICHA.md](CAPTERRA-FICHA.md)** (Bench v5 ~9,55-9,70/10).

## 1. Missão

Cérebro consultável da empresa sobre TODO conhecimento (ADRs, sessions, charters, runbooks, briefings, artigos operacionais de gráfica, dados ERP). Multi-tenant Tier 0, IA RAG nativo, visualização-grafo navegável.

## 2. User Stories canônicas

### US-KB-001 — Bridge canon dos 143 ADRs (ONDA 1, ✅ LIVE)

**Implementado em:** `Modules/KB/Jobs/KbBridgeFromMcpJob.php` · `Modules/KB/Services/KbBridgeStateService.php` · `Modules/KB/Services/KbEdgeAutoDeriver.php` · `Modules/KB/Entities/KbNode.php` · `Modules/KB/Entities/KbEdge.php` · `Modules/KB/Entities/KbBridgeState.php` · `Modules/KB/Observers/KbNodeObserver.php` · `Modules/KB/Tests/Feature/Http/KbNodeControllerTest.php` · `Modules/KB/Tests/Unit/KbBridgeFromMcpJobTest.php` · verificado@98cae0a (2026-06-18)
**Como** Wagner governance,
**quero** ver os 143 ADRs do projeto como nós navegáveis no grafo KB,
**para** poder consultar dependências (supersedes/related/charter-of) sem grep cego em filesystem.

Critérios:
- `KbBridgeFromMcpJob` cria `kb_nodes` (type=adr, is_editable=false, source_doc_id=mcp_memory_documents.id)
- Frontmatter `supersedes:` vira aresta `kb_edges` tipo `supersedes` (auto-derivada)
- Cross-link `#kb-NNN` em body vira aresta `cross-link`
- Idempotente: re-run não duplica nós nem arestas (UNIQUE business_id+source_doc_id)
- Multi-tenant Tier 0: cada business vê seus próprios ADRs (business_id global scope)

### US-KB-002 — Artigo editável (Larissa Cowork, ONDA 3 parcial)

**Implementado em:** `Modules/KB/Entities/KbNode.php` · `Modules/KB/Entities/KbNodeVersion.php` · `Modules/KB/Services/KbArticleService.php` · `Modules/KB/Http/Controllers/KbNodeController.php` · `Modules/KB/Observers/KbNodeObserver.php` · `resources/js/Pages/kb/Index.tsx` · `resources/js/Pages/kb/_components/BlockRenderer.tsx` · `Modules/KB/Tests/Feature/Http/KbNodeControllerTest.php` · `Modules/KB/Tests/Unit/KbArticleServiceUnitTest.php` · `Modules/KB/Tests/Feature/LgpdComplianceTest.php` · verificado@98cae0a (2026-06-18)
**Como** Larissa operadora gráfica,
**quero** criar artigo "Como trocar tinta Roland VS-540" com blocks (h1/p/img/code),
**para** consolidar SOP do balcão sem depender de Wagner editar manualmente git.

Critérios:
- `kb_nodes.type=article` + `is_editable=true` + `body_blocks=array`
- Block editor frontend (Composer.tsx) com tipos: paragraph, heading, image, code, callout
- Versionamento via `kb_node_versions` (snapshot append-only por save)
- Author tracking (`author_user_id`) + LogsActivity (LGPD Art. 37)
- Soft-delete pra reversibilidade

### US-KB-003 — Pergunta IA RAG sobre grafo (ONDA 4, ✅ LIVE)

**Implementado em:** `Modules/KB/Services/KbRagService.php` · `Modules/KB/Services/KbBgeRerankerService.php` · `Modules/KB/Services/KbCorpusBuilder.php` · `Modules/KB/Services/Dtos/RagResult.php` · `Modules/KB/Http/Controllers/KbAiController.php` · `Modules/KB/Tests/Feature/KbRagServiceMultiTenantTest.php` · `Modules/KB/Tests/Unit/KbBgeRerankerServiceTest.php` · `Modules/KB/Tests/Unit/KbBgeRerankerServiceProdTest.php` · `Modules/KB/Tests/Feature/KbRagasEvalTest.php` · verificado@98cae0a (2026-06-18)
**Como** Wagner ou Larissa,
**quero** perguntar "qual ADR rege multi-tenant aqui?" e receber resposta com citações,
**para** descobrir conhecimento sem precisar memorizar 143 slugs ADR.

Critérios:
- `KbRagService` roteia via `Modules/Jana/Ai/` (laravel/ai SDK + MeilisearchDriver hybrid embedder)
- Retorna `RagResult` DTO com summary + array de `CitationResult` (node_id + score)
- Reranker BGE (`KbBgeRerankerService`) ordena resultados por relevância semântica
- Cache 5min via `cache()->remember()` reduz custo IA
- Multi-tenant: pergunta de biz=1 nunca recupera nós de biz=4

### US-KB-004 — Trilha de aprendizado Larissa (ONDA 3+5)

**Implementado em:** `Modules/KB/Entities/KbPath.php` · `Modules/KB/Entities/KbPathStep.php` · `Modules/KB/Http/Controllers/KbPathController.php` · `resources/js/Pages/kb/_components/PathsDialog.tsx` · `Modules/KB/Tests/Feature/Http/KbPathControllerTest.php` · `Modules/KB/Database/Factories/KbPathFactory.php` · `Modules/KB/Database/Factories/KbPathStepFactory.php` · verificado@98cae0a (2026-06-18)
**Como** Larissa,
**quero** trilha "Como abrir OS no balcão" com 7 passos em ordem,
**para** treinar Mateus novo funcionário sem retrabalho de explicar oralmente.

Critérios:
- `kb_paths` + `kb_path_steps` (UNIQUE position) ordenam nós
- Persona-target (audience: larissa|wagner|mateus|eliana)
- Progresso por dispositivo via localStorage `oimpresso.kb.paths`
- Visualização tri-pane Cockpit V2 (Cowork [CC])

### US-KB-005 — Troubleshooter Q→Sim/Não→Fix (ONDA 3)

**Implementado em:** `Modules/KB/Entities/KbDecisionTree.php` · `Modules/KB/Entities/KbDecisionTreeStep.php` · `Modules/KB/Http/Controllers/KbDecisionTreeController.php` · `Modules/KB/Observers/KbDecisionTreeStepObserver.php` · `resources/js/Pages/kb/_components/TroubleshooterDialog.tsx` · `Modules/KB/Tests/Feature/Http/KbDecisionTreeControllerTest.php` · `Modules/KB/Tests/Unit/KbDecisionTreeStepTest.php` · `Modules/KB/Database/Factories/KbDecisionTreeFactory.php` · `Modules/KB/Database/Factories/KbDecisionTreeStepFactory.php` · verificado@98cae0a (2026-06-18)
**Como** Mateus operando Roland VS-540,
**quero** decision-tree "Roland não imprime?" com bifurcações até Fix,
**para** resolver problema sem ligar pra Wagner.

Critérios:
- `kb_decision_trees` + `kb_decision_tree_steps` (yes_next/yes_fix + no_next/no_fix)
- Invariante: cada step tem EXATAMENTE UM de (next_step OR fix) por branch (Observer)
- Fix pode linkar artigo kb_node via `yes_fix_node_id` → edge `fix-of-decision`

### US-KB-006 — Visualização-grafo Cytoscape (ONDA 5)

**Implementado em:** `Modules/KB/Http/Controllers/Admin/GraphController.php` · `resources/js/Pages/kb/Graph.tsx` · `resources/js/Pages/kb/_components/GraphCanvas.tsx` · `resources/js/Pages/kb/_components/GraphFilters.tsx` · `resources/js/Pages/kb/_components/GraphLegend.tsx` · `resources/js/Pages/kb/_components/GraphNodeDetail.tsx` · `Modules/KB/Entities/KbEdge.php` · verificado@98cae0a (2026-06-18)
**Como** Wagner,
**quero** ver os 143 ADRs num grafo navegável (Cytoscape.js) com edges supersedes/related,
**para** entender dependências arquiteturais visualmente.

Critérios:
- `Pages/kb/Graph.tsx` renderiza JSON do `KbGraphController` via Cytoscape
- Filtros por type (adr/session/charter), por business, por categoria
- Performance: paginação server-side se >500 nós
- Densidade Larissa 1280px (sem rounded-xl, sem padding wasted)

### US-KB-007 — Imprimir SOP balcão físico (ONDA 5)
**Como** Larissa,
**quero** imprimir artigo "Setup tinta Roland" no layout oficial Oimpresso,
**para** colar ao lado da impressora física como referência rápida.

Critérios:
- `KBPrintSOP` component CSS print media query
- Header com logo + business name + última edição + autor
- Footer com URL canon `oimpresso.com/kb/{slug}` (QR Code)

## 3. Inviolabilidades Tier 0 (sem ADR mãe nova é proibido)

- **Multi-tenant ADR 0093**: `business_id` global scope (`BusinessScope` via `BelongsToBusinessTrait`) em TODAS as tabelas `kb_*`. Pest cross-tenant biz=1 vs biz=99 obrigatório em todo Service que toca DB.
- **Canon append-only ADR 0061**: `kb_nodes` bridge canônico (`is_editable=false`) NUNCA tem `body_blocks` local — conteúdo vem do JOIN com `mcp_memory_documents.content_md`. Invariante enforced via `KbNodeObserver`.
- **Stack IA ADR 0035**: KbRagService usa `laravel/ai` SDK + `MeilisearchDriver` hybrid embedder. NUNCA criar provider/wrapper novo (Vizra rejeitada ADR 0048).
- **Runtime ADR 0058+0062**: Ollama embedder roda APENAS em CT 100 (FrankenPHP). NUNCA chamar Ollama do Hostinger.
- **MWART ADR 0104**: F3 Cowork→Inertia/React em `Pages/kb/*.tsx` segue 5 fases obrigatórias.
- **Gate visual ADR 0114**: screenshot Wagner aprova antes de F4 merge.
- **Inertia::defer**: props caras (paginate, eager-load, RAG calls) DEFAULT `Inertia::defer(...)` (RUNBOOK-inertia-defer-pattern).

## 4. Contratos técnicos (interfaces que NÃO mudam sem ADR)

### `KbRagService::ask(string $question, int $businessId, array $opts = []): RagResult`
- Throws `IsolationViolationException` se businessId não bate session
- Retorna `RagResult { string $answer, array<CitationResult> $citations, float $latencyMs, ?float $costUsd }`
- Cache key `kb:rag:{biz}:{md5(question)}` TTL 300s

### `KbBridgeFromMcpJob::handle(int $businessId, ?int $sinceTimestamp = null): void`
- Idempotente; incremental se `sinceTimestamp` passado
- Atualiza `kb_bridge_state` (last_bridge_at, docs_processed_last_run)
- Multi-tenant: cada job dispatched per-business (NUNCA cross-tenant)

### `KbEdgeAutoDeriverJob::handle(int $nodeId): void`
- Lê node + body_blocks → deriva edges (`supersedes`, `cross-link`, `references-data`)
- UNIQUE (biz, from, to, edge_type) impede duplicação

## 5. Estados / fluxos

- `kb_nodes.status`: `draft|published|archived` (boolean-like — NÃO state machine FSM, ADR 0143 N/A explícito em module.json)
- `kb_paths.status`: idem
- `kb_decision_trees.status`: idem

KB é módulo read-mostly. Sem ciclo transacional. `fsm_n_a=true` em module.json.

## 6. Testes obrigatórios (Pest)

| Test file | Cobre | Status |
|---|---|---|
| `MultiTenantTraitTest.php` | BelongsToBusinessTrait com biz=1 vs biz=99 | ✅ |
| `CrossTenantIsolationTest.php` | Cross-tenant pra cada Entity | ✅ |
| `LgpdComplianceTest.php` | PiiRedactor + LogsActivity | ✅ |
| `KbRagServiceMultiTenantTest.php` | RAG biz isolation | ✅ |
| `GovernanceInvariantsTest.php` | is_editable + body_blocks invariante | ✅ |
| `Wave26KbSmokeTest.php` | Smoke canônico (Wave 26) | ✅ NEW |
| `Wave26KbScaffoldTest.php` | Scaffold (8 peças módulo) | ✅ NEW |

## 7. Performance / Observabilidade

- OTel spans via `App\Util\OtelHelper::span()` em: `KbRagService::ask`, `KbBgeRerankerService::rerank`, `KbBridgeFromMcpJob::handle`, `KbArticleService::paginate`, `KbBridgeStateService::markRun`, `KbEdgeAutoDeriver::derive`
- `Inertia::defer` em Controllers `KbController`, `KbDecisionTreeController` (props caras como nodes paginate)
- Cache hybrid embedder Meilisearch reduz custo IA RAG

## 8. LGPD

- `KbComment.author_user_id` + corpo livre → potencial PII (3y retention, anonymize strategy)
- `KbFavorite.user_id` → preferência pessoal (5y retention)
- `kb_node_versions.author_user_id` → autor revisão (anonymize, conteúdo preservado)
- Activity log (Spatie) habilitado em `KbNode` + `KbComment` (LogsActivity trait)
- `PiiRedactor` referenciado em retention policy

Config canônica: `config/retention.KB.php` + `Modules/KB/Config/retention.php` (mirror).

## 9. Roadmap (ondas)

Ver [BRIEFING.md §"Plano em 6 ondas"](BRIEFING.md).

## 10. Referências canônicas

- [ADR 0150](../../decisions/0150-kb-unificado-grafo-conhecimento-modulo-ia-central.md) — KB unificado grafo (mãe)
- [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) — Multi-tenant Tier 0
- [ADR 0061](../../decisions/0061-conhecimento-canonico-git-mcp-zero-automem.md) — Canon append-only
- [ADR 0035](../../decisions/0035-stack-ai-canonica-wagner-2026-04-26.md) — Stack IA
- [ADR 0053](../../decisions/0053-mcp-server-governanca-como-produto.md) — MCP server
- [SCHEMA-DB-V1.md](SCHEMA-DB-V1.md) — Contrato técnico de tabelas
- [BRIEFING.md](BRIEFING.md) — Estado consolidado 1-pager
- [CAPTERRA-FICHA.md](CAPTERRA-FICHA.md) — Benchmark mercado
- [IA-MATURITY-FICHA.md](IA-MATURITY-FICHA.md) — Maturity IA

---
**Última atualização:** 2026-05-17 — Wave 26 saturação (D1.a markers + Smoke/Scaffold tests + SPEC canônico + retention mirror).
