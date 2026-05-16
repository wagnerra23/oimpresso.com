# Session 2026-05-15 — Agent C — KB Pest tests ONDA 1

> **Persona:** Agent C (testes Pest).
> **Owner:** [CL] (Claude Code).
> **Contrato:** [SCHEMA-DB-V1.md](../requisitos/KB/SCHEMA-DB-V1.md) + [ADR 0150 proposal](../decisions/proposals/0150-kb-unificado-grafo-conhecimento-modulo-ia-central.md) + [ADR 0093](../decisions/0093-multi-tenant-isolation-tier-0.md) + [ADR 0101](../decisions/0101-tests-business-id-1-nunca-cliente.md).
> **Branch:** claude/practical-engelbart-8d8eb0 (worktree).

## Resumo

Suite Pest completa do KB Unificado ONDA 1 (módulo IA central — ADR 0150) escrita ANTES dos Models/Controllers do Agent A existirem. Os testes são contrato de aceite — Agent A finaliza quando esta suite passa verde. 15 arquivos criados (1 Pest.php + 11 factories + 7 unit + 6 feature) cobrindo invariante crítica `is_editable=false ⇒ body_blocks IS NULL` (R1), cross-tenant isolation Tier 0 (R5), append-only kb_node_versions, FK circular root_step_id em transação, e os 4 fluxos REST principais (`/kb/nodes`, `/kb/paths`, `/kb/decision-trees`, `/kb/comments`). `phpunit.xml` atualizado com 2 directories KB (Feature + Unit).

## Arquivos criados

### Tests

| Path | # tests | Função |
|---|---|---|
| `Modules/KB/Tests/Pest.php` | n/a | Helpers `kbBootstrapSchema()`, `kbTeardownSchema()`, `kbActAsUser()`, `kbCreateBusinessRow()`, `kbCreateMcpDoc()` — TestCase aplicado a tudo em `__DIR__` |
| `Modules/KB/Tests/Unit/KbNodeTest.php` | 11 | Multi-tenant scope + invariante R1 (is_editable/body_blocks) + soft delete + casts JSON + relações + unique slug |
| `Modules/KB/Tests/Unit/KbEdgeTest.php` | 5 | Multi-tenant + self-edge block + unique triple + multi edge_type + JSON payload |
| `Modules/KB/Tests/Unit/KbPathStepTest.php` | 2 | Ordering by position + unique (path_id, position) |
| `Modules/KB/Tests/Unit/KbDecisionTreeStepTest.php` | 5 | Invariante "exatamente UM de (yes_next, yes_fix)" idem no |
| `Modules/KB/Tests/Unit/KbNodeVersionTest.php` | 5 | Append-only (UPDATE/DELETE bloqueado) + bridge nodes não versionam + cast JSON + biz scope |
| `Modules/KB/Tests/Unit/KbBridgeFromMcpJobTest.php` | 7 | Cria/atualiza node por doc + is_editable=false enforce + soft-delete cascade + supersedes from frontmatter + idempotência + biz scope |
| `Modules/KB/Tests/Unit/KbEdgeAutoDeriverTest.php` | 3 | cross-link from `#kb-XXX` + related-by-tag weight=0.6 + skip <2 tag overlap |
| `Modules/KB/Tests/Feature/Http/KbNodeControllerTest.php` | 9 | GET /kb Inertia + filter type + search q + POST 403/201 + PUT version snapshot + DELETE soft + restore + reverify + GET detail JOIN mcp |
| `Modules/KB/Tests/Feature/Http/KbCommentControllerTest.php` | 4 | POST com block_idx + 403 sem perm + DELETE author + DELETE block non-author |
| `Modules/KB/Tests/Feature/Http/KbFavoriteControllerTest.php` | 3 | Toggle ON + OFF idempotent + filter `?favorites=1` user-scoped |
| `Modules/KB/Tests/Feature/Http/KbPathControllerTest.php` | 4 | List paths + 403 sem perm + POST cria + steps ordenados + GET detail |
| `Modules/KB/Tests/Feature/Http/KbDecisionTreeControllerTest.php` | 4 | POST 403 + POST cria com root_step_id em transação + GET list + GET detail |
| `Modules/KB/Tests/Feature/CrossTenantIsolationTest.php` | 7 | **R5 ALL** — read/edge/comment/favorite/job/PUT/DELETE blocked cross-tenant |
| `Modules/KB/Tests/Feature/GovernanceInvariantsTest.php` | 3 | ADR bridge body_blocks NULL + bridge sem version + reverify metadata ok |

