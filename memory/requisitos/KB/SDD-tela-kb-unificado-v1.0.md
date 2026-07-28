---
id: requisitos-kb-sdd-tela-kb-unificado-v1-0
slug: kb-sdd
title: "SDD — KB Unificado (família /kb · /kb/v2+/sops · /kb/graph)"
type: sdd
module: KB
status: ativo
owner: wagner
version: 1.0.0
last_updated: 2026-07-28
related_docs:
  - SPEC.md
  - BRIEFING.md
  - SCHEMA-DB-V1.md
  - CAPTERRA-FICHA.md
  - SUPERFICIE.md
  - UI-CATALOG.md
  - kb-gap.md
related_adrs:
  - 0150-kb-unificado-grafo-conhecimento-modulo-ia-central
  - 0053-mcp-server-governanca-como-produto
  - 0093-multi-tenant-isolation-tier-0
  - 0061-conhecimento-canonico-git-mcp-zero-automem
  - 0035-stack-ai-canonica-wagner-2026-04-26
  - 0101-tests-business-id-1-nunca-cliente
  - 0104-processo-mwart-canonico-unico-caminho
  - 0264-governanca-executavel-trio-dominio-e2e
  - 0351-sdd-from-source
related_us:
  - US-KB-001
  - US-KB-002
  - US-KB-003
  - US-KB-004
  - US-KB-005
  - US-KB-006
  - US-KB-007
---

# SDD — KB Unificado (família de telas do módulo KB)

> **Como este documento nasceu.** Derivado pelo agent [`sdd-from-source`](../../../.claude/agents/sdd-from-source.md)
> ([ADR 0351](../../decisions/0351-sdd-from-source.md)) em 2026-07-28, tela-âncora `kb/Index`.
> **Nenhuma seção foi escrita do zero** — cada afirmação vem de uma das fontes de §0.1 e cita
> a âncora. O que não tinha fonte está marcado ⬜ ou como pergunta pra [W], nunca preenchido no chute.
>
> **Formato:** imita [`Produto/SDD-tela-cadastro-produto-v1.0.md`](../Produto/SDD-tela-cadastro-produto-v1.0.md)
> (não reabrir — imitar). Este SDD é do **MÓDULO/família**, nunca de uma tela: fluxo novo entra
> como `F<n>` em §5.3, caso novo entra na numeração `CU-KB-NN` de §6.

---

## 0. Base empírica

### 0.1 Fontes cruzadas (e as que NÃO existem)

| # | Fonte | Estado | O que deu |
|---|---|---|---|
| 1 | **Documentação canon** | ✅ | [`SPEC.md`](SPEC.md) US-KB-001..007 · [`BRIEFING.md`](BRIEFING.md) · [`SCHEMA-DB-V1.md`](SCHEMA-DB-V1.md) · charters `kb/Index`, `kb/Index.v2` (v5), `kb/Graph` · [ADR 0150](../../decisions/0150-kb-unificado-grafo-conhecimento-modulo-ia-central.md) (mãe) · [ADR 0053](../../decisions/0053-mcp-server-governanca-como-produto.md) (o acervo canon) |
| 2 | **React/Laravel vivo** | ✅ | `Modules/KB/Http/routes.php` (3 grupos + alias `/sops`) · `KbController` (`index`/`indexV2`/`show`/`history`/`softDelete`/`restore`) · `KbNodeController` · `KbAiController` · `KbArticleService` · `McpMemoryDocument` · `KbNode` · `Pages/kb/{Index,Index.v2,Graph}.tsx` |
| 3 | **Blade AdminLTE legada** | ❌ **NÃO EXISTE** | Varredura contada: `Modules/KB/Resources/views/` **não existe**; `find resources/views Modules/*/Resources/views -iname "*kb*" -o -iname "*memoria*"` → **0 arquivos**; `git log --all --diff-filter=A` sobre esses globs (repo **completo**, `is-shallow=false`) → **0 commits**. O módulo **nasceu Inertia**: o predecessor declarado no cabeçalho de `Pages/kb/Index.tsx` é `Pages/Copiloto/Admin/Memoria/Index.tsx` (React→React, não Blade→React). **Não há paridade Blade a defender aqui** — e isso é fato medido, não ausência de busca |
| 4 | **Delphi / Office Comercial** | ❌ **NÃO EXISTE** | `find memory -iname "*ANTI-REGRESSAO*"` → 2 arquivos, **ambos do Produto**. O KB não tem antecessor no WR Comercial (é capacidade nova, não migração). **Gap declarado, não inventado** |

> ⚠️ **Consequência honesta da ausência de 3 e 4:** o contrato de paridade deste módulo é **mais fraco**
> que o do Produto. Não existe "o legado fazia X e o React precisa manter" — existe só *"o canon
> promete X e o código faz Y"*. Todo CU aqui é ancorado em **doc canon + código**, nunca em memória
> de sistema antigo.

### 0.2 O que a triangulação expôs

1. **Existem DOIS KBs no mesmo módulo, com modelos de isolamento DIFERENTES** — e o SPEC não diz isso
   em lugar nenhum (§5.4). É a dívida central do módulo.
