# Estado-da-arte — KB scope-aware + observer-weighted ranking

**Data:** 2026-05-17
**Agent:** `estado-da-arte` (subagente Opus)
**Solicitante:** Wagner ([W])
**Tema:** Como modelar (a) escopo multi-profundidade (Empresa → Grupo → CNAE → Região → Setor) e (b) ranking observer-weighted (pesos variam por viewer + recursos disponíveis).
**Output:** decisão de design conceitual — **não código**, **não tasks MCP**, **não ADR**.

---

## TL;DR (200 palavras)

O mercado 2026 NÃO entrega "observer-weighted scope-aware KB" pronto. Entrega 2 metades separadas: (a) **scope via workspace nesting** (Notion/Claude Projects/Perplexity Spaces/ChatGPT Projects — Tier-0 isolation por workspace, sem cascata semântica) e (b) **personalization via signals implícitos** (Glean — papéis + co-autoria + grafo organizacional re-ranking pós-retrieval). Glean é o único que une os dois em produto sério; mas é hard-tenant (Empresa-única), não modela "este fato vale mais pra Larissa vendendo DTF agora do que pra Larissa planejando filial".

Recomendação: **DAG explícito de escopo** (`kb_scopes` separado de `business_id` intransponível) com **3 profundidades canônicas** (business → vertical/CNAE → projeto) + tags multi-dimensionais ortogonais; e **ranking híbrido em 2 estágios** — retrieval scope-cascade (mais específico ganha, geral é fallback) + re-rank query-time observer-weighted com features explícitas (papel, recursos declarados, horizonte temporal, urgência). Audit trail "você vê isso porque é X com recursos Y" obrigatório (mata filter bubble + LGPD). Custo: ~2-3 semanas IA-pair Onda 6+ do KB; pré-req: ContextoNegocio (ADR 0052) já existe e cobre 60% do trabalho de "observer profile".

---

## Fase 1 — Pesquisa estado-da-arte (10 referências)

### Tabela síntese

| # | Player | Mecanismo principal | Bom em | Ruim em |
|---|---|---|---|---|
| 1 | **Glean** | Hybrid IR (lexical + dense) + re-rank pós-retrieval com sinais organizacionais (role, co-authorship, team graph, freshness) + permission-aware filtering | Personalization implícita zero-config; combina IR clássico + LLM + embeddings | Hard-tenant Empresa-única; sem cascata de escopo aninhado; sem "recursos do observador" explícitos |
| 2 | **Notion AI Q&A** | RAG com chunks + embedding (transformer) + metadata rica (page title, workspace_id, permissions, content type) + permission-aware retrieval; em jul/2025 migrou pipeline pra Ray on Anyscale | Workspace nesting nativo (pages dentro de pages, herança de permissões); RAG production-grade em milhões de workspaces | Sem observer-weighted ranking explícito; relevância depende quase só de similaridade semântica + recency |
| 3 | **Linear** | Filtros + custom views salvas (pessoais ou de team); natural language filter ("show me issues assigned to me"); display options persistidas | Perspectiva = filtro explícito declarado pelo viewer (controle total, audit trivial); zero filter bubble | Não é RAG/semântico; perspectiva é "pull" não "push" — usuário declara filtro toda vez |
| 4 | **Stripe Connect** | "View Dashboard As" — admin da plataforma simula visão de connected account | Audit trail explícito (você está vendo como X); útil pra suporte/debug | Não cobre ranking; cobre apenas perspectiva de identidade (autenticação assumed) |
| 5 | **ChatGPT Projects** | Workspace self-contained: (a) system instructions, (b) uploaded files 5-40, (c) Project Memory partitioned (memories não vazam pra main chat nem entre projects) — rollout Ago/2025 | Isolation forte entre scopes (memory partition); custom instructions por projeto = "persona-config" simples | Custom instructions é texto livre (não estruturado); sem ranking observer-aware computado |
| 6 | **Mem.ai / Reflect** | Mem: AI-first auto-organize, "Similar Mems" surface enquanto digita, semantic search. Reflect: networked thought, bidirectional links manuais, e2e encryption | Mem entende contexto do que você está fazendo agora (observer inferido por atividade recente); Reflect controla overload com curadoria humana | Mem opaco demais (usuário não entende por que viu algo); Reflect zero AI — manual |
| 7 | **Perplexity Spaces** | Spaces = scope explícito: custom instructions (persona permanente) + arquivos + web search + AI cross-references docs locais + web | Persona explícita por Space (config declarativa, não inferida) | Sem hierarquia de Spaces; flat scope |
| 8 | **Claude Projects (Anthropic)** | Workspace = chat histories + knowledge base + custom instructions; RAG advanced em Pro/Team/Enterprise (capacity ↑↑); team share com granular permissions | Knowledge base = RAG grounded; system prompt = perspectiva declarada | Sem nesting (Projects são flat); sem observer-weighted ranking |
| 9 | **Coda AI** | Context dropdown explícito por chamada AI (no context / current page / current doc / highlighted text/table); ações automáticas (extract decisions, action items) | Decision-context awareness é feature de primeira classe (usuário escolhe escopo de cada query) | Manual demais; ranking é só "está no escopo selecionado ou não" — binário |
| 10 | **Granola** | AI meeting notes com "Recipes" (lenses /perfect-followup, /summary, /action-items); Spaces compartilhados; MCP server + APIs pessoal e enterprise pra integrar contexto em workflows externos | Lente trocável em runtime = mesmo dado, perspectivas diferentes sob demanda | Domínio meetings (não generalizável a KB inteiro); persona é por recipe, não por viewer |

