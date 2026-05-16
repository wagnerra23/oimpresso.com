# Feature Discovery — KB Unificado vs estado atual do oimpresso

> Agent G — `como-integrar` introspectivo. Mapeamento das 12 features da Onda 1-5 do KB Unificado vs código já presente em `D:\oimpresso.com\`. Tudo verificado por Glob/Grep + Read direto, sem web.

**Worktree analisado:** `D:\oimpresso.com\.claude\worktrees\practical-engelbart-8d8eb0\` (com mudanças dos Agents A-F já presentes) + `D:\oimpresso.com\` (main).

---

## TL;DR (5 linhas)

Dos 12 features investigados: **6 são reuse direto** (RAG, command palette, MCP-bridge, FSM stages, history, ReactMarkdown), **4 são extend de pattern existente** (graph viz, favoritos, versionamento UI, comments), **1 do zero mas com nicho parcial** (block editor — schema só) e **1 ausente mesmo** (print SOP). Agentes A-F **já criaram** 60% do código novo no worktree atual (12 migrations + 13 Entities + 8 Controllers + 2 Jobs + 4 Services + Pages/kb com BlockRenderer e Graph). **Risco maior:** duplicar RAG/embedder de Jana — Modules/Jana/ é MAIS maduro do que qualquer coisa nova; KB **deve compor**, não duplicar.

---

## Tabela mestra

| # | Feature | Status | Paths existentes | Reuse | Notas |
|---|---|---|---|---|---|
| 1 | KG visualização-grafo docs | 🟡 PARCIAL → 🟢 já implementado no worktree | `Modules/KB/Http/Controllers/Admin/GraphController.php` (legado ADS), `resources/js/Pages/ads/Admin/Graph.tsx` (legado ADS), `resources/js/Pages/kb/Graph.tsx` (NOVO, Agent E), `resources/js/Pages/kb/_components/GraphCanvas.tsx` + `GraphFilters.tsx` + `GraphLegend.tsx` + `GraphNodeDetail.tsx`, `_lib/graphLayout.ts` + `graphTypes.ts` + `mockGraphData.ts` | **REUSE pattern Graph.tsx ADS**. Reactflow 11.11.4 já no `package.json:73`. Layout determinístico simples já implementado (concentric/force-radial em `_lib/graphLayout.ts`). Não há cytoscape — reactflow é a escolha canônica. | Agent E já decidiu lib (comentário in-code com 5 alternativas avaliadas). `@dagrejs/dagre` TODO. Sem cytoscape no projeto. Performance esperada 700 nodes / 3000 edges biz=1 Wagner. |
| 2 | RAG sobre corpus de docs | ✅ JÁ EXISTE COMPLETO em Jana | `Modules/Jana/Ai/Agents/KbAnswerAgent.php`, `Modules/Jana/Mcp/Tools/KbAnswerTool.php`, `Modules/Jana/Services/Memoria/MeilisearchDriver.php`, `HydeQueryExpander.php`, `LlmReranker.php`, `RrfReranker.php`, `BgeReranker.php`, `NegativeCacheService.php`, `Cache/SemanticCacheService.php`, `Contextual/ContextualizerService.php` + `DocumentChunker.php`, `Telemetry/RetrievalTelemetryDecorator.php`, `Services/Ragas/RagasJudgeService.php`. Agent F **já criou** `Modules/KB/Services/KbCorpusBuilder.php` + `Services/Dtos/RagResult.php` + `SummaryResult.php` | **REUSE puro** — pipeline canônico é Camada A `laravel/ai` + MeilisearchDriver hybrid embedder (ADR 0035). KbCorpusBuilder em KB **deve apenas montar índice `kb_corpus` específico** + delegar RAG core pra Jana. NÃO criar provider novo. | Custo previsto ~R$ [redacted Tier 0]/call (gpt-4o-mini). KbAnswerAgent já formato canônico "Resposta:/Citações:/Confiança:". Embedder text-embedding-3-small via Meilisearch admin. RagasJudgeService já roda avaliação. |
| 3 | Decision tree / troubleshooter | 🟠 SIMILAR (FSM Pipeline para vendas/repair, não pra docs) → 🟢 KB tem o próprio | FSM canônico em `app/Domain/Fsm/` (31 arquivos, ADR 0143 LIVE prod biz=1 desde 2026-05-12): `ExecuteStageActionService.php`, `Models/SaleStageAction.php`, `Models/SaleProcessStage.php`, `Concerns/GuardsFsmTransitions.php`, `Observers/TransactionFsmObserver.php`. KB tem **schema próprio** (NOVO): `Modules/KB/Database/Migrations/2026_05_15_100007_create_kb_decision_trees_table.php`, `100008_create_kb_decision_tree_steps_table.php`, `Entities/KbDecisionTree.php` + `KbDecisionTreeStep.php`, `Http/Controllers/KbDecisionTreeController.php` | **IGNORE FSM (escopo diferente)** + **REUSE KB próprio** que Agent C já criou. FSM é multi-row transitions com side-effects (estoque, NFe); decision-tree KB é diagnóstico Q→Sim/Não simples sobre artigos. Não fundir. | Prototipo do troubleshooter editor em `prototipo-ui/prototipos/kb/kb-trouble-editor.jsx` (no main, fora deste worktree). |
| 4 | Trilhas / Learning paths | 🔴 NÃO EXISTE no projeto → 🟢 KB tem o próprio | MWART canônico (ADR 0104) é processo F1→F5 humano, não trilha de usuário. KB tem **schema próprio** (NOVO Agent C): `Modules/KB/Database/Migrations/2026_05_15_100005_create_kb_paths_table.php` + `100006_create_kb_path_steps_table.php`, `Entities/KbPath.php` + `KbPathStep.php`, `Http/Controllers/KbPathController.php`, `_lib/useKbPathProgress.ts` (Agent ?) | **CRIA DO ZERO** (Agent C já fez backend). Progresso por user via localStorage no front (`useKbPathProgress.ts`). Cloud-sync V2. | Pattern referência: nenhum em UltimatePOS. KB inaugura. |
| 5 | Block editor (para/h2/list/callout/image) | 🟡 PARCIAL — schema sim, renderer sim, EDITOR não | `Modules/KB/Database/Migrations/2026_05_15_100003_create_kb_nodes_table.php` (coluna `body_blocks JSON`). `resources/js/Pages/kb/_components/BlockRenderer.tsx` (Agent ?, port do `kb-page.jsx::ArticleReader`). `package.json` tem `react-markdown@^remark-gfm@^4.0.1`, `@assistant-ui/react-markdown@^0.10.6`. **NÃO há tiptap/slate/lexical/blocknote** no projeto. `components.json` é shadcn config. | **EXTEND BlockRenderer + criar Composer.tsx do zero** (Onda 3). Não trazer Tiptap — overhead 200KB+. Fazer composer minimal próprio com 5 kinds (para/h2/list/callout/image). | Anti-padrão F3 catalogado em `prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md`. ReactMarkdown bridge canon já em `Pages/kb/Index.tsx:19`. |
| 6 | Versionamento + diff visual | 🟡 PARCIAL — DB sim, UI diff visual não | DB `mcp_memory_documents_history` (Jana, ADR 0053): `Modules/Jana/Entities/Mcp/McpMemoryDocumentHistory.php`, migration `2026_04_29_100009_create_mcp_memory_documents_history_table.php`. KB tem **própria tabela** (NOVO): `kb_node_versions` + `Entities/KbNodeVersion.php` + `Http/Controllers/KbVersionController.php` (Agent ?). UI lista versões em `KbController::history()` retorna JSON (sem diff). **Nenhuma lib diff** (`react-diff-viewer`, `diff-match-patch`, `monaco-diff`) no package.json. | **REUSE pattern history-table** (KbVersionController já implementa list + restore). **CRIA diff visual UI do zero** — sugestão: lib `react-diff-viewer-continued` (~30KB gzipped, sem peer dependency conflitante) ou implementação caseira com `diff` (jsdiff). | Restore é append-only (cria nova versão com snapshot, NUNCA apaga existente) — ADR 0061 reforço explícito no controller. |
| 7 | Comments inline ancorados em block | 🟢 JÁ IMPLEMENTADO NO WORKTREE | `Modules/KB/Database/Migrations/2026_05_15_100011_create_kb_comments_table.php`, `Modules/KB/Entities/KbComment.php`, `Http/Controllers/KbCommentController.php` (POST + DELETE), `Http/routes.php` (POST `/kb/nodes/{slug}/comments`). `mcp_task_comments` em Jana é texto livre **sem block_idx** — diferente. | **REUSE backend (já completo)**. **CRIA UI** — passar `renderAfterBlock` prop em `BlockRenderer.tsx` (Agent ? já previu o slot: comentário linha 25 `/** opcional: por bloco, conteúdo extra (ex: comentários inline em ONDA 3) */`). | Schema `block_idx INT` ancora comment a posição no array `body_blocks`. Validation `block_idx ≥ 0`, text ≤5000 chars. |
| 8 | Favoritos (per-user) | 🟢 JÁ IMPLEMENTADO NO WORKTREE | Backend: `Modules/KB/Database/Migrations/2026_05_15_100010_create_kb_favorites_table.php`, `Entities/KbFavorite.php`, `Http/Controllers/KbFavoriteController.php` (POST toggle), rota `kb.favorites.toggle`. Frontend: `resources/js/Pages/kb/_lib/useKbFavorites.ts` (port do Cowork `kb-images-print.jsx`, localStorage key `oimpresso.kb.favs.v1`), `_components/KbFavStar.tsx`. **Não há `task_watchers` análogo nem padrão `Modules/Copiloto/Memoria` reusable**. | **REUSE puro**. Hook é localStorage por enquanto — comentário inline diz "trocar pra POST /kb/nodes/{slug}/favorite quando user tiver permission `kb.favorite` + cloud sync (V2)". | UX cloud sync 2-step migration prevista. |
| 9 | Command palette ⌘K | ✅ JÁ EXISTE COMPLETO (shared) | `resources/js/Components/CommandPalette.tsx` (wrapper cmdk via shadcn `command.tsx`). `package.json:60: "cmdk": "^1.1.1"`. Integrado globalmente em `resources/js/Layouts/AppShellV2.tsx:78,506`. Endpoint backend: `Modules/ProjectMgmt/Http/Controllers/SearchController.php` (busca debounced em /project-mgmt/search). | **EXTEND** — adicionar grupo "KB" no `CommandPalette.tsx` chamando endpoint novo `/kb/search?q=` (a criar). Pattern atual: Tasks/Epics/Cycles/Projects grupos com `CommandGroup` cmdk-nativos. Navegação keyboard nativa pronta. | KB precisa só novo endpoint backend + adicionar `CommandGroup` no `CommandPalette.tsx`. Ergonomia já resolvida. |
| 10 | Auto-tag IA / suggest meta | 🟡 PARCIAL — Dto criado, lógica não | `Modules/KB/Services/Dtos/MetaSuggestion.php` (NOVO, Agent F) — dto com title/excerpt/tags/category_slug/nivel + meta (latency/tokens/cost). `Modules/KB/Services/Dtos/SummaryResult.php` companheiro. Rotas comentadas em `routes.php:101-104` (`/kb/ai/suggest-meta` placeholder ONDA 4). **Não há service `KbRagService::suggestMeta()` ainda** — apenas Dto. | **CRIA service do zero** seguindo pattern `KbAnswerAgent` em Jana (laravel/ai single-shot prompt + parser estruturado). KbCorpusBuilder do Agent F é dep necessário (corpus version hash pra cache invalidation). | Custo previsto ~R$ [redacted Tier 0]/call. Reusar mesmo modelo gpt-4o-mini do KbAnswerAgent. |
| 11 | Print SOP / impressão layout oficial | 🔴 NÃO EXISTE no projeto Inertia | Blade legacy: `resources/views/sell/*.blade.php`, `resources/views/stock_transfer/print.blade.php`, `Modules/Accounting/Resources/views/transactions/sales/invoice.blade.php`, `Modules/NfeBrasil/Resources/views/mail/danfe-html.blade.php`. **Nenhum Inertia Print page** (sem `Pages/**/Print*.tsx`). `package.json` sem `react-pdf`/`@react-pdf/renderer`. `print:` Tailwind class não usado (só em `prototipo-ui` JSX). Rota commentada em `routes.php:111` (`/kb/print-sop/{slug}` placeholder ONDA 5). | **CRIA DO ZERO** — fazer `Pages/kb/PrintSop.tsx` com layout dedicado (sem AppShellV2), CSS `@media print` em arquivo separado, `window.print()` no `onMount`. Inspiração mockups: `prototipo-ui/prototipos/kb/kb-images-print.jsx` (não está neste worktree, ler do main). Pattern Blade legacy NÃO ajuda — é jQuery+server. | Larissa colar ao lado da Roland VS-540 — sem dependencies externas. Estimativa: 4-6h um agent. |
| 12 | MCP-bridge tabela A→tabela B idempotente | ✅ JÁ EXISTE COMPLETO + Agent A já replicou | Caso canônico (Jana): `Modules/Jana/Services/Mcp/IndexarMemoryGitParaDb.php` (git filesystem → `mcp_memory_documents`), `Console/Commands/McpSyncMemoryCommand.php` (artisan + webhook + cron 5min). Pattern: `firstOrNew(slug)` + `git_sha` diff detect + soft-delete sumiu + redactions PII. Idempotente. **Agent A já replicou** padrão em `Modules/KB/Jobs/KbBridgeFromMcpJob.php` + `Services/KbBridgeStateService.php` + tabela `kb_bridge_state` (last_bridge_at incremental — economiza full sweep de 352+ docs). | **REUSE pattern direto** — Agent A já fez certo. `tries=3 backoff=[60,300,900]`, `$businessId no constructor` (NUNCA `session()` em job — proibição Tier 0), `withoutGlobalScopes` documentado, retry com `last_error` persistido. | TODO: `App\Services\PiiRedactor` reuse comentado in-code linha 113. Agora redact é manual `substr(80)`. |

---

## Recomendações pros agents

### Agent A (`KbBridgeFromMcpJob` + bridge state)
- ✅ **Já consolidado**. Pattern espelha `IndexarMemoryGitParaDb` (Jana) — fonte canônica.
- ⚠️ Hook PiiRedactor pendente — usar `Modules/Jana/Services/Privacy/PiiRedactor.php` quando refatorar (existe).
- ⚠️ Schedule pendente em `app/Console/Kernel.php` — adicionar `KbBridgeFromMcpJob::dispatch($biz)` every 15min foreach business ativo.

### Agent B (Frontend + tri-pane)
- ✅ Reuse `CommandPalette.tsx` global — só adicionar `CommandGroup` "KB" + endpoint `/kb/search?q=` (backend novo).
- ✅ Reuse `react-markdown@^9` + `remark-gfm@^4.0.1` (Index.tsx legado já usa). Não trazer outras libs MD.
- ⚠️ Charter `Pages/kb/Index.charter.md` precisa atualizar Goals/UX targets — se editar `.tsx` SEM atualizar charter quebra ADR 0104 F3.
- ⚠️ MWART F2 BACKEND BASELINE com Pest fixture de `store()` — Agent A já criou `Tests/Unit/Kb*Test.php` mas falta Feature test do controller.

### Agent C (Decision-trees + paths)
- ✅ **Não fundir com FSM canônico** (`app/Domain/Fsm/`) — escopos diferentes. KB tem schema próprio.
- ✅ Pattern UI: copiar trilha do prototipo `prototipo-ui/prototipos/kb/kb-paths.jsx` + decision-tree `kb-trouble-editor.jsx` (ambos no main repo, NÃO neste worktree).
- ⚠️ `useKbPathProgress.ts` é localStorage — checar key `oimpresso.kb.path-progress.v1` pra não colidir com favs.

### Agent D (Block editor + composer + auto-tag IA)
- ✅ BlockRenderer.tsx pronto (port `kb-page.jsx`). Compor Composer com mesma `types.ts` (`KbBlock`).
- ✅ **NÃO trazer Tiptap/Slate/Lexical** — bundle penalty alto. Fazer composer minimal próprio (5 kinds).
- ✅ Auto-tag IA: REUSE pattern `KbAnswerAgent` em Jana (Camada A `laravel/ai` + gpt-4o-mini single-shot). Dto `MetaSuggestion.php` (Agent F) já modelado.
- ⚠️ Versão diff visual: usar `react-diff-viewer-continued` (peer deps clean, ~30KB gzipped). NÃO usar Monaco — overhead 600KB+.

### Agent E (Graph + Print SOP)
- ✅ **Reactflow já decidido** (commit no `Graph.tsx:8-37`). NÃO mudar.
- ✅ Layout `_lib/graphLayout.ts` concentric/force-radial já pronto. Dagre é TODO opcional.
- ⚠️ **Print SOP é blank slate** — sem precedente Inertia no projeto. Layout dedicado, sem AppShellV2, `@media print` próprio.
- ⚠️ Bridge ERP nodes (os/customer/nfe — ONDA 6) — não escopo do Agent E.

### Agent F (RAG over corpus + KbCorpusBuilder)
- ✅ KbCorpusBuilder já tem cabeçalho rico — **NÃO duplicar** MeilisearchDriver/embedder. Só MONTAR índice `kb_corpus` separado.
- ✅ Reuse `KbAnswerAgent` (Jana) com **outro** corpus retrieval (kb_nodes via `KbCorpusBuilder` em vez de `mcp_memory_documents`).
- ✅ `RagResult.php` Dto já criado.
- ⚠️ Custo: cache redis `kb_corpus_version_hash` (max kb_nodes.updated_at, mcp_memory_documents.updated_at) — Agent F já comentou pattern in-code.

### Para todos
- ⛔ **Multi-tenant Tier 0** ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)) — `business_id` global scope obrigatório em TODA model `kb_*`. `BelongsToBusinessTrait` em `Modules/KB/Entities/Concerns/` já criado.
- ⛔ **Job assíncrono passa `$businessId` no constructor** — `KbBridgeFromMcpJob` (Agent A) fez certo. Não usar `session()` em job.
- ⛔ **Bridge canon body_blocks IS NULL invariant** — `is_editable=false ⇒ body_blocks IS NULL` enforced via `KbNodeObserver` (precisa criar — Agent ? deve registrar).
- ⛔ **Roles Spatie suffix `#{biz}`** — tabela `roles.business_id` NOT NULL. Se Agent ? criar role `kb-author`, usar `Role::firstOrCreate(['name' => "kb-author#{$bizId}", 'business_id' => $bizId, 'guard_name' => 'web'])`.
- ⛔ **MWART F3** ([ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)) — Edit em `Pages/kb/*.tsx` SEM `memory/requisitos/KB/RUNBOOK-<tela>.md` é BLOQUEADO pelo hook `block-mwart-violation.ps1`. Já existe `Index.charter.md` no Pages/kb/ — falta RUNBOOK pra cada tela nova.