2. **A tela-âncora `/kb` não tem contrato executável nenhum.** O único teste que a menciona
   (`KbNodeControllerTest`, o caso *"GET /kb returns Inertia kb/Index component"*) está `->skip(...)`
   **e** asserta um payload que o Controller nunca serviu (`has('nodes')` × o real é
   `docs`/`filters`/`kpis`/`github_repo`). Ver §9 D-2.
3. **O endpoint que serve o CONTEÚDO não repete o filtro de acesso da lista** — §5.3 F2 / §9 D-1.
4. **`/kb/graph` é fachada**: rota é closure sem props e `/kb/graph/data` devolve `{nodes:[],edges:[],kpis:null}`
   hardcoded → a tela cai em `_lib/mockGraphData.ts`. O `anchor-lint` já acusava
   (*"US-KB-006 wired porém NÃO-SERVIDO — 0 hits"*), e a âncora do SPEC apontava pro Controller errado (§9 D-3).

---

## 1. Visão geral

O KB é o **cérebro consultável** do oimpresso: ADRs, sessions, charters, runbooks, briefings e artigos
operacionais em um lugar só, com busca, leitura, RAG e (prometido) grafo — [ADR 0150](../../decisions/0150-kb-unificado-grafo-conhecimento-modulo-ia-central.md).

### 1.1 Família de telas

| Tela | Rota(s) | Fonte de dado | Isolamento | Estado |
|---|---|---|---|---|
| **`kb/Index`** (V3 — âncora deste run) | `GET /kb` (`kb.index`) | `mcp_memory_documents` | `scope_required` + `admin_only` + permissão coarse | 🟢 viva, servindo dado real |
| **`kb/Index.v2`** (tri-pane) | `GET /kb/v2` (`kb.v2`) · `GET /sops` (`sops.index`) | `kb_nodes` | `business_id` global scope ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)) | 🟡 viva com dado real desde 2026-07-17; leitor sem corpo completo |
| **`kb/Graph`** | `GET /kb/graph` (`kb.graph.page`) | **nenhuma** (closure sem props) | n/a | 🔴 **mock** — cai em `_lib/mockGraphData.ts` |