**Total: 72 tests** (40 unit + 32 feature).

### Factories

| Path | States | Notas |
|---|---|---|
| `Modules/KB/Database/Factories/KbNodeFactory.php` | `editable()`, `bridge($mcpDocId)`, `pinned()`, `outdated()`, `deleted()` | default `is_editable=true`, body_blocks com 1 para |
| `Modules/KB/Database/Factories/KbEdgeFactory.php` | `crossLink($blockIdx?)`, `supersedes()`, `relatedByTag($weight)`, `fixOf()` | from/to_node_id sempre via state explícito |
| `Modules/KB/Database/Factories/KbCategoryFactory.php` | — | hue=240 default |
| `Modules/KB/Database/Factories/KbSubcategoryFactory.php` | — | category_id via state |
| `Modules/KB/Database/Factories/KbPathFactory.php` | `draft()` | status=published default |
| `Modules/KB/Database/Factories/KbPathStepFactory.php` | — | position=1, step_type=leitura default |
| `Modules/KB/Database/Factories/KbDecisionTreeFactory.php` | — | root_step_id NÃO setado (FK circular — populado pelo Service) |
| `Modules/KB/Database/Factories/KbDecisionTreeStepFactory.php` | — | default yes_fix + no_fix (terminal) |
| `Modules/KB/Database/Factories/KbCommentFactory.php` | — | node_id + author_user_id via state |
| `Modules/KB/Database/Factories/KbFavoriteFactory.php` | — | user_id + node_id via state |
| `Modules/KB/Database/Factories/KbNodeVersionFactory.php` | — | append-only — usar pra read tests; pra "Service gera version" use Service direto |

### Config

| Path | Mudança |
|---|---|
| `phpunit.xml` | Adicionado `<directory>./Modules/KB/Tests/Feature</directory>` + `<directory>./Modules/KB/Tests/Unit</directory>` no `<testsuite name="Feature">` (pegadinha catalogada — sem essa entrada Pest roda vazio) |

## TODOs deixados

(Marcados como `TODO[CL]` no código pra Agent A confirmar durante implementação.)

| Path:linha aprox | Descrição |
|---|---|
| `Pest.php:113` | FQCN da Exception lançada por Observer invariante R1 (LogicException? DomainException?) — só usei `\Throwable` |
| `KbNodeTest.php:97` | Tipo exato da Exception "is_editable" no invariante R1 |
| `KbNodeTest.php:181` | Nome canônico da relação `author` vs `authorUser` |
| `KbNodeTest.php:196` | Nome canônico da relação `sourceDoc` vs `source_doc` vs `document` |
| `KbNodeTest.php:151` | SoftDeletes trait assumido em KbNode (precisa confirmar com Agent A) |
| `KbEdgeTest.php:64` | Self-edge: enforce por CHECK constraint (MySQL 8+) OR Observer PHP — SQLite não replica CHECK das migrations |
| `KbPathStepTest.php:51` | Se `KbPath::steps()` já vem `orderBy('position')` ou se precisa explícito |
| `KbNodeVersionTest.php:79` | Onde enforça "bridge node NÃO versiona" — Service OR Observer? |
| `KbBridgeFromMcpJobTest.php (multiple)` | FQCN do Job — tentei `Modules\KB\Jobs\KbBridgeFromMcpJob` + 2 fallbacks via `class_exists` helper |
| `KbEdgeAutoDeriverTest.php (multiple)` | FQCN do Deriver — `Modules\KB\Services\KbEdgeAutoDeriver` esperado |
| `KbEdgeAutoDeriverTest.php:60` | Método pra derivar cross-link: `deriveForNode($id)` vs `derive(array $nodeIds)` |
| `KbNodeControllerTest.php:36` | Skip do GET /kb Inertia até Agent A confirmar shape de prop `nodes` |
| `KbNodeControllerTest.php:189` | Shape do detail JSON pra bridge node (content_md root vs aninhado em source_doc) |
| `KbDecisionTreeControllerTest.php:88` | Filtro default exclui archived OR inclui — feature soft |
| `KbDecisionTreeControllerTest.php:106` | Shape do GET detail JSON (`tree.steps` vs `data.steps`) |
| `KbPathControllerTest.php:106` | Idem — shape do GET detail JSON |
| `KbFavoriteControllerTest.php:67` | Shape do listing com `?favorites=1` |
| `CrossTenantIsolationTest.php:73` | Endpoint `kb.edges.store` opcional — skip se ausente |
| `permissions.php` | Permissions novas (kb.write, kb.publish.path, kb.publish.troubleshoot, kb.favorite, kb.comment, kb.ai.ask, kb.graph.view) precisam ser declaradas no `Modules/KB/Resources/permissions.php` E criadas como Permission Spatie pelo seeder |