---

## Pegadinhas críticas

- ⚠️ **KbNodeObserver não foi achado** no Glob — invariante `is_editable=false ⇒ body_blocks IS NULL` precisa estar enforced em `saving()` event do Eloquent. Sem isso, agent ou dev distraído pode escrever `body_blocks` em bridge e violar ADR 0061. **Bloqueia ONDA 3.**
- ⚠️ **Junction NTFS Windows** — múltiplos worktrees `agent-*` apontam pra mesmas paths Modules/KB. Se algum agent rodar `git worktree remove --force` com junction vendor/ ainda presente → vendor do main some (PEGADINHA-junction-vendor-worktree-windows.md). Limpar worktrees inativos antes de merge.
- ⚠️ **Duplicação corpus Meilisearch** — KbCorpusBuilder cria índice `kb_corpus` separado. Conferir que **não duplica** com índice já existente do `MeilisearchDriver` (Jana). Decisão Agent F: índice **separado** (clean), MAS Wagner pode preferir índice único compartilhado pra economizar storage Meilisearch — confirmar antes de F4 cutover.
- ⚠️ **Schema kb_node_versions sem snapshot completo** — `KbVersionController::restoreVersion` lê `$version->snapshot` como array. Confere que migration `100009_create_kb_node_versions_table.php` declara `snapshot JSON NOT NULL` com colunas esperadas (`title`, `excerpt`, `body_blocks`, `tags`, `status`, `category_id`, `subcategory_id`, `nivel`, `equip`). Não conferido neste sweep.
- ⚠️ **Permissions rename pendente** — Controllers usam `can:copiloto.mcp.memory.manage` (dívida técnica). `Resources/permissions.php` já declara `kb.view`, `kb.softdelete`, `kb.restore`, `kb.history.view`. Rename pra `kb.manage` em PR separado (dívida técnica registrada no header do `KbController.php:30`). Não fazer agora ou quebra rotas legacy `/kb` em produção.
- ⚠️ **Pages/kb/Graph.tsx ≠ Pages/ads/Admin/Graph.tsx** — naming similar mas paths diferentes. CommandPalette deve mapear `/kb/graph` e NÃO sobrescrever `/ads/admin/graph` no menu.
- ⚠️ **Bridge incremental + `business_id NULL`** — Jana `mcp_memory_documents` aceita `business_id=NULL` pra docs globais (ADRs). `KbBridgeFromMcpJob` (Agent A linha 92) trata: `->where(fn($q) => $q->where('business_id', $this->businessId)->orWhereNull('business_id'))`. **Cuidado:** se Wagner promover doc global pra biz-específico no futuro (`UPDATE business_id = 4`), bridge cria 2 kb_nodes (1 em biz=1 já existente + 1 em biz=4 novo) e perde linkage. Mitigar com migração explícita quando acontecer.