> As três coexistem **por decisão registrada** (`Modules/KB/Http/routes.php`, bloco 3: *"Coexistência:
> /kb (V3 docs canon) · /kb/v2 (gate visual) · /sops (alias)"*). Qual delas vira "a" tela do KB é
> **decisão pendente de [W]** (charter `Index.v2` §7 D2) — este SDD documenta as três, não elege.

---

## 2. Público-alvo e personas

> 🖐 **curado: foto que envelhece** — sai do [BRIEFING](BRIEFING.md) (reescrito 2026-07-17 contra o
> banco de produção), não de leitura de código.

### P1 · Wagner / governança (biz=1) — persona REAL, dono do acervo
Lê ADR/session/reference/spec, cruza dependências, faz soft-delete LGPD de doc sensível. É quem
`/kb` serve hoje. Densidade 1440px. O BRIEFING mediu: o acervo é **99,8% documento de governança**.

### P2 · Larissa / balcão (biz=4, vestuário) — persona ASPIRACIONAL
SOP, trilha e troubleshooter no balcão 1280px (US-KB-002/004/005/007). ⚠️ O BRIEFING corrige a v1
do SPEC: *"a 'operadora de gráfica' era ficção do corpus mock"* — biz=4 tem **poucos** `article`.
**Não escrever CU que assuma volume operacional dessa persona sem medir o acervo dela.**

### P3 · Time MCP (Felipe/Maiara/Luiz/Eliana)
Consomem o mesmo acervo pelas tools MCP (`decisions-search`, `kb-answer`) — mesmo modelo de
permissão (`scope_required` via `McpMemoryDocument::scopeAcessiveisPara`), superfície diferente.

---

## 3. Governança aplicável

### 3.1 Tier 0 — IRREVOGÁVEL

| Invariante | Vale pra | Âncora |
|---|---|---|
| `business_id` global scope | **`kb_*`** (`KbNode`, `KbPath`, `KbEdge`, …) via `BelongsToBusinessTrait` | [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) |
| **Repo-wide by design** (`business_id` nullable = plataforma) | **`mcp_memory_documents`** | [ADR 0053](../../decisions/0053-mcp-server-governanca-como-produto.md) + docblock de `McpMemoryDocument` (*"REPO-WIDE: ADR 0053 docs canon do git são da plataforma, não per-business"*) |
| Canon append-only: nó bridge (`is_editable=false`) **nunca** tem `body_blocks` local | `kb_nodes` | [ADR 0061](../../decisions/0061-conhecimento-canonico-git-mcp-zero-automem.md) + `KbNodeObserver` |
| Ollama/embedder **só** no CT 100 | RAG | [ADR 0062](../../decisions/0062-separacao-runtime-hostinger-ct100.md) |
| Testes biz=1 / biz=99 — **NUNCA biz=4** | toda a suíte | [ADR 0101](../../decisions/0101-tests-business-id-1-nunca-cliente.md) + guard `LogicException` em `kbActAsUser`/`kbCreateBusinessRow` |

> ⚠️ **A distinção da 1ª × 2ª linha é a peça que faltava no canon.** O charter `kb/Index` lista
> *"`business_id` global scope na query do Controller"* como restrição Tier 0 da tela — mas a tela lê
> `mcp_memory_documents`, que é **repo-wide por decisão** (ADR 0053). Obedecer o charter ao pé da letra
> quebraria o desenho. O isolamento REAL dessa tela é **`scope_required` + `admin_only`**. Ver §9 D-4 —
> divergência **aberta**, não corrigida por mim (mexe em restrição declarada = [W]).

### 3.2 Processo de mudança
Tela = MWART 5 fases ([ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)) + gate visual [W] ([ADR 0114](../../decisions/0114-prototipo-ui-cowork-loop-formalizado.md)).
Contrato = trio charter/casos/teste ([ADR 0264](../../decisions/0264-governanca-executavel-trio-dominio-e2e.md)).
Lane: `.github/workflows/kb-pest.yml` — **catraca por prova-verde**: um arquivo só entra na allowlist
depois de comprovadamente verde contra MySQL real. **Agente propõe; não inclui.**

---

## 4. Design system aplicável

- **`kb/Index` (V3):** `AppShellV2` + `PageHeader` + `KpiGrid`/`KpiCard` + shadcn (`Card`, `Select`,
  `AlertDialog`, `ScrollArea`) + `ReactMarkdown`+`remark-gfm` no preview. `SafeSelectItem` nos filtros
  data-driven (defesa do crash Radix `value=""` — [proibicoes §5 2026-06-29](../../proibicoes.md)).
- **`kb/Index.v2`:** tri-pane Cockpit V2, tokens OKLCH. **Dívida medida:** 68 ocorrências de cor crua
  absorvidas no baseline do `ui:lint` (dono do contrato = `UI Lint ratchet vs baseline (LEI)`, required —
  ver `Index.v2.casos.md` UC-KBV2-09; **não** duplicar régua aqui).
- **`kb/Graph`:** Reactflow 11 (decisão registrada no cabeçalho de `Pages/kb/Graph.tsx`) — o SPEC
  US-KB-006 ainda diz "Cytoscape". Divergência de doc, ver §9 D-3.

---

## 5. Arquitetura

> ⚙️ **derivado** — re-rodável do caminho real Controller→Service→Model. Cite **símbolo**, não linha:
> os `arquivo:linha` apodrecem no primeiro refactor.

### 5.1 Visão em camadas

```
┌───────────────────────────────────────────────────────────────────────┐
│ FRONTEND — Inertia/React (resources/js/Pages/kb/)                     │
│  Index.tsx (V3 · docs canon) · Index.v2.tsx (tri-pane · kb_nodes)     │
│  Graph.tsx (mock) · _components/{NodeReader,NodeList,CategorySidebar, │
│    HealthPanel,PathsDialog,TroubleshooterDialog,KbCommandPalette,…}   │
│  _lib/{useKbFavorites,useKbRecent,useKbPathProgress,mockGraphData}    │
├───────────────────────────────────────────────────────────────────────┤
│ HTTP — Modules/KB/Http/routes.php (3 grupos)                          │
│  stack: web · SetSessionData · auth · language · timezone ·           │
│         AdminSidebarMenu · CheckUserLogin                             │
│  /kb          → KbController@index         (docs canon)               │
│  /kb/v2·/sops → KbController@indexV2       (kb_nodes)                 │
│  /kb/graph    → closure Inertia::render    (SEM props → mock)         │
│  /kb/{slug}/{show,history} · DELETE /kb/{slug} · POST …/restore       │
│  /kb/nodes/* · /kb/paths/* · /kb/decision-trees/* · /kb/edges/*       │
│  /kb/ai/{ask,summarize/{slug},suggest-meta}   (throttle 10/min)       │
├───────────────────────────────────────────────────────────────────────┤
│ CONTROLLERS — Modules/KB/Http/Controllers/                            │
│  KbController (índice V3 + V2 + show/history/softDelete/restore)      │
│    └ gate ÚNICO no construtor: can:jana.mcp.memory.manage (coarse)    │
│  KbNodeController · KbVersionController · KbPathController ·          │
│  KbDecisionTreeController · KbEdgeController · KbFavoriteController · │
│  KbCommentController · KbAiController · DataController · Fontes ·     │
│  Admin/GraphController  ⚠️ roteado pelo **ADS** (/ads/admin/graph),   │
│                            NÃO pelo /kb/graph — ver §9 D-3            │
├───────────────────────────────────────────────────────────────────────┤
│ SERVIÇOS — Modules/KB/Services/                                       │
│  KbArticleService::buildListQuery · KbRagService::ask (cache 5min)    │
│  KbBgeRerankerService · KbCorpusBuilder · KbBridgeStateService ·      │
│  KbEdgeAutoDeriver · KbAutoClassifierService                          │
├───────────────────────────────────────────────────────────────────────┤
│ DADOS — MySQL                                                          │
│  mcp_memory_documents  ← REPO-WIDE (ADR 0053; business_id nullable)   │
│    + mcp_memory_document_history (revisões)                            │
│  kb_nodes/kb_edges/kb_paths/kb_decision_trees/kb_categories/…          │
│    ← multi-tenant business_id global scope (ADR 0093)                 │
├───────────────────────────────────────────────────────────────────────┤
│ JOBS/CMDs — KbBridgeFromMcpJob (mcp→kb_nodes) · kb:drift-detector ·   │
│             kb:code-scan (AST, ADR 0350) · KbEdgeAutoDeriverJob       │
└───────────────────────────────────────────────────────────────────────┘
```

### 5.2 Modelo de dados (núcleo)

- **`mcp_memory_documents`** — cache governado do git (`memory/**`), sincronizado por webhook GitHub.
  Colunas que a tela `/kb` usa: `slug`, `type`, `module`, `title`, `content_md`, `scope_required`,
  `admin_only`, `git_sha`, `git_path`, `pii_redactions_count`, `indexed_at`, `deleted_at` (SoftDeletes).
  **`business_id` é nullable e NULL = plataforma** (ADR 0053).
  **`scope_required`** vem do frontmatter **ou** da heurística `IndexarMemoryGitParaDb::inferirScopeRequired`
  (slug com `credenciais`/`secret` → `copiloto.mcp.admin`; `session` com `audit` → `copiloto.mcp.audit.read`).
  ⚠️ *Recibo do tamanho da população: 6 arquivos casam "credenciais|secret" e 28 sessions casam "audit" —
  **medido no DISCO (`ls memory/**`) em 2026-07-28, NÃO em `mcp_memory_documents`**. O dono do número é a
  query no banco; isto é só prova de que a população **não é vazia**.* (Lei "fato derivado não se restateia" —
  [proibicoes §5 2026-07-17](../../proibicoes.md).)
- **`kb_nodes`** — grafo multi-tenant: `type` (`adr|session|charter|article|reference|…`), `is_editable`,
  `body_blocks` (JSON), `source_doc_id` → `mcp_memory_documents.id`, `category_id`/`subcategory_id`,
  `status`, `pinned`, `last_verified_at`, `code_drift_state` (JSON — veredito do `kb:drift-detector`).
- **`kb_edges`** — `supersedes` · `cross-link` · `references-data` · `fix-of-decision`, UNIQUE
  (biz, from, to, edge_type).
- **`kb_paths`/`kb_path_steps`** · **`kb_decision_trees`/`kb_decision_tree_steps`** (invariante do
  Observer: exatamente um de `next_step` OU `fix` por ramo) · `kb_favorites` · `kb_comments` ·
  `kb_node_versions` (append-only por save).

### 5.3 Fluxos críticos

> Cada `F<n>` é o caminho REAL rota→controller→service→model. Fluxo novo de tela irmã entra AQUI,
> nunca num §5 paralelo.

**F1 · Listar o acervo canon (tela-âncora `/kb`)** — `Index.tsx` → `GET /kb` →
`KbController@index` → `buildDocsPayload($user, type, module, q, with_pii, page)`:
`McpMemoryDocument::acessiveisPara($user)` → `doTipo`/`doModulo`/`buscarTexto` (FULLTEXT MySQL
`MATCH…AGAINST`) → `where pii_redactions_count > 0` (se `with_pii`) → `select` + `selectRaw
CHAR_LENGTH(content_md) as size_chars` → `withTrashed()` → `paginate(25)->withQueryString()`.
Em paralelo `buildKpisPayload()` roda 5 agregações (`count`, `onlyTrashed`, PII, `groupBy type`,
`groupBy module limit 15`, `max(indexed_at)`).
⚠️ **`buildKpisPayload` NÃO chama `acessiveisPara`** — os KPIs e os selects de filtro contam/nomeiam
documentos que o usuário não pode abrir. Assimetria com o F1 acima; ver §9 D-1(b).
⚠️ **Sem `Inertia::defer`**, por decisão registrada no próprio código (*"ROLLBACK Wave L/W7 PR #963:
Inertia::defer quebrava Pages (initial render undefined)"*) — contradiz a regra "defer default em
props caras" ([RUNBOOK-inertia-defer-pattern](../_DesignSystem/RUNBOOK-inertia-defer-pattern.md))
e o próprio SPEC §7. Dívida honesta, não descuido; ver §9 D-5.

**F2 · Abrir o documento no preview (o conteúdo)** — clique/`j`/`k` na lista → `openDoc(slug)` →
`fetch('/kb/{slug}/show')` → `KbController@show` →
`McpMemoryDocument::withTrashed()->where('slug',$slug)->firstOrFail()` → JSON com **`content_md`
inteiro**.
🔴 **Este é o único caminho que serve conteúdo, e ele NÃO repete o `acessiveisPara` do F1.**
Varredura contada (`git grep -n acessiveisPara -- '*.php'`, sem `head_limit`): **13 linhas em 8
arquivos**; dentro de `KbController.php` há **1 único site**, e ele está em `buildDocsPayload`
(a lista), não em `show`/`history`/`softDelete`/`restore`. Consequência de contrato: doc com
`admin_only=true` ou `scope_required` que o usuário não tem **some da lista mas é servido por slug**.
Ver §9 D-1(a) e `CU-KB-02`.

**F3 · Soft-delete LGPD** — botão → `AlertDialog` exige digitar `CONFIRMO` (client) →
`DELETE /kb/{slug}` com `{confirm}` → `KbController@softDelete` valida `required|in:CONFIRMO`
(**server**, não confia no client) → `$doc->delete()` (SoftDeletes) → JSON `{ok,message}`.
Nota de reversibilidade que a UI declara: *"próximo sync do GitHub vai re-criar se o arquivo ainda
estiver no repo"*.

**F4 · Restaurar** — `POST /kb/{slug}/restore` → `onlyTrashed()->where('slug')->firstOrFail()` →
`restore()`.

**F5 · Histórico de revisões** — `GET /kb/{slug}/history` → `withTrashed()` + `history()` ordenado
por `changed_at`, `limit(50)`. **A UI mostra a contagem mas o botão está `disabled` (`title="Em breve (O11)"`)** —
o endpoint existe e a tela não o consome. Ver `CU-KB-06`.

**F6 · Tri-pane sobre `kb_nodes` (`/kb/v2` + `/sops`)** — `KbController@indexV2` →
`KbArticleService::buildListQuery($request)` (já scopado pelo global scope) →
`orderByDesc('pinned')->orderByDesc('updated_at')->paginate(2000)` + `KbCategory` (`orderBy sort_order`)
+ `KbSubcategory` + `business.name` + mapa `can[]`. Contrato já coberto por
[`Index.v2.casos.md`](../../../resources/js/Pages/kb/Index.v2.casos.md) (UC-KBV2-01..13) e
`KbIndexV2ContractTest`. **Cap explícito 2000** — acima disso a lista fica curta e o `paginator.total`
mostra o número real (visível, não silencioso).

**F7 · Grafo (`/kb/graph`)** — rota é **closure** `Inertia::render('kb/Graph')` **sem props**;
`/kb/graph/data` devolve `{nodes:[],edges:[],kpis:null}` hardcoded → `Graph.tsx` cai em
`_lib/mockGraphData.ts` com badge "modo mock". **Nenhum Controller do KB serve esta tela.**
Ver §9 D-3.

**F8 · Pergunta RAG** — `POST /kb/ai/ask` (throttle 10/min/user) → `KbAiController@ask` →
`KbRagService::ask(question, businessId)` → `Modules/Jana/Ai` (laravel/ai + Meilisearch hybrid) →
`RagResult{answer, citations[], latencyMs, costUsd}`; cache `kb:rag:{biz}:{md5(question)}` TTL 300s;
auditoria append-only em `mcp_audit_log`.

**F9 · Bridge canon → grafo** — `KbBridgeFromMcpJob::handle(businessId, sinceTimestamp?)` lê
`mcp_memory_documents` e materializa `kb_nodes` (`is_editable=false`, `source_doc_id`), idempotente
por UNIQUE(business_id, source_doc_id); `KbEdgeAutoDeriver` deriva `supersedes`/`cross-link` do
frontmatter/corpo; `kb_bridge_state` guarda `last_bridge_at`.
⚠️ **O bridge copia metadata, não `body_blocks`** — é por isso que o leitor da V2 mostra título+excerpt
e não o corpo (limite declarado no docblock de `indexV2`).

### 5.4 Onde os dois mundos não se conversam (dívida central)

| | `/kb` (V3) | `/kb/v2` + `/sops` |
|---|---|---|
| Tabela | `mcp_memory_documents` | `kb_nodes` |
| Isolamento | `scope_required` + `admin_only` (repo-wide, ADR 0053) | `business_id` global scope (ADR 0093) |
| Conteúdo | `content_md` completo | título + excerpt (bridge não traz corpo) |
| Busca | FULLTEXT MySQL server-side | filtro client-side sobre payload de até 2000 nós |
| Categorias | `type`/`module` (strings do sync) | `kb_categories`/`kb_subcategories` (relacional) |
| Contrato executável | **nenhum** (antes deste run) | `Index.v2.casos.md` + `KbIndexV2ContractTest` |

O F9 (bridge) é a única ponte, e ela é **de mão única e parcial** (metadata sim, corpo não). Qualquer
decisão de "promover a V2" (charter `Index.v2` §7 D2) precisa resolver o corpo — senão a V2 substitui
uma tela que **lê** por uma que **lista**.

---

## 6. Casos de uso

> ⚙️+🖐 **misto** — o **enunciado** de cada CU é derivado de §0.1 (canon + código) e é re-rodável;
> o **estado** (✅/🟡/🔴/⬜/🧪) é curado e sai do **veredito da lane**, nunca da minha leitura.
>
> **Convenção:** `[must]`/`[should]` · `[T0]` invariante de isolamento ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) /
> [ADR 0053](../../decisions/0053-mcp-server-governanca-como-produto.md) conforme a tabela) · `[LGPD]` ·
> `[perf]`. **Não há `[V0]`** neste módulo: nenhum fluxo do KB toca preço, custo, margem, `num_uf` ou
> estoque (varredura contada em `Modules/KB/**`: 0 ocorrências de `num_uf`/`final_total`/`qty_available`).

