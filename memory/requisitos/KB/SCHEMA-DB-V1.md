# KB Unificado — Schema DB V1 (contrato técnico)

> **Status:** contrato firme. Mudanças exigem ADR amendment a [0149](../../decisions/proposals/0149-kb-unificado-grafo-conhecimento-modulo-ia-central.md).
> **Audiência:** agents implement-expert + Wagner. Este doc é o que os agents paralelos consomem como CONTRATO.
> **Princípio:** **kb_nodes é o grafo. mcp_memory_documents continua read-only fotografia git.** Bridge nullable FK.

---

## 1. Resumo das 9 tabelas + 0 alterações em existentes

```
┌──────────────────────────────────────────────────────────────────────────┐
│  EXISTENTES (intactas)                                                   │
│    mcp_memory_documents          ← fotografia git read-only (352 docs)   │
│    mcp_memory_documents_history  ← revisões                              │
│    mcp_audit_log                 ← audit forense (intact)                │
│    users, business, roles, ...    ← UltimatePOS core (intact)            │
├──────────────────────────────────────────────────────────────────────────┤
│  NOVAS (Onda 1)                                                          │
│    kb_nodes                      ← nó do grafo (artigo|adr|session|...)  │
│    kb_edges                      ← aresta tipada entre 2 nós             │
│    kb_categories                 ← taxonomia (1ª camada)                 │
│    kb_subcategories              ← taxonomia (2ª camada, derivada)       │
│    kb_paths                      ← trilhas de aprendizado                │
│    kb_path_steps                 ← passos ordenados de trilha            │
│    kb_decision_trees             ← troubleshooters                       │
│    kb_decision_tree_steps        ← perguntas + branches (yes/no)         │
│    kb_node_versions              ← histórico append-only de edits        │
│    kb_favorites                  ← bookmark por user                     │
│    kb_comments                   ← comentários inline ancorados em block │
└──────────────────────────────────────────────────────────────────────────┘
```

11 tabelas novas. Zero ALTER em existentes.

## 2. Convenções globais

- **Multi-tenant:** TODAS as tabelas `kb_*` têm `business_id INT NOT NULL` + FK pra `business.id` + índice. Models Eloquent usam `BelongsToBusinessTrait` (já existe no projeto) ou global scope explícito. **Tier 0 IRREVOGÁVEL** ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)).
- **PK:** `BIGINT UNSIGNED AUTO_INCREMENT` (padrão Laravel).
- **Timestamps:** `created_at`, `updated_at` em todas. `deleted_at` (soft-delete) onde indicado.
- **Charset:** `utf8mb4_unicode_ci` (UTF-8 brasileiro full).
- **JSON:** colunas `body_blocks`, `payload`, `snapshot` são `JSON` (MySQL 8+).
- **Slug:** `VARCHAR(180)` com unique composto `(business_id, slug)` quando aplicável.
- **Tipo enum representado como VARCHAR(40)** (não MySQL ENUM — evita ALTER pra adicionar valores). Validação fica no Model.

## 3. DDL — kb_nodes (nó do grafo)