---

## Estado de prontidão por feature (resumo binário)

```
1. KG vis-grafo docs              [████████░░] 80% — falta Inertia::defer + smoke biz=1
2. RAG sobre corpus               [██████████] 100% — só plugar KbCorpusBuilder ao KbAnswerAgent
3. Decision tree                  [████████░░] 80% — schema+controller+entity, falta UI
4. Trilhas / Learning paths       [██████░░░░] 60% — schema+entity+hook, falta page
5. Block editor (renderer ok)     [████░░░░░░] 40% — renderer ok, composer MISSING
6. Versionamento + diff visual    [██████░░░░] 60% — backend+list ok, diff UI MISSING
7. Comments inline                [████████░░] 80% — backend ok, slot no renderer, UI MISSING
8. Favoritos                      [██████████] 100% — full stack done
9. Command palette ⌘K (KB grupo)  [██░░░░░░░░] 20% — só shared, falta endpoint /kb/search
10. Auto-tag IA suggest meta      [██░░░░░░░░] 20% — Dto, service MISSING
11. Print SOP                     [░░░░░░░░░░] 0%  — blank slate
12. MCP-bridge tabela A→B         [██████████] 100% — Agent A done, schedule TODO
```

Estimativa restante consolidada (Onda 2-5): ~7-10 dias agents paralelos respeitando pré-reqs ROADMAP.