### 6.1 Acervo canon — tela `/kb` (`CU-KB-01..08`)

#### CU-KB-01 — Listar o acervo com filtro, busca e paginação `[must]` 🧪
*Dado* um usuário com a permissão do módulo; *quando* abre `/kb`; *então* recebe a página Inertia
`kb/Index` com `docs` paginado (25/pág), `filters` ecoados e `kpis`.
1. `[must]` Filtro por `type` e `module` restringe a lista; `q` usa FULLTEXT (`buscarTexto`).
2. `[must]` `with_pii` restringe a docs com `pii_redactions_count > 0`.
3. `[must]` Documento soft-deletado **aparece** (`withTrashed`) marcado como deletado — é tela de
   governança, não de catálogo.
4. `[should]` Filtros ecoam de volta em `filters` (a UI reconstrói o estado do servidor).
5. `[perf]` 25 por página + `CHAR_LENGTH` no SQL (não trafega `content_md` na lista).
   Âncora: charter `kb/Index` §Goals 1/3 + `KbController@index`/`buildDocsPayload`.

#### CU-KB-02 — Só se vê o que se pode ver `[must][T0]` 🔴 **assimetria medida**
*Dado* um documento com `admin_only=true` **ou** `scope_required` que o usuário não possui;
*quando* ele tenta alcançá-lo; *então* nem a lista nem o **conteúdo** o entregam.
1. `[must]` A **lista** filtra — ✅ hoje (`acessiveisPara` no `buildDocsPayload`).
2. `[must][T0]` O **conteúdo** (`/kb/{slug}/show`) também filtra — 🔴 **hoje NÃO**: o `show()` não
   chama `acessiveisPara` (F2, varredura contada 13 linhas/8 arquivos, 1 site no `KbController`).