```sql
CREATE TABLE kb_nodes (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    business_id     INT NOT NULL,

    -- tipo do nó (governa renderização + edição)
    type            VARCHAR(40)  NOT NULL COMMENT 'article|adr|session|charter|runbook|briefing|spec|comparativo|reference|os|customer|product|nfe|equipment|external_file',
    slug            VARCHAR(180) NOT NULL,
    title           VARCHAR(255) NOT NULL,
    excerpt         VARCHAR(500) NULL,

    -- conteúdo editável (apenas type=article ou externos)
    body_blocks     JSON         NULL COMMENT 'array de blocks [{kind: para|h2|list|callout|image, ...}]. NULL pra bridge canon.',

    -- bridge pra fotografia git canônica (read-only)
    source_doc_id   BIGINT UNSIGNED NULL COMMENT 'FK mcp_memory_documents.id quando type in (adr|session|charter|runbook|briefing|spec)',
    source_entity_type VARCHAR(80)  NULL COMMENT 'App\\Transaction, App\\Contact, Modules\\Repair\\JobSheet, etc — quando type in (os|customer|nfe|...)',
    source_entity_id   BIGINT UNSIGNED NULL,

    -- governança / status
    is_editable     TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'true só pra type=article (operacional). bridges = false.',
    status          VARCHAR(40)  NOT NULL DEFAULT 'ok' COMMENT 'draft|ok|outdated|deleted|deprecated',
    pinned          TINYINT(1) NOT NULL DEFAULT 0,

    -- taxonomia operacional (NULL pra bridges)
    category_id     BIGINT UNSIGNED NULL,
    subcategory_id  BIGINT UNSIGNED NULL,
    nivel           VARCHAR(20)  NULL COMMENT 'iniciante|intermediario|avancado (operacional)',
    equip           VARCHAR(80)  NULL COMMENT 'Roland VS-540, HP Latex 365, etc',
    tags            JSON         NULL COMMENT 'array de strings',

    -- métricas
    reads_count     INT UNSIGNED NOT NULL DEFAULT 0,
    helpful_count   INT UNSIGNED NOT NULL DEFAULT 0,
    outdated_votes  INT UNSIGNED NOT NULL DEFAULT 0,
    os_linked_count INT UNSIGNED NOT NULL DEFAULT 0,

    -- meta
    author_user_id  BIGINT UNSIGNED NULL,
    read_time_min   SMALLINT UNSIGNED NULL,
    last_verified_at TIMESTAMP NULL COMMENT 'última re-verificação pelo dono (botão "Re-verificar")',

    created_at      TIMESTAMP NULL,
    updated_at      TIMESTAMP NULL,
    deleted_at      TIMESTAMP NULL,

    PRIMARY KEY (id),
    UNIQUE KEY uniq_business_slug (business_id, slug),
    KEY idx_business_type (business_id, type),
    KEY idx_business_status_pinned (business_id, status, pinned),
    KEY idx_source_doc (source_doc_id),
    KEY idx_source_entity (source_entity_type, source_entity_id),
    KEY idx_category (category_id),
    KEY idx_subcategory (subcategory_id),
    KEY idx_author (author_user_id),
    KEY idx_deleted (deleted_at),

    CONSTRAINT fk_kb_nodes_business FOREIGN KEY (business_id) REFERENCES business(id) ON DELETE CASCADE,
    CONSTRAINT fk_kb_nodes_source_doc FOREIGN KEY (source_doc_id) REFERENCES mcp_memory_documents(id) ON DELETE SET NULL,
    CONSTRAINT fk_kb_nodes_category FOREIGN KEY (category_id) REFERENCES kb_categories(id) ON DELETE SET NULL,
    CONSTRAINT fk_kb_nodes_subcategory FOREIGN KEY (subcategory_id) REFERENCES kb_subcategories(id) ON DELETE SET NULL,
    CONSTRAINT fk_kb_nodes_author FOREIGN KEY (author_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Invariante crítica:** `is_editable=false ⇒ body_blocks IS NULL ⇒ conteúdo vem do JOIN com mcp_memory_documents.content_md`. Enforcement via CHECK constraint OU validação Model (Laravel não suporta CHECK em todas as versões — usar Eloquent observer `saving` event).

## 4. DDL — kb_edges (aresta tipada)

```sql
CREATE TABLE kb_edges (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    business_id   INT NOT NULL,
    from_node_id  BIGINT UNSIGNED NOT NULL,
    to_node_id    BIGINT UNSIGNED NOT NULL,

    edge_type     VARCHAR(40) NOT NULL COMMENT
        'next-in-path|fix-of-decision|supersedes|charter-of|references-data|ai-related|cross-link|related-by-tag',

    weight        DECIMAL(5,3) NOT NULL DEFAULT 1.000 COMMENT 'pra ai-related/related-by-tag, score 0-1',
    payload       JSON NULL COMMENT 'metadata específico do tipo (ex: ai-related → embedding score; cross-link → block_idx)',

    -- proveniência (importante pra ai-related: foi gerada automaticamente?)
    generated_by  VARCHAR(40) NOT NULL DEFAULT 'manual' COMMENT 'manual|bridge_job|ai_embed|tag_overlap|user_action',

    created_at    TIMESTAMP NULL,
    updated_at    TIMESTAMP NULL,

    PRIMARY KEY (id),
    UNIQUE KEY uniq_edge_triple (business_id, from_node_id, to_node_id, edge_type),
    KEY idx_business_from (business_id, from_node_id),
    KEY idx_business_to (business_id, to_node_id),
    KEY idx_business_type (business_id, edge_type),

    CONSTRAINT fk_kb_edges_business FOREIGN KEY (business_id) REFERENCES business(id) ON DELETE CASCADE,
    CONSTRAINT fk_kb_edges_from FOREIGN KEY (from_node_id) REFERENCES kb_nodes(id) ON DELETE CASCADE,
    CONSTRAINT fk_kb_edges_to FOREIGN KEY (to_node_id) REFERENCES kb_nodes(id) ON DELETE CASCADE,

    -- nó não pode apontar pra si mesmo
    CHECK (from_node_id <> to_node_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Diretrizes de uso:**
- `supersedes` — auto-derivada do frontmatter `supersedes:` de ADRs durante bridge job
- `charter-of` — auto-derivada do frontmatter `charter_adr:` ou do path `*.charter.md` ao lado de `.tsx`
- `references-data` — gerada por job `KbScanReferencesJob` que regex'a `#OS-1234`, `cliente XYZ`, etc. em body_blocks de artigos editáveis
- `cross-link` — gerada quando `body_blocks` contém `#kb-XXX` ou `#aXX` (compat Cowork) — via Markdown parser
- `ai-related` — gerada pelo job `KbAiRelateJob` periódico (cosine similarity de embeddings)
- `related-by-tag` — gerada por job ou query view materializada (tag overlap + cat/equip bonus, mesmo algoritmo do `kb-extras.jsx relatedArticles`)

## 5. DDL — kb_categories + kb_subcategories

```sql
CREATE TABLE kb_categories (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    business_id INT NOT NULL,
    slug        VARCHAR(60)  NOT NULL,
    label       VARCHAR(120) NOT NULL,
    description VARCHAR(255) NULL,
    hue         SMALLINT UNSIGNED NOT NULL DEFAULT 240 COMMENT '0-360 OKLCH chroma',
    icon        VARCHAR(80)  NULL,
    sort_order  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    created_at  TIMESTAMP NULL,
    updated_at  TIMESTAMP NULL,

    PRIMARY KEY (id),
    UNIQUE KEY uniq_business_slug (business_id, slug),
    KEY idx_business_sort (business_id, sort_order),

    CONSTRAINT fk_kb_categories_business FOREIGN KEY (business_id) REFERENCES business(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE kb_subcategories (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    business_id INT NOT NULL,
    category_id BIGINT UNSIGNED NOT NULL,
    slug        VARCHAR(60)  NOT NULL,
    label       VARCHAR(120) NOT NULL,
    description VARCHAR(255) NULL,
    -- regra opcional pra derivar subcategoria automaticamente (mesmo modelo do KB_SUBCATS Cowork)
    auto_match  JSON NULL COMMENT 'ex: {field: "equip", op: "=", value: "Roland VS-540"} ou {field: "tags", op: "regex", value: "/sangria|medida/i"}',
    created_at  TIMESTAMP NULL,
    updated_at  TIMESTAMP NULL,

    PRIMARY KEY (id),
    UNIQUE KEY uniq_business_cat_slug (business_id, category_id, slug),
    KEY idx_business_category (business_id, category_id),

    CONSTRAINT fk_kb_subcategories_business FOREIGN KEY (business_id) REFERENCES business(id) ON DELETE CASCADE,
    CONSTRAINT fk_kb_subcategories_category FOREIGN KEY (category_id) REFERENCES kb_categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## 6. DDL — kb_paths + kb_path_steps (trilhas)

```sql
CREATE TABLE kb_paths (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    business_id     INT NOT NULL,
    slug            VARCHAR(120) NOT NULL,
    title           VARCHAR(180) NOT NULL,
    audience        VARCHAR(180) NULL COMMENT 'Larissa primeiro mês, Wagner onboarding governança, etc.',
    description     VARCHAR(500) NULL,
    hue             SMALLINT UNSIGNED NOT NULL DEFAULT 240,
    status          VARCHAR(40)  NOT NULL DEFAULT 'published' COMMENT 'draft|published|archived',
    author_user_id  BIGINT UNSIGNED NULL,
    created_at      TIMESTAMP NULL,
    updated_at      TIMESTAMP NULL,
    deleted_at      TIMESTAMP NULL,

    PRIMARY KEY (id),
    UNIQUE KEY uniq_business_slug (business_id, slug),
    KEY idx_business_status (business_id, status),

    CONSTRAINT fk_kb_paths_business FOREIGN KEY (business_id) REFERENCES business(id) ON DELETE CASCADE,
    CONSTRAINT fk_kb_paths_author FOREIGN KEY (author_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE kb_path_steps (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    business_id INT NOT NULL,
    path_id     BIGINT UNSIGNED NOT NULL,
    node_id     BIGINT UNSIGNED NOT NULL,
    position    SMALLINT UNSIGNED NOT NULL COMMENT '1-based',
    step_type   VARCHAR(40) NOT NULL DEFAULT 'leitura' COMMENT 'leitura|pratica|decisao',
    note        VARCHAR(500) NULL,
    created_at  TIMESTAMP NULL,
    updated_at  TIMESTAMP NULL,

    PRIMARY KEY (id),
    UNIQUE KEY uniq_path_position (path_id, position),
    KEY idx_business_path (business_id, path_id),
    KEY idx_node (node_id),

    CONSTRAINT fk_kb_path_steps_business FOREIGN KEY (business_id) REFERENCES business(id) ON DELETE CASCADE,
    CONSTRAINT fk_kb_path_steps_path FOREIGN KEY (path_id) REFERENCES kb_paths(id) ON DELETE CASCADE,
    CONSTRAINT fk_kb_path_steps_node FOREIGN KEY (node_id) REFERENCES kb_nodes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Progresso de trilha por usuário:** vive em `localStorage` no frontend (`oimpresso.kb.paths`) por padrão. Quando `kb.path.progress.cloud_sync` permission ativa, salvar em tabela auxiliar `kb_path_user_progress (user_id, path_id, step_id, completed_at)` — fora do escopo V1, registrar como TODO.

## 7. DDL — kb_decision_trees + kb_decision_tree_steps (troubleshooters)

```sql
CREATE TABLE kb_decision_trees (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    business_id     INT NOT NULL,
    slug            VARCHAR(120) NOT NULL,
    title           VARCHAR(180) NOT NULL,
    equip           VARCHAR(80)  NULL,
    when_to_use     VARCHAR(500) NULL COMMENT 'descrição do sintoma',
    hue             SMALLINT UNSIGNED NOT NULL DEFAULT 240,
    status          VARCHAR(40)  NOT NULL DEFAULT 'published',
    root_step_id    BIGINT UNSIGNED NULL COMMENT 'primeiro passo (entry point) — populado após criação',
    author_user_id  BIGINT UNSIGNED NULL,
    created_at      TIMESTAMP NULL,
    updated_at      TIMESTAMP NULL,
    deleted_at      TIMESTAMP NULL,

    PRIMARY KEY (id),
    UNIQUE KEY uniq_business_slug (business_id, slug),
    KEY idx_business_status (business_id, status),

    CONSTRAINT fk_kb_dt_business FOREIGN KEY (business_id) REFERENCES business(id) ON DELETE CASCADE,
    CONSTRAINT fk_kb_dt_author FOREIGN KEY (author_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE kb_decision_tree_steps (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    business_id     INT NOT NULL,
    tree_id         BIGINT UNSIGNED NOT NULL,
    position        SMALLINT UNSIGNED NOT NULL,
    question        VARCHAR(500) NOT NULL,

    -- branch SIM: ou aponta pra próximo step OU termina com fix
    yes_next_step_id BIGINT UNSIGNED NULL,
    yes_fix          TEXT NULL COMMENT 'pode citar #kb-NNN pra cross-link',
    yes_fix_node_id  BIGINT UNSIGNED NULL COMMENT 'edge fix-of-decision opcional',

    -- branch NÃO
    no_next_step_id  BIGINT UNSIGNED NULL,
    no_fix           TEXT NULL,
    no_fix_node_id   BIGINT UNSIGNED NULL,

    created_at      TIMESTAMP NULL,
    updated_at      TIMESTAMP NULL,

    PRIMARY KEY (id),
    UNIQUE KEY uniq_tree_position (tree_id, position),
    KEY idx_business_tree (business_id, tree_id),

    CONSTRAINT fk_kb_dts_business FOREIGN KEY (business_id) REFERENCES business(id) ON DELETE CASCADE,
    CONSTRAINT fk_kb_dts_tree FOREIGN KEY (tree_id) REFERENCES kb_decision_trees(id) ON DELETE CASCADE,
    CONSTRAINT fk_kb_dts_yes_next FOREIGN KEY (yes_next_step_id) REFERENCES kb_decision_tree_steps(id) ON DELETE SET NULL,
    CONSTRAINT fk_kb_dts_no_next  FOREIGN KEY (no_next_step_id)  REFERENCES kb_decision_tree_steps(id) ON DELETE SET NULL,
    CONSTRAINT fk_kb_dts_yes_fix_node FOREIGN KEY (yes_fix_node_id) REFERENCES kb_nodes(id) ON DELETE SET NULL,
    CONSTRAINT fk_kb_dts_no_fix_node  FOREIGN KEY (no_fix_node_id)  REFERENCES kb_nodes(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Invariante por linha:** exatamente UM de (`yes_next_step_id`, `yes_fix`) populado, idem para `no_*`. Validar via observer Eloquent.

`kb_decision_trees.root_step_id` é populado em segundo INSERT após `kb_decision_tree_steps` ser criado (FK circular — registrar em transação).

## 8. DDL — kb_node_versions (histórico append-only)

```sql
CREATE TABLE kb_node_versions (
    id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    business_id       INT NOT NULL,
    node_id           BIGINT UNSIGNED NOT NULL,
    version_at        TIMESTAMP NOT NULL,
    author_user_id    BIGINT UNSIGNED NULL,
    snapshot          JSON NOT NULL COMMENT '{title, excerpt, body_blocks, tags, status, category_id, subcategory_id, nivel, equip}',
    change_reason     VARCHAR(255) NULL,

    PRIMARY KEY (id),
    KEY idx_business_node_when (business_id, node_id, version_at),

    CONSTRAINT fk_kb_versions_business FOREIGN KEY (business_id) REFERENCES business(id) ON DELETE CASCADE,
    CONSTRAINT fk_kb_versions_node FOREIGN KEY (node_id) REFERENCES kb_nodes(id) ON DELETE CASCADE,
    CONSTRAINT fk_kb_versions_author FOREIGN KEY (author_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Trigger MySQL append-only opcional V2** — V1 confia no Model Observer que bloqueia UPDATE/DELETE em código.

`kb_node_versions` SÓ é populado pra `kb_nodes.is_editable=true`. Bridges (ADR/session/charter) já têm versionamento via `mcp_memory_documents_history`.

## 9. DDL — kb_favorites + kb_comments

```sql
CREATE TABLE kb_favorites (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    business_id INT NOT NULL,
    user_id     BIGINT UNSIGNED NOT NULL,
    node_id     BIGINT UNSIGNED NOT NULL,
    created_at  TIMESTAMP NULL,

    PRIMARY KEY (id),
    UNIQUE KEY uniq_user_node (user_id, node_id),
    KEY idx_business_user (business_id, user_id),

    CONSTRAINT fk_kb_favorites_business FOREIGN KEY (business_id) REFERENCES business(id) ON DELETE CASCADE,
    CONSTRAINT fk_kb_favorites_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_kb_favorites_node FOREIGN KEY (node_id) REFERENCES kb_nodes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE kb_comments (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    business_id     INT NOT NULL,
    node_id         BIGINT UNSIGNED NOT NULL,
    block_idx       SMALLINT UNSIGNED NOT NULL COMMENT 'index do bloco em body_blocks',
    text            TEXT NOT NULL,
    author_user_id  BIGINT UNSIGNED NOT NULL,
    created_at      TIMESTAMP NULL,
    updated_at      TIMESTAMP NULL,
    deleted_at      TIMESTAMP NULL,

    PRIMARY KEY (id),
    KEY idx_business_node_block (business_id, node_id, block_idx),
    KEY idx_author (author_user_id),
    KEY idx_deleted (deleted_at),

    CONSTRAINT fk_kb_comments_business FOREIGN KEY (business_id) REFERENCES business(id) ON DELETE CASCADE,
    CONSTRAINT fk_kb_comments_node FOREIGN KEY (node_id) REFERENCES kb_nodes(id) ON DELETE CASCADE,
    CONSTRAINT fk_kb_comments_author FOREIGN KEY (author_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## 10. Bridge job — `KbBridgeFromMcpJob`

Job assíncrono Laravel, agendado via Horizon ou cron a cada **15 minutos** (incremental, baseado em `mcp_memory_documents.updated_at > kb_nodes.updated_at`).

**Pseudocódigo:**

```php
foreach (McpMemoryDocument::where('business_id', $businessId)
    ->where('updated_at', '>', $lastBridgeAt)
    ->cursor() as $doc) {

    $node = KbNode::firstOrNew(['source_doc_id' => $doc->id]);

    $node->fill([
        'business_id'   => $doc->business_id,
        'type'          => $doc->type, // adr|session|charter|runbook|briefing|spec|reference
        'slug'          => $doc->slug,
        'title'         => $doc->title,
        'excerpt'       => Str::limit(strip_tags($doc->content_md), 400),
        'body_blocks'   => null, // canônico = NULL, conteúdo vem do JOIN
        'is_editable'   => false,
        'status'        => $doc->deleted_at ? 'deleted' : 'ok',
        'tags'          => $doc->metadata['tags'] ?? null,
    ])->save();

    // Auto-derive edges baseadas no frontmatter
    KbEdgeAutoDeriver::deriveSupersedes($node, $doc);    // frontmatter supersedes:
    KbEdgeAutoDeriver::deriveRelated($node, $doc);       // frontmatter related:
    KbEdgeAutoDeriver::deriveCharterOf($node, $doc);     // se path tem .charter.md
}

$state->update(['last_bridge_at' => now()]);
```

Estado do bridge persistido em `kb_bridge_state (business_id, last_bridge_at)`.

## 11. Endpoints REST + Inertia (contrato pra Agent 3 e Agent 4)

```
GET    /kb                                Inertia kb/Index — tri-pane
GET    /kb/graph                          Inertia kb/Graph — visualização-grafo
GET    /kb/nodes                          JSON paginado de kb_nodes (?type, ?category, ?q, ?cursor)
GET    /kb/nodes/{slug}                   JSON detalhe + body (com JOIN mcp se bridge)
POST   /kb/nodes                          cria artigo (kb.write)
PUT    /kb/nodes/{slug}                   edita artigo (autosnap → kb_node_versions) (kb.write)
DELETE /kb/nodes/{slug}                   soft-delete (kb.softdelete)
POST   /kb/nodes/{slug}/restore           restore (kb.restore)
POST   /kb/nodes/{slug}/reverify          dono confirma frescor (kb.write)
GET    /kb/nodes/{slug}/versions          lista snapshots
POST   /kb/nodes/{slug}/restore-version   restaura snapshot (kb.write)
POST   /kb/nodes/{slug}/favorite          toggle favorito do user atual
POST   /kb/nodes/{slug}/comments          adiciona comment inline (block_idx)
DELETE /kb/comments/{id}                  delete próprio comment

GET    /kb/paths                          lista trilhas
GET    /kb/paths/{slug}                   detalhe trilha + nodes ordenados
POST   /kb/paths                          cria trilha (kb.publish.path)
PUT    /kb/paths/{slug}                   edita trilha (kb.publish.path)

GET    /kb/decision-trees                 lista troubleshooters
GET    /kb/decision-trees/{slug}          detalhe + steps
POST   /kb/decision-trees                 cria troubleshooter (kb.publish.troubleshoot)
PUT    /kb/decision-trees/{slug}          edita troubleshooter (kb.publish.troubleshoot)

POST   /kb/ai/ask                         RAG sobre corpus → resposta + sources[]
POST   /kb/ai/summarize/{slug}            resume nó on-click
POST   /kb/ai/suggest-meta                auto-tag (title, excerpt, tags) a partir de body_blocks

GET    /kb/graph/data                     JSON {nodes, edges, kpis} pro Cytoscape.js
GET    /kb/print-sop/{slug}               render server-side PDF (Spatie\Browsershot)
```

Todos sob `middleware ['web', 'SetSessionData', 'auth', 'language', 'timezone', 'AdminSidebarMenu', 'CheckUserLogin']` (stack UltimatePOS canônica).

## 12. Permissions (Spatie + PermissionRegistry)

Adicionar em `Modules/KB/Resources/permissions.php`:

```php
['key' => 'kb.write',                'label' => 'KB: Criar/editar artigos',          'risk' => 'medium', 'requires' => ['kb.view']],
['key' => 'kb.publish.path',         'label' => 'KB: Criar/editar trilhas',          'risk' => 'medium', 'requires' => ['kb.view']],
['key' => 'kb.publish.troubleshoot', 'label' => 'KB: Criar/editar troubleshooters',  'risk' => 'medium', 'requires' => ['kb.view']],
['key' => 'kb.graph.view',           'label' => 'KB: Ver visualização-grafo',        'risk' => 'low',    'requires' => ['kb.view']],
['key' => 'kb.favorite',             'label' => 'KB: Favoritar (próprio)',           'risk' => 'low',    'requires' => ['kb.view']],
['key' => 'kb.comment',              'label' => 'KB: Comentar inline',               'risk' => 'low',    'requires' => ['kb.view']],
['key' => 'kb.ai.ask',               'label' => 'KB: Perguntar à IA (RAG)',           'risk' => 'medium', 'requires' => ['kb.view']],
```

**Dívida técnica preservada:** middleware `can:copiloto.mcp.memory.manage` continua canônica até PR de rename. Novas permissions são declarativas no PermissionRegistry; mapping real fica no `KbController->middleware()`.

## 13. Seeders V1 (Onda 1)

- `KbCategoriesSeeder` — 7 categorias (Produção, Equipamentos, Pré-impressão, Atendimento, Fiscal, Sistema, Pessoas) + categoria `governance` exclusiva pra ADRs/sessions
- `KbSubcategoriesSeeder` — 16 subcats com `auto_match` (port direto de KB_SUBCATS Cowork + governance.{adr,session,charter,runbook,briefing,spec})
- `KbBridgeFromMcpSeeder` — chama `KbBridgeFromMcpJob` síncrono pra biz=1 + biz=4 (ROTA LIVRE) na primeira execução
- `KbOperacionalSeeder` — 18 artigos do Cowork + 3 trilhas (KB_PATHS) + 3 troubleshooters (KB_TROUBLES) pra business_id IN (4) — ROTA LIVRE piloto

## 14. Indices de performance esperados

| Query típica | Tabela | Index esperado | Custo |
|---|---|---|---|
| Lista artigos do biz X filtrado por type=adr | kb_nodes | idx_business_type | O(log n) |
| Detalhe nó por slug | kb_nodes | uniq_business_slug | O(1) |
| Nó canônico → join conteúdo | kb_nodes + mcp_memory_documents | idx_source_doc | O(1) |
| Pinados topo | kb_nodes | idx_business_status_pinned | O(log n) |
| Edges from X | kb_edges | idx_business_from | O(log n) |
| Edges to Y (reverse) | kb_edges | idx_business_to | O(log n) |
| Edges de tipo Z | kb_edges | idx_business_type | O(log n) |
| Comments de nó | kb_comments | idx_business_node_block | O(log n) |
| Versões de nó | kb_node_versions | idx_business_node_when | O(log n) |
| Favoritos do user | kb_favorites | uniq_user_node + idx_business_user | O(1)/O(log n) |

Volume estimado biz=1 (Wagner) inicial:
- kb_nodes ~700 (143 ADR + ~500 session + ~30 charter + ~50 runbook + ~10 briefing + headroom)
- kb_edges ~3000 (4x nodes em média, contando supersedes + charter-of + cross-link + related-by-tag auto-gerado)
- kb_node_versions ~0 inicialmente, cresce ~10/dia em uso normal
- kb_path_steps ~24 (3 trilhas Cowork x ~8 passos)
- kb_decision_tree_steps ~15 (3 trees x ~5 perguntas)

Volume biz=4 (ROTA LIVRE) inicial: artigos operacionais 18 + ADRs/sessions relevantes (~50) = ~68 nodes.

## 15. Não-objetivos V1 (registrados como TODO)

- ❌ Tabela `kb_path_user_progress` (cloud sync de checkbox) — fica em localStorage V1
- ❌ Tabela `kb_external_files` separada — V1 usa Spatie medialibrary direto em kb_nodes
- ❌ Embeddings vectorizados em coluna separada — V1 confia em Meilisearch hybrid embedder ([ADR 0035](../../decisions/0035-stack-ai-canonica-wagner-2026-04-26.md))
- ❌ Grafo distribuído (Neo4j, Dgraph) — V1 MySQL é suficiente até 100k nodes
- ❌ Webhook KB → ADS pra disparar Brain B em decision-tree complexa — ONDA 6
- ❌ Skill `/kb-curate` que sugere edges ao curador — ONDA 7+