## Riscos R1-R5 cobertos (ADR 0150)

| Risco ADR 0150 | Mitigação testada | Test responsável |
|---|---|---|
| **R1** Duplicação acidental kb_nodes ↔ mcp_memory_documents | invariante `is_editable=false ⇒ body_blocks IS NULL` | `KbNodeTest::rejects body_blocks when is_editable=false`<br>`KbNodeTest::allows NULL body_blocks when is_editable=false`<br>`GovernanceInvariantsTest::keeps ADR bridge nodes body_blocks always NULL`<br>`KbBridgeFromMcpJobTest::sets is_editable=false for all bridge nodes` |
| **R2** Custo IA RAG explode sem caching | (ONDA 4) — não coberto V1 | n/a (ONDA 4) |
| **R3** UX visualização-grafo confunde Wagner | (gate visual ADR 0114) — não é teste Pest | n/a (gate visual) |
| **R4** Concurrent edit (raro) | otimista `updated_at` pre-save | TODO[CL]: registrar test em ONDA 3 (block editor) |
| **R5** Multi-tenant leak via bridge | `business_id` global scope em todas tabelas | `CrossTenantIsolationTest` (7 tests covering read/edge/comment/favorite/bridge_job/PUT/DELETE)<br>`KbNodeTest::scopes by business_id`<br>`KbEdgeTest::scopes by business_id`<br>`KbNodeVersionTest::scopes by business_id`<br>`KbBridgeFromMcpJobTest::respects business scope` |

R2 e R3 ficam pra ondas posteriores. R4 deve ser endereçada na ONDA 3 (block editor) — fora do escopo Agent C ONDA 1.

## Cobertura por método HTTP

| Método | # tests | Endpoints cobertos |
|---|---|---|
| **GET** | 8 | `/kb` (Inertia, 1) · `/kb/nodes` filter type/q/favorites (3) · `/kb/nodes/{slug}` detail (1) · `/kb/paths` (1) · `/kb/paths/{slug}` (1) · `/kb/decision-trees` (1) · `/kb/decision-trees/{slug}` (1) |
| **POST** | 11 | `/kb/nodes` 403 + 201 (2) · `/kb/nodes/{slug}/reverify` (1) · `/kb/nodes/{slug}/restore` (1) · `/kb/nodes/{slug}/comments` 403/201 (3) · `/kb/nodes/{slug}/favorite` toggle (2) · `/kb/paths` 403/201 (2) · `/kb/decision-trees` 403/201 (2) |
| **PUT** | 3 | `/kb/nodes/{slug}` 200 + cross-tenant 403 (2) · `/kb/nodes/{slug}` invariante R1 block (1) |
| **DELETE** | 4 | `/kb/nodes/{slug}` 200 + cross-tenant 403 (2) · `/kb/comments/{id}` author + non-author 403 (2) |