---

## Arquivos canônicos relacionados

- [BRIEFING.md](../requisitos/KB/BRIEFING.md) — 6 ondas, status atual
- [SCHEMA-DB-V1.md](../requisitos/KB/SCHEMA-DB-V1.md) — contrato 11 tabelas + endpoints
- [CAPTERRA-FICHA.md](../requisitos/KB/CAPTERRA-FICHA.md) — bench v2 + v5
- [ADR 0150 proposal](../decisions/proposals/0150-kb-unificado-grafo-conhecimento-modulo-ia-central.md)
- [ADR 0035](../decisions/0035-stack-ai-canonica-wagner-2026-04-26.md) — stack IA canônica (NÃO duplicar)
- [ADR 0053](../decisions/0053-mcp-server-governanca-como-produto.md) — MCP server pattern
- [ADR 0061](../decisions/0061-conhecimento-canonico-git-mcp-zero-automem.md) — bridge canônico append-only
- [ADR 0093](../decisions/0093-multi-tenant-isolation-tier-0.md) — Tier 0 IRREVOGÁVEL
- [ADR 0104](../decisions/0104-processo-mwart-canonico-unico-caminho.md) — MWART F1-F5

---

**Sessão:** 2026-05-15 · Agent G · Worktree practical-engelbart-8d8eb0 · Output enxuto solicitado pelo parent. Pesquisa 100% introspectiva (Glob/Grep/Read), zero web.