3. `[must]` `history`, `softDelete` e `restore` seguem a mesma regra do `show` — 🔴 mesmo defeito.
4. `[should]` Superadmin vê tudo (`hasRole('superadmin')` no scope) — comportamento intencional.
   Âncora: docblock de `McpMemoryDocument::scopeAcessiveisPara` (*"filtra por scope_required vs Spatie
   permissions do user"*) + [ADR 0053](../../decisions/0053-mcp-server-governanca-como-produto.md).
   Contrato executável: `UC-KB-02` (failing-first). **Decisão do fix é de [W]** (403 × 404 × filtrar) —
   ver §9 D-1(a).

#### CU-KB-03 — Ler o documento no preview `[must]` 🧪
1. `[must]` `GET /kb/{slug}/show` devolve `content_md` + metadata (`type`, `module`, `scope_required`,
   `admin_only`, `git_sha`, `git_path`, `pii_redactions_count`, `history_count`, `github_url`).
2. `[must]` `github_url` só existe quando há `git_path` (senão `null` — sem link quebrado).
3. `[should]` Markdown renderiza GFM (tabela/checklist) e link externo abre em nova aba com
   `rel="noopener noreferrer"`.
4. `[should]` Estado do painel persiste em `localStorage['oimpresso-kb-panel']`.
   Âncora: charter §Goals 2 + `KbController@show` + `Index.tsx::openDoc`.

#### CU-KB-04 — Soft-delete LGPD com dupla confirmação `[must][LGPD]` 🧪
1. `[must]` O servidor exige `confirm=CONFIRMO` (`required|in:CONFIRMO`) — **não** confia no client.
2. `[must]` É *soft*: o registro continua no banco (`deleted_at`), recuperável.
3. `[must]` Sem o campo (ou com valor errado) → 422 e **nada** é deletado.
4. `[should]` A UI declara a reversibilidade (re-sync do GitHub re-cria).
   Âncora: SPEC §8 LGPD + `KbController@softDelete` + `Index.tsx::doSoftDelete`.

#### CU-KB-05 — Restaurar documento deletado `[must][LGPD]` 🧪
1. `[must]` `POST /kb/{slug}/restore` volta o doc pra lista (`deleted_at` nulo).
2. `[must]` Slug não-deletado → 404 (`onlyTrashed`), não 200 silencioso.
   Âncora: SPEC §8 + `KbController@restore`.

#### CU-KB-06 — Histórico de revisões `[should]` 🟡 **backend pronto, UI desligada**
1. `[should]` `GET /kb/{slug}/history` devolve até 50 revisões ordenadas por `changed_at` + o `current`.
2. 🔴 A UI mostra `{n} versões` mas o botão está `disabled` (`title="Em breve (O11)"`) — endpoint vivo,
   consumidor desligado.
   Âncora: `KbController@history` + `Index.tsx` (bloco `history_count`).

#### CU-KB-07 — KPIs do acervo `[should]` ⚠️ **contrato em disputa**
1. `[should]` A tela mostra total, soft-deletados, docs com PII, tipos e último sync.
2. ⚠️ **Os KPIs não passam por `acessiveisPara`** enquanto a lista passa (F1). Duas leituras possíveis
   e nenhuma tem fonte canon: *(a)* KPI é métrica do acervo da plataforma (repo-wide, ADR 0053) e a
   assimetria é intencional; *(b)* KPI deve espelhar o que o usuário vê, senão a tela promete N docs e
   entrega M. **Não escolho o vencedor** — vira `[BACKLOG]` no `casos.md` e pergunta pra [W] (§9 D-1(b)).
3. `[must]` **O número nunca é escrito à mão em doc.** O dono é a query (`buildKpisPayload`); qualquer
   citação carrega recibo (query + resultado + data + sistema medido) — [proibicoes §5 2026-07-17](../../proibicoes.md).

#### CU-KB-08 — Abrir a tela é leitura pura `[must]` 🧪
1. `[must]` `GET /kb` não escreve em `mcp_memory_documents` (contagem idêntica antes/depois).
2. `[must]` `GET /kb` não enfileira Job e não chama IA (`Queue::fake()` + `assertNothingPushed`).
3. `[must]` Anônimo nunca recebe 200 nem 500 — a stack `auth` barra (302/401/403).
   Âncora: paridade com `UC-KBV2-03/04/01` da tela irmã (mesmo Controller, mesmo construtor) +
   charter §Non-Goals (a tela não edita).

### 6.2 Grafo (`CU-KB-09`)

#### CU-KB-09 — Visualizar o grafo do conhecimento `[should]` 🔴 **fachada**
1. `[should]` `/kb/graph` deveria renderizar nós+arestas reais (US-KB-006).
2. 🔴 Hoje: closure sem props + `/kb/graph/data` hardcoded vazio → `mockGraphData.ts`. O `anchor-lint`
   já acusa *"wired porém NÃO-SERVIDO — 0 hits na janela do ledger"*.
3. 🔴 A âncora do SPEC apontava `Admin/GraphController` — que é roteado pelo **ADS**
   (`Modules/ADS/Routes/web.php` → `ads.admin.graph.index`) e monta um grafo de
   Memory↔Skills↔Policy↔Tools, **não** o grafo do KB. Corrigido neste run (§9 D-3).
4. ⬜ Lib: código diz **Reactflow 11**; SPEC US-KB-006 diz **Cytoscape**. Divergência de doc.

### 6.3 Grafo multi-tenant e tri-pane (`CU-KB-10`)

#### CU-KB-10 — Tri-pane sobre `kb_nodes` com isolamento por business `[must][T0]` 🧪
Coberto **integralmente** por [`Index.v2.casos.md`](../../../resources/js/Pages/kb/Index.v2.casos.md)
(UC-KBV2-01..05, 07, 08, 09, 13) + `KbIndexV2ContractTest` (V1..V7). **Não reescrever aqui** — este CU
é o ponteiro; o contrato mora lá (1 tema = 1 dono).

### 6.4 Non-goals explícitos (por design, não regressão)

- ❌ A tela `/kb` **não edita** documento canon — o canon é append-only via git ([ADR 0061](../../decisions/0061-conhecimento-canonico-git-mcp-zero-automem.md));
  edição só existe em `kb_nodes` com `is_editable=true` (US-KB-002).
- ❌ `/kb` **não** é chat livre — RAG focado com citações; chat é `Pages/Copiloto/Chat.tsx` (charter §Non-Goals).
- ❌ `/kb` **não** filtra por `business_id` — repo-wide by design (§3.1). Não é bug; é ADR 0053.
- ❌ Imprimir SOP (US-KB-007) **não existe** — `PrintSopController` está comentado em `routes.php` e o
  `KBPrintSOP` é `TODO` no `Index.v2.tsx` (varredura contada: 2 ocorrências, ambas comentário).

---

## 7. Requisitos não-funcionais

| NFR | Alvo | Estado medido |
|---|---|---|
| FCP `/kb` | <800ms (charter) | ⬜ não medido — **sem `Inertia::defer`** (rollback PR #963), 6 queries síncronas no render |
| Switch de nó | <100ms | ⬜ não medido — `fetch` por doc, sem cache client dos últimos 20 (charter promete, código não faz) |
| Payload V2 | cap 2000 nós | ✅ explícito no `indexV2`; acima disso a lista fica curta e o total mostra o real |
| RAG | cache 5min por (biz, pergunta) | ✅ `KbRagService` |
| Observabilidade | OTel span em `ask`/`rerank`/`bridge`/`paginate` | ✅ SPEC §7 |
| A11y | WCAG 2.1 AA | ⬜ sem scorecard a11y pra `kb/Index` |
| Multi-tenant | biz=1 × biz=99 em todo Service que toca DB | 🟡 `kb_*` sim; `mcp_memory_documents` **não se aplica** (§3.1) |

---

## 8. Estratégia de qualidade e rollout

### 8.1 Testes
- **Lane:** `.github/workflows/kb-pest.yml` (`PHP / Pest (KB · MySQL)`), always-run + skip-as-pass,
  **allowlist explícita de 13 arquivos** provados verdes contra MySQL real.
  **Força do veredito:** consultado o dono único ([`governance/required-checks-baseline.json`](../../../governance/required-checks-baseline.json)),
  `PHP / Pest (KB · MySQL)` **não consta** → **advisory**: reprova visível, **não bloqueia merge**.
- **Sqlite lane** (`.github/ci-sqlite-pest.list`): 3 arquivos do KB (`KbDriftPersistence`, `KbCodeScan`, `KbCodeGraph`).
- **Fora de qualquer lane** (portanto "verde impossível"): `Http/*ControllerTest`, `GovernanceInvariantsTest`,
  `KbHealthSnapshotTest`, `KbMigrationsRegisteredTest`, `Wave26/28` parciais — bloqueio de **isolamento de
  teste** (MySQL persistente-no-run), documentado no próprio YAML.

### 8.2 Rollout
MWART 5 fases + gate visual [W]; cutover V2×V3 é decisão [W] pendente (charter `Index.v2` §7 D2).

---

## 9. Riscos e dívidas conhecidas

| # | Dívida | Evidência | Dono |
|---|---|---|---|
| **D-1(a)** | `show`/`history`/`softDelete`/`restore` não repetem `acessiveisPara` da lista | varredura contada: 13 linhas/8 arquivos; 1 site no `KbController`, em `buildDocsPayload` | **[W]** decide o fix (403 × 404 × filtrar); contrato em `UC-KB-02` |
| **D-1(b)** | KPIs/selects não passam por `acessiveisPara` | `buildKpisPayload` (5 agregações cruas) | **[W]** — `[BACKLOG]` no casos.md, CU-KB-07 |
| **D-2** | A tela-âncora não tinha contrato executável | `KbNodeControllerTest` caso *"GET /kb …"* está `->skip()` **e** asserta `has('nodes')` (payload inexistente); o arquivo não está na allowlist da lane | fechado por este run (`Index.casos.md` + `KbIndexContratoTest`) — **falta [W] aprovar a entrada na allowlist** |
| **D-3** | `/kb/graph` é mock; âncora do SPEC apontava Controller de outro módulo | closure sem props + `/kb/graph/data` vazio; `Admin/GraphController` roteado só por `Modules/ADS/Routes/web.php` | âncora corrigida neste run; **construir o Controller é decisão de produto [W]** |
| **D-4** | Charter `kb/Index` declara restrição Tier 0 (`business_id` global scope) que a tabela desta tela **não tem por design** | charter §Restrições × docblock `McpMemoryDocument` (*"REPO-WIDE: ADR 0053"*) | **[W]** — é INTENÇÃO declarada, agente não reescreve ([proibicoes §Precedência](../../proibicoes.md)) |
| **D-5** | `index()` sem `Inertia::defer` contra a regra do RUNBOOK e o próprio SPEC §7 | comentário *"ROLLBACK Wave L/W7 PR #963"* no Controller | técnica — reabrir com medição, não no escuro |
| **D-6** | Bridge não traz `body_blocks` → leitor da V2 sem corpo | docblock de `indexV2` | técnica, gated na decisão V2×V3 |
| **D-7** | `kb_nodes.category_id` majoritariamente NULL (classificador `auto_match` sem leitor PHP) | charter `Index.v2` §3 + nota final do `Index.v2.casos.md` | gated na decisão **D6** de [W] (template de categorias por vertical) |

---

## 10. Roadmap de evolução

1. **Fechar o contrato de acesso (D-1a)** — decisão [W] + fix + `UC-KB-02` verde. É o único item com
   cheiro de Tier 0 no módulo.
2. **Entrar na lane** — `KbIndexContratoTest` na allowlist do `kb-pest.yml` depois de provado verde
   (catraca), e aí `Index.casos.md` sai de 🧪.
3. **Ligar o leitor da V2 ao corpo (D-6)** — pré-requisito real de qualquer cutover V2×V3.
4. **Decidir V2 × V3 (charter §7 D2)** — [W].
5. **Grafo real (D-3)** — `KbGraphController` servindo `kb_nodes`/`kb_edges` com `Inertia::defer` e
   `business_id` scope; até lá o badge "modo mock" fica, e é honesto.
6. **Classificador `auto_match` (D-7)** — gated na decisão D6.
7. **US-KB-007 (imprimir SOP)** — não começou; âncora `_pendente_` no SPEC.

---

## 11. Referências

- [ADR 0150](../../decisions/0150-kb-unificado-grafo-conhecimento-modulo-ia-central.md) — KB unificado (mãe)
- [ADR 0053](../../decisions/0053-mcp-server-governanca-como-produto.md) — MCP server / o acervo canon repo-wide
- [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) · [ADR 0061](../../decisions/0061-conhecimento-canonico-git-mcp-zero-automem.md) · [ADR 0101](../../decisions/0101-tests-business-id-1-nunca-cliente.md)
- [ADR 0264](../../decisions/0264-governanca-executavel-trio-dominio-e2e.md) — trio/casos-gate · [ADR 0351](../../decisions/0351-sdd-from-source.md) — este processo
- [`SPEC.md`](SPEC.md) · [`BRIEFING.md`](BRIEFING.md) · [`SCHEMA-DB-V1.md`](SCHEMA-DB-V1.md) · [`SUPERFICIE.md`](SUPERFICIE.md)
- Contratos de tela: [`Index.casos.md`](../../../resources/js/Pages/kb/Index.casos.md) · [`Index.v2.casos.md`](../../../resources/js/Pages/kb/Index.v2.casos.md)

---

## Changelog

| Versão | Data | Mudança |
|---|---|---|
| 1.0.0 | 2026-07-28 | Nascimento. Derivado pelo `sdd-from-source` (ADR 0351) com tela-âncora `kb/Index`. Fontes 3 (Blade) e 4 (Delphi) **medidas como inexistentes**, não assumidas. Expõe: os dois modelos de isolamento (§5.4), a assimetria `acessiveisPara` lista×conteúdo (§5.3 F2 / D-1), a fachada do `/kb/graph` (D-3) e a ausência total de contrato executável da tela-âncora (D-2). |