**Permissions explicitamente exercitadas:** kb.view, kb.write, kb.softdelete, kb.restore, kb.publish.path, kb.publish.troubleshoot, kb.favorite, kb.comment. Permissions de IA (kb.ai.ask) e grafo (kb.graph.view) ficam pras ondas 4 e 5.

## Cenários NÃO cobertos (e por quê)

| Cenário | Por quê |
|---|---|
| `/kb/graph` Cytoscape JSON (ONDA 5) | Fora ONDA 1 |
| `/kb/ai/*` RAG (ONDA 4) | Fora ONDA 1 |
| `/kb/print-sop/{slug}` PDF (ONDA 5) | Fora ONDA 1 |
| `kb_path_user_progress` cloud sync | Out-of-scope V1 (SCHEMA §15 — localStorage) |
| `kb_external_files` Spatie medialibrary | Out-of-scope V1 (SCHEMA §15) |
| Embeddings Meilisearch hybrid | Out-of-scope V1 (SCHEMA §15) |
| Performance benchmarks (>5000 nodes) | Wagner com 352 docs hoje — virtualização vira teste em ONDA 5 quando UX grafo + Cytoscape entrar |
| Concurrent edit (R4) | ONDA 3 (block editor + composer) |
| Cascade delete `mcp_doc.deleted_at` → kb_nodes orfãos | Coberto parcial em `KbBridgeFromMcpJobTest::cascades mcp_doc soft-delete` — full audit fica TODO[CL] em smoke ONDA 4 |
| Bench v5 score (≥9.5/10) | Coberto pelo `comparativo`/`capterra-senior` skills, não Pest |
| ROTA LIVRE biz=4 cenários reais | **Proibido** ([ADR 0101](../decisions/0101-tests-business-id-1-nunca-cliente.md)) — testes usam biz=1 + biz=99 SEMPRE |

## Estratégia de schema nos tests

Dado que `phpunit.xml` usa `DB_CONNECTION=sqlite` + `DB_DATABASE=:memory:`, e o KB depende de FKs pra `business`, `users`, `mcp_memory_documents` (tabelas core UltimatePOS / Modules\Copiloto que NÃO podem ser instaladas standalone):

- `kbBootstrapSchema()` cria as 3 tabelas externas mínimas inline ANTES de rodar migrations KB
- 5 tabelas Spatie (`permissions`, `roles`, `model_has_*`, `role_has_permissions`) criadas inline pra suportar `givePermissionTo()`
- Migrations KB de `Modules/KB/Database/Migrations/2026_05_15_1000*.php` carregadas via `require ... ->up()` (mesmo pattern que `RepairFsmActionControllerTest.php` usa pra FSM canon)
- `kbTeardownSchema()` faz drop em ordem reversa pra respeitar FK CASCADE

**Pegadinhas conhecidas:**

- SQLite NÃO replica CHECK constraint inline em `Schema::create`, então `kb_edges` CHECK `from != to` precisa observer PHP (catalogado em TODO[CL])
- `kb_decision_trees.root_step_id` FK circular adiada via raw `ALTER TABLE` em migration — em SQLite isso pode ignorar silenciosamente (TODO[CL] confirmar)
- Usar `\DB::table()` raw quando queremos burlar BelongsToBusinessTrait global scope (criar nodes em biz=99 enquanto autenticado em biz=1)

## Comando pra rodar (quando Agent A terminar)

```bash
# Worktree: D:/oimpresso.com/.claude/worktrees/practical-engelbart-8d8eb0
vendor/bin/pest Modules/KB/Tests/

# OR só Unit:
vendor/bin/pest Modules/KB/Tests/Unit/

# OR só Feature (HTTP):
vendor/bin/pest Modules/KB/Tests/Feature/

# OR um test específico:
vendor/bin/pest Modules/KB/Tests/Unit/KbNodeTest.php

# OR rodar 1 it:
vendor/bin/pest --filter="rejects body_blocks when is_editable=false"
```