### Papers/research relevantes (2024-2025)

- **RankRAG (NeurIPS 2024)** — instruction-tune 1 LLM pra ranking+geração no mesmo modelo; relevante porque o re-rank fica barato (não precisa modelo separado). Não cobre observer-aware explícito, mas mostra que LLM pode incorporar "qual observador" no prompt do ranker. [arxiv 2407.02485](https://arxiv.org/html/2407.02485v1)
- **GraphRAG (ACM TOIS 2025 survey)** — retrieval sobre KG hierárquico (tree ou DAG) com summaries em níveis superiores. Justifica DAG sobre tree estrito quando entidade pertence a múltiplos pais. [dl.acm.org/10.1145/3777378](https://dl.acm.org/doi/10.1145/3777378)
- **Filter Bubble / Homogenization (TheWebConf 2024)** — recomendação personalizada produz 2 outcomes adversos: homogenization (todos veem o mesmo apesar de preferências diferentes) E filter bubble (cada um vê só o que reforça). Mitigation: diversity + transparency + user control. [dl.acm.org/10.1145/3589334.3645497](https://dl.acm.org/doi/10.1145/3589334.3645497)
- **RAGSys / ColdRAG (RecSys 2024-2025)** — RAG resolve cold-start: novo viewer sem histórico recebe ranking razoável via fallback a perfil de role/posição. [arxiv 2405.17587](https://arxiv.org/html/2405.17587v2)
- **Lost-in-the-middle + positional bias** — LLMs ignoram contexto no meio; reranking importa pra qualidade. Implicação direta no design: top-K rerankado tem que ser pequeno (~10) e os top-3 reais devem estar no início.
- **Hierarchical KG QA (Enterprise Information Systems 2025)** — survey reforça multi-level com summaries ascendentes pra performance em corpus grande. [tandfonline.com/doi/abs/10.1080/17517575.2025.2580477](https://www.tandfonline.com/doi/abs/10.1080/17517575.2025.2580477)

### Top 5 padrões canônicos estado-da-arte

1. **Workspace nesting com permission-aware retrieval (Notion/Claude/Perplexity)** — scope = container hard, permissões herdadas. Funciona porque permission filtering é deterministic e cacheável.
2. **Re-rank pós-retrieval com sinais organizacionais (Glean)** — IR clássico devolve top-100, ranker re-pontua com role + co-authorship + freshness + permission gates. Custo controlado: re-rank só os 100 já filtrados.
3. **Persona declarativa via system prompt por scope (Perplexity Spaces / Claude Projects / Coda AI)** — usuário escreve "como Larissa vendedora DTF" no instruction field, e isso vira contexto persistente. Baixo custo de engenharia, alto controle do usuário.
4. **Custom views salvas como filtros explícitos (Linear)** — perspectiva é uma query nomeada, não um modelo de IA. Audit trivial, zero filter bubble, fácil compartilhar.
5. **Lenses trocáveis em runtime (Granola Recipes / Coda context dropdown)** — mesmo dado, perspectivas múltiplas sob demanda via slash-command. Mata "switcher viewer" pesado — vira ação atômica.

### Top 5 anti-patterns documentados

1. **Filter bubble por excesso de personalização** — Larissa nunca vê info que não bate perfil → perde fato crítico (margem caindo num produto fora do mix). Glean/Notion mitigam parcial com freshness; Linear evita via filtro explícito.
2. **Opacidade ("por que estou vendo isso?")** — Mem.ai famoso por sugestões "mágicas" que usuário não entende. Em ERP-BR com Larissa não-técnica, **fatal** — vai parar de confiar. Sempre evidence chip.
3. **Re-rank per-viewer per-query naive (custo O(N·M))** — Glean caches re-rank de queries comuns + usa features pre-computadas no índice. Sem cache, ranking observer-weighted em 5000 nodes × 5 viewers ativos = explosão de latência + custo IA.
4. **Persona declarativa virando system prompt gigante (text-blob)** — ChatGPT Projects + Perplexity sofrem disso: instructions crescem para 5kb texto + nunca são versionados + ninguém entende interação com novas perguntas. ContextoNegocio (ADR 0052) já evita isso: campos estruturados, não blob.
5. **Workspace nesting profundo (>3 níveis) em UI** — Notion permite N níveis; usuários se perdem. Best-practice é 2-3 níveis máx visíveis + breadcrumb sempre. ChatGPT/Claude Projects ficam em 1 nível por isso.

---

## Fase 2 — Compare com o que oimpresso tem hoje

### Inventário do que já existe (auditado em memory/)

| Capacidade | Estado oimpresso 2026-05-17 | Arquivo/referência |
|---|---|---|
| **Multi-tenant Tier 0** | Forte. `business_id` global scope em todas tabelas; Pest cross-tenant biz=1 vs biz=99 obrigatório | [ADR 0093](../decisions/0093-multi-tenant-isolation-tier-0.md) |
| **KB grafo de conhecimento** | Aceita 2026-05-16, schema V1 detalhado (11 tabelas), implementação em 6 ondas, ONDA 1-5 mergeada PR #934 | [ADR 0150](../decisions/0150-kb-unificado-grafo-conhecimento-modulo-ia-central.md) + `memory/requisitos/KB/SCHEMA-DB-V1.md` |
| **ContextoNegocio 3-ângulos** | Aceita 2026-04-29 e validada em prod. Cada métrica expõe múltiplos recortes (bruto/líquido/caixa) com glossário inline; ~270 tokens por business; cache TTL 10min | [ADR 0052](../decisions/0052-contextonegocio-expor-multiplos-angulos.md) |
| **MemoriaContrato + Meilisearch hybrid** | Stack canônica laravel/ai 0.6.3 + MeilisearchDriver default + Ollama embedder; RAG production-ready | [ADR 0035](../decisions/0035-stack-ai-canonica-wagner-2026-04-26.md) |
| **Modular especializado por vertical** | Vestuario (prod) / ComunicacaoVisual (construção) / OficinaAuto (aguarda sinal) — CNAE-driven mas hardcoded em Modules/ | [ADR 0121](../decisions/0121-oimpresso-modular-especializado-por-vertical.md) |
| **Categorias/subcategorias** | `kb_categories` + `kb_subcategories` (2 níveis, business-scoped, auto_match opcional via field/op/value JSON) | SCHEMA-DB-V1.md §5 |
| **Edges tipadas** | 8 tipos (`next-in-path`, `fix-of-decision`, `supersedes`, `charter-of`, `references-data`, `ai-related`, `cross-link`, `related-by-tag`) com weight 0-1 e generated_by (manual/bridge_job/ai_embed/tag_overlap/user_action) | SCHEMA-DB-V1.md §4 |
| **Persona declarativa** | Trilhas (`kb_paths`) têm persona-target Larissa/Wagner/Mateus/Eliana/Iniciante; é tag, não config de ranking | SCHEMA-DB-V1.md §6 |
| **Observer-weighted ranking** | **NÃO EXISTE**. Nenhum sinal de viewer profile afeta retrieval. | (gap) |
| **Hierarquia de escopo (Empresa→Grupo→CNAE→Região)** | **NÃO EXISTE**. `business_id` é flat (1 nível). Vertical CNAE é convenção em Modules/, não dado relacional. | (gap) |
| **Audit "você vê X porque é Y"** | Parcial. Audit trail genérico em `mcp_audit_log` (LGPD), mas não há "porque feature" exposto na UI. | (gap parcial) |

### Tabela comparativa estado-da-arte vs oimpresso

| Dimensão | Estado-da-arte 2026 | oimpresso hoje | Distância |
|---|---|---|---|
| Permission-aware retrieval em RAG | Notion/Claude/Glean: filtra resultados por ACL antes/durante retrieval | `business_id` global scope intransponível (mais forte que ACL semântico — é dual-write impossível) | **bate ou supera** |
| Workspace nesting (≥2 níveis) | Notion permite N; Claude/Perplexity 1; Linear via views | 1 nível só (`business_id` flat) | **curta a longa** (depende se precisa) |
| Persona declarativa por scope | Perplexity custom instructions, Coda context dropdown, Granola Recipes | Trilhas têm persona-target (tag); ContextoNegocio tem 3 ângulos de métrica | **média** — base existe, falta UX de "lente" |
| Re-rank pós-retrieval com sinais | Glean: role + co-authorship + team graph + freshness + permissions | KB ONDA 5 vai entregar `KbRagService` mas sem observer features — só BM25+embedding+graph proximity | **longa** |
| Audit "você vê X porque..." | Stripe Connect (View As); Linear (filter chips visíveis) | `mcp_audit_log` (LGPD post-hoc); UI sem evidence chips | **longa** |
| Recursos do observador (caixa/capacidade/urgência) afetam ranking | **NINGUÉM faz isso bem em 2026** — papers só (HyDE / personalized embedding); produtos não | Não existe | **GAP DE MERCADO** — Wagner está na fronteira aqui |
| Lente em runtime (mudar perspectiva sem reload) | Granola /recipes, Coda context dropdown | Não existe | **média** (fácil de adicionar dado o schema) |
| Cold-start novo observador | RAGSys/ColdRAG: fallback a role profile | Larissa entra pela 1ª vez → KB cai em ranking BM25 puro | **média** |
| Filter bubble mitigation | Notion: freshness; Linear: filtro explícito; pesquisa: diversity injection | KB tem `outdated_votes` mas não impede ranking enviesado | **média** |

### Onde oimpresso já bate ou supera

- **Multi-tenant Tier 0** — mais rigoroso que SaaS-RBAC enterprise. Notion/Glean são hard-tenant Empresa-única (1 tenant por dataset); oimpresso é hard-tenant + intransponível + Pest cross-tenant biz=99. Concorrente direto BR (Bling/Tiny/Conta Azul/Omie) não chega perto.
- **ContextoNegocio 3-ângulos validado em prod** — a maioria dos players expõe "1 número" via RAG e deixa LLM inventar matemática. Wagner já aprendeu via Larissa real que isso quebra (ADR 0052). Esse insight é estado-da-arte; falta replicar pra outras métricas (custos, lucro, inadimplência — já mapeado §"Princípios derivados" do ADR 0052).
- **Schema KB com edges tipadas + weight + generated_by** — granularidade que Notion não expõe (lá é tudo "linked references" sem tipo). Mais perto de KG acadêmico que de produto SaaS.
- **Modular por vertical com Vestuario em prod 2 anos** — não é "promessa" como Glean Enterprise; é caso real com ROTA LIVRE rodando.

### Onde a distância é longa

- **Observer-weighted ranking** — não existe nem stub. Adicionar é trabalho real (~2-3 semanas Onda 6+).
- **Hierarquia explícita Empresa→Grupo→CNAE→Região→Setor** — schema atual não modela. Larissa biz=4 é flat; quando ComunicacaoVisual escalar com 6 candidatos saudáveis OfficeImpresso, vai querer "fato sobre ComVis BR vs Sul vs SP" — não tem mecanismo.
- **Evidence chips na UI** — KB ONDA 5 entrega Cytoscape mas sem "ranking explanation". Risco filter bubble com Larissa não-técnica.

---

## Fase 3 — Gaps priorizados + recomendação

### Decifrando a tese do Wagner (importante antes do gap list)

> "cada pessoa vai querer ver a análise pela perspectiva dela. os pesos vão variar de acordo do observador, vai depender do recurso que o observador tiver — a informação vai valer a pena ou não."

Operacionalizando:

| Dimensão do "observador" | Sinal estrutural | Já existe no oimpresso? |
|---|---|---|
| **Identidade** (Larissa, Wagner, Mateus) | `user_id` + `users.role` (spatie) | ✅ |
| **Papel funcional** (operacional / governança / comercial) | Falta — implícito em roles spatie | ⚠️ Parcial |
| **Recursos disponíveis** (caixa, capacidade ociosa máquina, horizonte de tempo) | ContextoNegocio tem caixa; falta capacidade/horizonte | ⚠️ Parcial |
| **Decisão em curso** (vender DTF agora / planejar filial / contratar) | Não modelado em dado nenhum | ❌ |
| **Urgência** (resposta agora / análise mensal) | Inferível por atividade recente (`mcp_audit_log`) | ⚠️ Indireto |
| **Contexto operacional** (na OS X agora / na NFe Y agora) | `source_entity_type/id` em kb_nodes existe; sessão atual não modelada | ⚠️ Parcial |

O "recurso muda os pesos" do Wagner é exatamente o que falta em **todos os 10 players pesquisados**. Glean chega mais perto modelando role+team, mas não modela orçamento/capacidade/horizonte.

Portanto **oimpresso pode ser pioneiro nessa dimensão**, com vantagem: ContextoNegocio (ADR 0052) já tem 60% da feature de "recursos do observador" (caixa do business). Falta:
1. Capacidade ociosa por recurso (máquina/setor/pessoa)
2. Horizonte temporal declarado pela pergunta
3. Decisão em curso (campo opcional em sessão)

### Decisão de design: tree vs DAG vs flat-tagged

**Recomendação: DAG explícito com 3 profundidades canônicas + tags multi-dim ortogonais.**

Justificativa:

- **Tree estrito (1 pai)** — falha porque Larissa biz=4 é simultaneamente "ROTA LIVRE" (empresa) E "Vestuario" (vertical CNAE) E "Sul/SC" (região) E "individual" (não-grupo econômico). Forçar 1 pai obriga escolha falsa.
- **DAG sem hierarquia** — permite N pais mas perde cascata semântica (qual escopo "ganha" quando o fato existe em 2). Estado-da-arte (GraphRAG) prefere DAG com pesos nas arestas pra resolver.
- **Flat com tags multi-dim ortogonais** — Pinecone namespaces / Linear labels. Simples, mas não permite **cascata** (mais específico vence, geral é fallback) — exatamente o que Wagner descreveu como tese.

A escolha canônica é **DAG com profundidade declarada por aresta**. O nó "ROTA LIVRE biz=4" pertence a múltiplos escopos via arestas `belongs-to-scope`; cada aresta tem `depth` (1=mais específico, 5=mais geral) pra resolver tiebreak.

**Profundidade-alvo: 3 níveis canônicos (não mais).**

| Nível | Significado | Exemplo |
|---|---|---|
| **1 (mais específico, intransponível)** | `business_id` — o que existe hoje. Tier 0 IRREVOGÁVEL. Nunca cascateia entre. | ROTA LIVRE (biz=4) |
| **2 (cluster opcional)** | `scope` (grupo econômico OU CNAE OU região) — DAG, business pertence a 0..N | Vestuario (CNAE 4781-4/00), Sul/SC |
| **3 (universal)** | `global` — fatos que valem pra qualquer business sem PII (regulação CONFAZ, manual Roland VS-540, lei CLT) | "Portaria MTP 671/2021" |

Maior que 3 níveis em UI = perde-se (anti-pattern §5). Internamente o schema permite mais (depth INT), mas UI exibe ≤3.

**Por que NÃO 5 ou 7**: Notion/Claude evitam profundidade >2-3 visíveis. Wagner é técnico mas Larissa não. Larissa precisa entender "isso vale só pra eu / pra meu setor / pra todo mundo".

### Esboço de schema conceitual (NÃO código)

```sql
-- Acrescentar a kb_nodes (V2)
ALTER TABLE kb_nodes ADD COLUMN scope_visibility VARCHAR(20) NOT NULL DEFAULT 'business'
  COMMENT 'business | scope | global — controla cascata de leitura';
-- business: só este business_id vê (default, preserva Tier 0)
-- scope: business_id E quem compartilha scope via kb_node_scopes
-- global: todos (raros, sem PII)

-- Nova tabela kb_scopes (DAG nodes)
CREATE TABLE kb_scopes (
    id BIGINT PRIMARY KEY,
    slug VARCHAR(60) UNIQUE,           -- 'vestuario-cnae-4781', 'sul-sc', 'comunicacao-visual'
    label VARCHAR(120),
    kind VARCHAR(40),                  -- 'cnae' | 'regiao' | 'grupo-economico' | 'setor-interno'
    parent_scope_id BIGINT NULL,       -- DAG: pode apontar pra outro scope (nested)
    depth SMALLINT NOT NULL DEFAULT 2  -- 1=específico, 3=universal
);

-- Bridge business ↔ scope (M:N, DAG)
CREATE TABLE kb_business_scopes (
    business_id INT,
    scope_id BIGINT,
    PRIMARY KEY (business_id, scope_id)
);

-- Bridge node ↔ scope adicional (quando node compartilha além do business)
CREATE TABLE kb_node_scopes (
    node_id BIGINT,
    scope_id BIGINT,
    weight DECIMAL(3,2) DEFAULT 1.0,   -- quão forte é a vinculação
    PRIMARY KEY (node_id, scope_id)
);

-- Perfil do observador (estende users; nullable preserva back-compat)
CREATE TABLE kb_observer_profiles (
    user_id BIGINT PRIMARY KEY,
    business_id INT NOT NULL,
    role_functional VARCHAR(40),       -- 'operacional' | 'governanca' | 'comercial' | 'financeiro'
    resources_json JSON,               -- {caixa_disp, capacidade_ociosa_horas, equipamentos_disponiveis}
    horizon_default VARCHAR(20),       -- 'agora' | 'mes' | 'trimestre' | 'ano'
    updated_at TIMESTAMP
);

-- Decisão em curso opcional (sinal forte de re-ranking)
CREATE TABLE kb_observer_intents (
    id BIGINT PRIMARY KEY,
    user_id BIGINT,
    business_id INT,
    intent_label VARCHAR(80),          -- 'vender DTF hoje', 'planejar filial SP'
    intent_payload JSON,               -- {horizonte, orcamento, urgencia}
    active_until TIMESTAMP,            -- TTL pra evitar drift
    created_at TIMESTAMP
);
```

Pseudo, conceitual. Detalhes (índices, FKs, charset) ficam no SCHEMA V2 quando ADR aceitar.

### Como entra no ContextoNegocio 3-ângulos existente

ContextoNegocio (ADR 0052) hoje injeta no system prompt:
```
FATURAMENTO ÚLTIMOS 90 DIAS:
  - BRUTO   R$ 38.215,07
  - LÍQUIDO R$ 37.518,47
  - CAIXA   R$ 35.440,25
```

Versão V2 com observer-weighted adiciona um **bloco "observador"**:
```
OBSERVADOR ATUAL:
  - papel: operacional (vendas balcão)
  - recursos: caixa R$ 35.440 disponível · 12h capacidade Roland VS-540
  - horizonte declarado: agora-7d
  - decisão em curso: vender DTF (margem 220%)

PRIORIDADE DOS FATOS PRA ESTA PERGUNTA (boost pré-rerank):
  - fatos sobre DTF: +0.4
  - fatos com horizonte ≤7d: +0.3
  - fatos que exigem caixa > R$ 35k: -1.0 (filtra)
```

LLM recebe boost explícito + filtro de recursos + horizonte. Mesmo dado, ranking distinto por observador. Custo: ~80 tokens adicionais por system prompt (ainda <400 total, OK).

### Algoritmo de ranking proposto (híbrido 2 estágios)

**Estágio 1 — Retrieval scope-cascade (mesmo custo de BM25/embedding hoje)**

1. Query Meilisearch hybrid embedder (já existe — ADR 0035) sobre `kb_nodes` com filter `business_id = X` (Tier 0 intransponível).
2. UNION ALL com `business_id = X AND scope_id IN (scopes_do_business)` (visibility=scope).
3. UNION ALL com `scope_visibility = 'global'` (universal, sem PII).
4. Top-K=100, dedup, ordenado por (depth ASC, score DESC) — **mais específico cascateia primeiro, geral é fallback**.

**Estágio 2 — Re-rank observer-weighted (somente sobre top-100)**

Função de score:

```
final_score = α·base_score
            + β·role_match(node, observer.role_functional)
            + γ·resource_match(node.requires, observer.resources)
            + δ·horizon_match(node.horizon_validity, observer.horizon)
            + ε·intent_match(node.tags, observer.intents_active)
            + ζ·freshness(node.updated_at)
            + η·diversity_penalty(top-3 já selecionados)
```

α/β/.../η são pesos calibráveis (start: 0.4/0.15/0.15/0.10/0.10/0.05/0.05). Diversity_penalty mata filter bubble forçando 1 dos top-3 a vir de scope diferente.

**Custo computacional**: top-K=100 × 1 viewer × função aritmética = O(100). Cacheável por (query, viewer_profile_hash) com TTL 5min. Em 50 perguntas/dia × 5 viewers = 250 reranks/dia. Trivial.

**Cold-start (Larissa nova, sem `kb_observer_intents`)**: cai pra role_match puro com defaults; ainda melhor que ranking sem viewer. Não há fallback "ruim".

### Comparativo % maturidade

| Capacidade | oimpresso hoje | Estado-da-arte 2026 | % matur. |
|---|---|---|---|
| Multi-tenant isolation | Tier 0 IRREVOGÁVEL | Hard-tenant SaaS (Notion/Glean) | **110%** |
| KB grafo tipado | 11 tabelas, 8 edge types, ONDA 1-5 mergeada | GraphRAG papers ACM 2025 | **85%** |
| RAG production | laravel/ai + Meilisearch hybrid | Notion Anyscale/Ray jul/2025 | **75%** |
| Persona declarativa | Trilha tem persona-target tag | Perplexity Spaces custom instructions | **45%** |
| Scope nesting | flat business_id; CNAE em Modules/ | Notion N-level workspace | **20%** |
| Observer-weighted ranking | inexistente | Glean signals (role/co-author/team) | **5%** |
| Recursos do observador afetam pesos | inexistente | **ninguém faz bem** | **0% (mas mercado também 5-10%)** |
| Audit "porque você vê X" | mcp_audit_log post-hoc | Stripe View As + Linear filter chips | **30%** |
| Filter bubble mitigation | outdated_votes apenas | Diversity injection (papers TheWebConf 2024) | **15%** |
| Cold-start ranking | BM25 fallback | RAGSys/ColdRAG role profile | **40%** |
| **MÉDIA** | — | — | **~45%** |

oimpresso está em ~45% do estado-da-arte agregado, com 2 dimensões superando o mercado (multi-tenant + KB schema granular) e 3 dimensões com gap longo (scope nesting, observer-weighted, evidence chips). A janela pra ser pioneiro está aberta em "recursos do observador" — mercado todo está em 5-10% nessa dimensão.

### Gap list priorizado (top 10, impacto × esforço)

| # | Gap | Impacto | Esforço IA-pair | Pré-req? |
|---|---|---|---|---|
| 1 | **`kb_observer_profiles` + `kb_observer_intents` (perfil + intent ativa)** | Alto — desbloqueia todo o resto | ~6h IA-pair (1 migration + 1 service + 1 endpoint settings) | Nenhum |
| 2 | **Re-rank observer-weighted no `KbRagService` (ONDA 4 já mergeada)** | Alto — Larissa começa a sentir resposta "minha" | ~10h IA-pair (estender service + cache + Pest) | Gap #1 |
| 3 | **ContextoNegocio v2 com bloco "observador" no system prompt** | Alto — fecha loop Larissa via Jana imediatamente | ~4h IA-pair (estender ContextSnapshotService) | Gap #1 |
| 4 | **Evidence chips na UI KB (`/kb` listagem + tri-pane)** | Alto — mata filter bubble + LGPD-friendly + ensina Larissa | ~8h IA-pair (componente + boost reason exposto via API) | Gap #2 |
| 5 | **`kb_scopes` + `kb_business_scopes` + `kb_node_scopes` (DAG escopo 3-níveis)** | Médio-alto — abre cascata pra ComunicacaoVisual quando escalar 6 candidatos | ~12h IA-pair (3 migrations + seeders Vestuario/ComVis/OficinaAuto/Sul-SC + UI selector) | Nenhum (mas faz sentido depois de #1-#3) |
| 6 | **Switcher "ver pelos olhos de X" (Wagner debug Larissa, à la Stripe View As)** | Médio — só Wagner usa, mas crítico pra suporte | ~5h IA-pair (toggle admin + impersonation com audit) | Gap #1 |
| 7 | **Diversity injection no top-3 (1 vinda de scope diferente)** | Médio — mata filter bubble silencioso | ~3h IA-pair (penalty term no scoring) | Gap #2 |
| 8 | **Lenses runtime (slash command `/perspectiva: planejamento mensal`)** | Médio — UX delicada, beneficia Wagner muito mais que Larissa | ~8h IA-pair (parser comando + override temporário do intent) | Gap #1 |
| 9 | **Cold-start fallback role-based pra novo viewer** | Baixo (hoje só Larissa+Wagner ativos) | ~3h IA-pair (default profile por role spatie) | Gap #1 |
| 10 | **Auditor "ranking explainer" admin (`/copiloto/admin/ranking-debug?query=X&user=Y`)** | Baixo (debug Wagner) | ~4h IA-pair (endpoint + view JSON) | Gap #2, #4 |

**Total trilha completa (#1-#4 core MVP)**: ~28h IA-pair = ~3.5 dias úteis fator-10x (ADR 0106). Margem 2x = 7 dias úteis.

### Recomendação concreta

**Comece pelo Gap #1 (`kb_observer_profiles` + `kb_observer_intents`) — alto impacto, baixo esforço (~6h IA-pair), zero pré-req bloqueante, desbloqueia 5 outros gaps.**

Razões:

1. **É a fundação intransponível.** Sem perfil estruturado do observador, qualquer ranking que tentar fazer (#2, #3, #4) volta pra reescrever isso depois.
2. **Pode coexistir com ranking atual.** A tabela existe vazia, profile NULL faz fallback ao comportamento de hoje. Zero risco de regressão.
3. **Loop fechado com Larissa imediato após Gap #3** — adicionar bloco "observador" no ContextoNegocio v2 (Jana já consome ContextSnapshotService) faz a Larissa sentir resposta personalizada antes mesmo de mexer no KB.
4. **Mata o gap de mercado em ~3 semanas.** Nenhum dos 10 players pesquisados modela recursos do observador. Wagner pode escrever ADR + post + Capterra-update destacando "ERP-BR primeiro a personalizar análise por recurso disponível do observador".

**Pré-req checado:** zero. Não precisa de aprovação Wagner pra mais nada além desta recomendação.

**Próxima ação hoje:** Wagner aprova esboço conceitual desta sessão → criar **ADR proposta** `memory/decisions/proposals/NNNN-observer-weighted-kb-scope-dag.md` formalizando (a) decisão tree-vs-DAG (DAG 3-níveis), (b) schema `kb_observer_*` + `kb_scopes`, (c) algoritmo ranking 2-estágios, (d) seção "diferencial vs mercado". ADR sintetiza esta sessão em formato canon, vira ponto de partida pra Onda 6.

### Pesquisa adicional sugerida

- **Implícito vs explícito profile**: Glean inferre (atividade + grafo); Perplexity declara (custom instructions). Híbrido (declarado + ajustado por atividade) parece ideal mas custa governança LGPD. Validar com Eliana antes de escolher.
- **Paper "Hard Negatives, Hard Lessons" (Findings EMNLP 2025)** — implicações pra treinar reranker observer-aware. [aclanthology.org/2025.findings-emnlp.481](https://aclanthology.org/2025.findings-emnlp.481.pdf)
- **Paper "How Good are LLM-based Rerankers" (Findings EMNLP 2025)** — viabilidade de usar Sonnet/Haiku como reranker observer-aware vs cross-encoder dedicado. Custo-benefício importa porque cada query passa por reranker. [arxiv 2508.16757](https://arxiv.org/pdf/2508.16757)
- **GraphRAG ACM TOIS 2025 survey** — formalização DAG-vs-tree e cascata. [dl.acm.org/10.1145/3777378](https://dl.acm.org/doi/10.1145/3777378)
- **Restraining Filter Bubbles (JASIST 2025)** — algorithmic affordances pra equilibrar diversidade × personalização. Específico pra dimensão "Larissa não-técnica + mobile 30%". [asistdl.onlinelibrary.wiley.com/doi/10.1002/asi.24988](https://asistdl.onlinelibrary.wiley.com/doi/10.1002/asi.24988)
- **RAGSys paper** — cold-start como RAG; pode informar fallback pra novo viewer sem histórico. [arxiv 2405.17587](https://arxiv.org/html/2405.17587v2)
- **Constituição v2 oimpresso §6 + ADR 0093** — leitura paralela obrigatória antes de codificar; observer-weighted NÃO PODE atravessar business_id em hipótese alguma. Bloco "observador" no system prompt sempre é deste business.

### Caveats de honestidade epistêmica

- Não encontrei nenhum produto SaaS production que modele "recursos do observador afetam ranking" — termos "observer-weighted", "viewer-aware", "user-conditioned ranking" são raros e ficaram em escopo de paper (não de produto). O mercado está mesmo aberto nessa dimensão.
- Glean é fechado (sem paper público recente sobre algoritmo de re-ranking); minhas inferências sobre features dele vêm de blog posts oficiais 2025, não de código. Calibrar quando time MCP testar Glean numa demo.
- ContextoNegocio v2 (gap #3) tem precedente comprovado (ADR 0052 já validada com Larissa). Outros gaps são extrapolação razoável mas não validada empiricamente — Wagner deve sinalizar com Larissa antes de escala.

---

## Fontes citadas

- [Glean — A Complete Guide to Search Personalization in 2025](https://www.glean.com/blog/search-personalization)
- [Glean — Building Robust Enterprise Search with LLMs and Traditional IR (ZenML LLMOps)](https://www.zenml.io/llmops-database/building-robust-enterprise-search-with-llms-and-traditional-ir)
- [Notion — Q&A: instant answers from your workspace content](https://www.gend.co/blog/notion-q-and-a)
- [DataTinkerer — How Notion Scaled AI Q&A to Millions of Workspaces](https://www.datatinkerer.io/p/how-notion-scaled-ai-q-and-a-to-millions-of-workspaces)
- [Linear Docs — Filters](https://linear.app/docs/filters)
- [Linear Docs — Custom Views](https://linear.app/docs/custom-views)
- [Stripe — Manage connected accounts with the Dashboard](https://docs.stripe.com/connect/dashboard)
- [OpenAI Help — Projects in ChatGPT](https://help.openai.com/en/articles/10169521-using-projects-in-chatgpt)
- [Unite.AI — How to Use ChatGPT's Project Memory](https://www.unite.ai/how-to-use-chatgpts-project-memory/)
- [Aloa — Mem vs Reflect 2025](https://aloa.co/ai/comparisons/ai-note-taker-comparison/mem-vs-reflect)
- [Perplexity Spaces guide (perplexityaimagazine)](https://perplexityaimagazine.com/perplexity-hub/perplexity-ai-spaces-guide-2026/)
- [Anthropic — Collaborate with Claude on Projects](https://www.anthropic.com/news/projects)
- [Coda AI — features overview](https://coda.io/product/ai)
- [Granola — Series C $125M turning meetings into enterprise AI context (TNW)](https://thenextweb.com/news/granola-series-c-meeting-ai-enterprise-context)
- [arxiv RankRAG NeurIPS 2024](https://arxiv.org/html/2407.02485v1)
- [ACM TOIS — Graph Retrieval-Augmented Generation: A Survey](https://dl.acm.org/doi/10.1145/3777378)
- [TheWebConf 2024 — Filter Bubble or Homogenization?](https://dl.acm.org/doi/10.1145/3589334.3645497)
- [arxiv RAGSys 2405.17587](https://arxiv.org/html/2405.17587v2)
- [Hierarchical KG QA Enterprise IS 2025](https://www.tandfonline.com/doi/abs/10.1080/17517575.2025.2580477)
- [JASIST 2025 — Restraining filter bubbles algorithmic affordances](https://asistdl.onlinelibrary.wiley.com/doi/10.1002/asi.24988?af=R)
- [Findings EMNLP 2025 — How Good are LLM-based Rerankers](https://aclanthology.org/2025.findings-emnlp.305.pdf)
- [Findings EMNLP 2025 — Hard Negatives Hard Lessons](https://aclanthology.org/2025.findings-emnlp.481.pdf)
- [WorkOS — How to design an RBAC model for multi-tenant SaaS](https://workos.com/blog/how-to-design-multi-tenant-rbac-saas)

## Cross-refs canon oimpresso

- [ADR 0093 — Multi-tenant Tier 0 IRREVOGÁVEL](../decisions/0093-multi-tenant-isolation-tier-0.md)
- [ADR 0150 — KB Unificado Grafo de Conhecimento](../decisions/0150-kb-unificado-grafo-conhecimento-modulo-ia-central.md)
- [ADR 0052 — ContextoNegocio múltiplos ângulos](../decisions/0052-contextonegocio-expor-multiplos-angulos.md)
- [ADR 0035 — Stack AI canônica](../decisions/0035-stack-ai-canonica-wagner-2026-04-26.md)
- [ADR 0121 — Modular especializado por vertical](../decisions/0121-oimpresso-modular-especializado-por-vertical.md)
- [Schema KB V1](../requisitos/KB/SCHEMA-DB-V1.md) — base pra estender V2 com `kb_scopes` + `kb_observer_*`