Esperado primeiro run: muitos `markTestSkipped` (Models/Services do Agent A ausentes) + alguns failed (waiting Observer, FormRequest). Conforme Agent A merge, count de passing aumenta. **Critério de saída:** 100% green (zero skip) = ONDA 1 backend pronta.

## Handoff pro Agent A (ler antes de implementar)

Pra que o suite passe verde, Agent A precisa criar (mínimo):

1. **Models** em `Modules/KB/Entities/`:
   - `KbNode` (use `BelongsToBusinessTrait` + `SoftDeletes`; casts: `body_blocks`/`tags` JSON, `is_editable` bool, `pinned` bool)
   - `KbEdge` (cast `payload` JSON, `weight` decimal:3)
   - `KbSubcategory`, `KbPath`, `KbPathStep`, `KbDecisionTree`, `KbDecisionTreeStep`, `KbNodeVersion`, `KbFavorite`, `KbComment`
   - Relações: `business()`, `author()`, `category()`, `subcategory()`, `sourceDoc()`, `versions()`, `comments()`, `steps()` (orderBy('position')), `edgesFrom()`, `edgesTo()`
2. **Observers**:
   - `KbNodeObserver::saving` — invariante R1 `is_editable=false ⇒ body_blocks=null`
   - `KbEdgeObserver::saving` — `from != to` (anti self-loop em SQLite)
   - `KbEdgeObserver::saving` — `from.business_id == to.business_id == edge.business_id` (anti cross-tenant edge)
   - `KbDecisionTreeStepObserver::saving` — invariante "exatamente UM de (yes_next, yes_fix)" idem no
   - `KbNodeVersionObserver::updating/deleting` — bloqueia (append-only)
   - `KbNodeVersionObserver::creating` — bloqueia se `KbNode::find($node_id)->is_editable === false`
3. **Controllers + routes**: `KbNodeController`, `KbPathController`, `KbDecisionTreeController`, `KbCommentController`, `KbFavoriteController` (resource controllers full CRUD)
4. **Permissions** novas registradas em `Modules/KB/Resources/permissions.php` + Permission Spatie pelo seeder
5. **Job** `Modules\KB\Jobs\KbBridgeFromMcpJob` com constructor `($businessId)` (NUNCA depender de session)
6. **Service** `Modules\KB\Services\KbEdgeAutoDeriver` com `deriveForNode($id)` + `deriveRelatedByTag($bizId)`
7. **Factories** — já criei skeleton, Agent A pode alterar campos conforme FQCN dos Models

## Tier 0 enforcement nos tests

- Helper `kbActAsUser($bizId)` lança `LogicException` se `$bizId === 4` (ROTA LIVRE) — **enforce ADR 0101 em runtime de teste**
- Todos os cross-tenant scenarios usam biz=1 vs biz=99 (canônico)
- Pest config local (`Modules/KB/Tests/Pest.php`) aplica `Tests\TestCase` em tudo dentro do `__DIR__`

## Próximos passos

1. Aguardar Agent A finalizar Models + Controllers + Observers + Job
2. Rodar `vendor/bin/pest Modules/KB/Tests/` — coletar primeiro snapshot de pass/fail/skip
3. Iterar TODO[CL] com Agent A onde signature/FQCN diferir do esperado
4. Quando 100% green → Wagner aprova ONDA 1 → spawn ONDA 2 (frontend Inertia)
5. Em ONDA 4 (RAG), criar `KbRagServiceTest` cobrindo cache + custo + sources cite
6. Em ONDA 5 (grafo), criar `KbGraphControllerTest` cobrindo viewport virtualization >5000 nodes

---

**Commit:** não commitado (Agent C não faz git ops por contrato).
**Trabalho parent:** quando todos 3 agents terminarem, parent consolida em 3 PRs separados (1 por domínio: backend Agent A, frontend Agent B, tests Agent C).
