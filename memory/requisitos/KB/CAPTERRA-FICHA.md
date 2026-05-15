---
modulo: KB
versao_ficha: 2.1
formato: capterra-ficha-canonica (ADR 0089)
gerado_em: 2026-05-15
fonte:
  - prototipo-ui/prototipos/kb/Bench KB.html (v1, 15 dimensões, score 8,27)
  - prototipo-ui/prototipos/kb/Bench KB v2.html (v2, 16 dimensões, score 9,40 — +1,13 após 3 refinos)
  - handoff Cowork (5) 2026-05-15 19:55 — features v5 sobem estimativa pra ~9,55-9,70
proximo_review: Bench v3 quando Cowork [CC] re-medir após sync (5)
---

# CAPTERRA-FICHA — Módulo KB (Knowledge Base unificado IA-powered)

## 1. Posicionamento

ERP-com-KB-IA-integrado pra PME brasileira (foco gráfica/comunicação visual). Diferente da maioria dos KBs (Notion, Confluence, Guru) que são plataformas standalone, o KB do oimpresso vive **dentro do ERP** com integração nativa de dados (OS, vendas, NFe, clientes) e do Copiloto IA (Jana com memória persistente). Atende 2 personas:

- **Wagner / governança:** browser de 143 ADRs + ~500 sessions + ~30 charters + ~50 runbooks + ~10 briefings com visualização-grafo de dependências, RAG IA com citações, trilhas didáticas.
- **Larissa / operacional gráfica:** tri-pane de procedimentos (Calibrar ICC Roland, Trocar bobina HP Latex, Sangria banner 3×2m), troubleshooters interativos, imprimir SOP pra colar no balcão.

## 2. Players comparados (8)

| Player | Tipo | Score | Forte | Frágil |
|---|---|---|---|---|
| **Oimpresso KB (nosso)** | ERP nativo | **~9,55-9,70** | Integração ERP, visualização-grafo, fit gráfica/pt-BR, custo zero | Mobile/campo (mitigado por imprimir SOP) |
| Notion | Geral | 7,75 | Editor blocos, AI, flexibilidade | Sem troubleshoot, sem fit operacional |
| Confluence | Enterprise | 7,40 | Hierarquia, governança | UX velha, lento, denso |
| Guru | Support | 7,95 | Verificação, AI Answers, browser ext | Preço (~R$80/u/mês), hierarquia simples |
| Slab | Startup | 7,30 | Editor, tipografia | Sem troubleshoot, integrações limitadas |
| Stonly | Guias | 7,15 | Decision trees, mobile field, AI guide gen | Editor genérico fraco |
| Intercom Articles | Help center | 7,85 | Fin AI, integração Inbox | Caro, foco em externo |
| Linear Docs (beta) | Eng | — | Keyboard-first, performance | Não é KB-purpose-built |

Bench fonte: `prototipo-ui/prototipos/kb/Bench KB v2.html` (Cowork [CC] self-assessment crítico).

## 3. Matriz de 16 dimensões × 7 concorrentes

(escala 0-10; valores agregados Bench v2 + ajuste features v5)

| # | Dimensão | Nós (v2) | Nós (v5 est.) | Notion | Confl | Guru | Slab | Stonly | Intercom |
|---|---|---|---|---|---|---|---|---|---|
| 1 | Busca rápida (fuzzy, palette, fallback IA) | 9,5 | 9,5 | 8,5 | 8,0 | 8,5 | 8,5 | 6,0 | 8,5 |
| 2 | Hierarquia / taxonomia (cat, subcats, tree) | 9,0 | 9,0 | 8,0 | 9,5 | 7,5 | 7,5 | 5,0 | 7,5 |
| 3 | Editor de conteúdo (blocos, mídia, embeds) | 8,0 | **9,0** | 9,8 | 7,5 | 7,5 | 9,0 | 7,0 | 8,5 |
| 4 | Troubleshoot interativo (árvores, biblio, hist) | 9,8 | **10,0** | 3,0 | 3,0 | 4,0 | 3,0 | 9,8 | 5,0 |
| 5 | Versionamento (frescor, re-verif, diff) | 9,0 | **9,5** | 7,5 | 9,0 | 9,8 | 7,5 | 7,0 | 7,5 |
| 6 | Saúde do KB (analytics 4-quadrantes) | 9,0 | 9,0 | 6,0 | 7,5 | 9,0 | 7,0 | 7,5 | 8,0 |
| 7 | Integração com produto (links a OS) | 9,5 | 9,5 | 6,0 | 7,0 | 8,0 | 6,0 | 5,0 | 8,0 |
| 8 | Command palette ⌘K | 9,5 | 9,5 | 9,0 | 5,0 | 7,5 | 8,0 | 6,0 | 7,0 |
| 9 | Experiência de leitura (TOC, cross-link, present) | 9,5 | 9,5 | 8,0 | 7,5 | 7,5 | 9,0 | 7,5 | 8,0 |
| 10 | Feedback social (votos, comments inline) | 9,0 | 9,0 | 7,5 | 8,0 | 9,0 | 7,5 | 7,5 | 8,5 |
| 11 | AI / assistente (resumir, RAG, citações, auto-tag) | 8,0 | **9,0** | 9,5 | 6,5 | 9,0 | 7,0 | 6,0 | 9,0 |
| 12 | Personas operacionais (balcão, técnico, onboarding) | 9,5 | 9,5 | 6,0 | 5,0 | 7,5 | 6,0 | 8,0 | 6,0 |
| 13 | Densidade visual (info/pixel) | 9,5 | 9,5 | 5,0 | 6,0 | 6,0 | 7,0 | 6,0 | 5,0 |
| 14 | Custo / licenciamento | 10,0 | 10,0 | 6,0 | 5,0 | 5,0 | 5,0 | 4,0 | 5,0 |
| 15 | Fit pt-BR + gráfica | 10,0 | 10,0 | 7,0 | 7,0 | 5,0 | 6,0 | 5,0 | 7,0 |
| 16 | Trilhas de aprendizado | 9,5 | 9,5 | 2,0 | 2,0 | 4,5 | 2,0 | 5,0 | 4,0 |
| 17* | **Imprimir SOP balcão (nova v5)** | — | **10,0** | 4,0 | 5,0 | 3,0 | 5,0 | 4,0 | 4,0 |
| 18* | **Favoritos pessoais (nova v5)** | — | 9,0 | 8,0 | 7,5 | 8,5 | 7,0 | 6,0 | 7,5 |

\* Dimensões 17 e 18 são adições propostas pelo Bench v3 (não medidas no v2 do Cowork — estimativa derivada das features v5).

### Onde já somos melhores ou empatamos com o topo (8 dimensões em v5)

1. **Troubleshoot interativo** — 10,0 (empate técnico com Stonly + editor visual fecha último gap)
2. **Trilhas de aprendizado** — 9,5 (ninguém faz bem)
3. **Imprimir SOP balcão (nova)** — 10,0 (ninguém faz)
4. **Integração ERP** — 9,5 (cita OS/cliente reais nativamente)
5. **Densidade Larissa 1280px** — 9,5
6. **Personas operacionais** — 9,5
7. **Custo** — 10,0 (zero licença extra)
8. **Fit pt-BR/gráfica** — 10,0

### Onde evoluímos mais (8 dimensões com Δ ≥ +0,5 vs v1)

| Dimensão | v1 → v5 | Δ | Causa |
|---|---|---|---|
| AI / assistente | 0 → 9,0 | **+9,0** | RAG + resumir + auto-tag (maior salto histórico) |
| Editor de conteúdo | 5,0 → 9,0 | +4,0 | Block editor + bloco IMAGEM |
| Hierarquia | 7,5 → 9,0 | +1,5 | Subcategorias tree + auto_match |
| Busca | 8,0 → 9,5 | +1,5 | Empty palette → IA fallback |
| Feedback social | 7,5 → 9,0 | +1,5 | Comments inline por block_idx |
| Troubleshoot | 8,5 → 10,0 | +1,5 | Editor visual + biblioteca + histórico |
| Versionamento | 8,0 → 9,5 | +1,5 | Diff block-a-block + restore one-click |
| Leitura | 8,5 → 9,5 | +1,0 | Cross-link automático + modo apresentação |

### Gap remanescente real (1 dimensão)

- **Mobile / técnico de campo (não medida explícita, mas catalogada Bench v2)** — tri-pane quebra <1100px. **Mitigado** (não fechado) por feature v5 "Imprimir SOP": Mateus PCP de mãos sujas tem papel plastificado ao lado da Roland VS-540, sem precisar de mobile-responsive. Roadmap: V2 do KB pode adicionar layout single-column quando viewport <1100px (responsivo gracioso).

## 4. Priorização P0-P3 (escopo ONDA 1-5 Wagner-governança)

### P0 (essencial pra primeiro release)

| Capacidade | Score baseline esperado | Onda | Bloqueador |
|---|---|---|---|
| Browser de ADRs/sessions com tri-pane Cockpit V2 | 9,0 | 1+2 | — |
| Bridge `mcp_memory_documents` → `kb_nodes` (read-only) | n/a (infra) | 1 | — |
| Visualização-grafo dos 143 ADRs + edges supersedes/charter-of/related | 9,5 | 5 | depende kb_edges populado (ONDA 1 bridge) |
| RAG IA "perguntar ao KB" com citações de fonte | 9,0 | 4 | Modules/Copiloto OK (ADR 0035 ✅) |
| Command palette ⌘K + keyboard nav | 9,5 | 2 | — |

### P1 (após primeiro release Wagner ok, antes da Larissa)

| Capacidade | Score | Onda | Bloqueador |
|---|---|---|---|
| Trilhas didáticas pra ADRs canônicas (Constituição v2, Multi-tenant Tier 0, FSM Pipeline) | 9,5 | 3+seed | kb_paths + kb_path_steps (ONDA 1) |
| Resumir doc on-click (Sonnet, cache) | 8,5 | 4 | endpoint /kb/ai/summarize |
| Favoritos pessoais por user_id | 9,0 | 5 | kb_favorites (ONDA 1) |
| Histórico de versões com diff visual (operacional + bridge ADR) | 9,5 | 3 | kb_node_versions (ONDA 1) |

### P2 (Larissa operacional gráfica)

| Capacidade | Score | Onda | Bloqueador |
|---|---|---|---|
| 18 artigos seed do Cowork (Roland, HP Latex, NF-e, etc) | n/a (conteúdo) | 6 | seeder + business_id=4 |
| Troubleshooters Roland/HP/NF-e (3 árvores) | 10,0 | 6 | kb_decision_trees seed |
| Editor visual de árvore de decisão | 10,0 | 3 | KBTroubleEditor port |
| Block editor com bloco IMAGEM (upload, URL, Ctrl+V paste) | 9,0 | 3 | Spatie medialibrary OK |
| Imprimir SOP layout oficial pra balcão | 10,0 | 5 | Spatie\Browsershot |

### P3 (ONDA 6+, decisão Wagner)

| Capacidade | Score | Onda | Notas |
|---|---|---|---|
| `references-data` edges (artigos ↔ OS/cliente/NFe) | 9,5 | 6 | KbScanReferencesJob |
| `useKbContext(modelType, modelId)` hook nos módulos | 9,5 | 6 | mostra KB relevante em telas OS/Cliente |
| MCP server expõe `kb-search`/`kb-graph-query` pro Copiloto/Jana | n/a (infra) | 7 | ADR mãe nova de tools KB no MCP |
| Skill `/kb-curate` sugere edges | n/a | 8 | depende de embeddings estáveis |

## 5. Análise de risco competitivo

### Por que líderes mundiais não cobrem nosso espaço

- **Notion / Confluence** são plataformas de produtividade horizontal — não casam com schema de ERP nativo. Integração via API exige curva longa e custa caro.
- **Guru** tem AI Answers + verificação por dono, mas R$80/user/mês inviabiliza pra PME gráfica. Sem hierarquia profunda nem decision tree.
- **Stonly** é referência mundial em decision tree mas o editor de artigos é fraco, sem ERP, sem IA RAG sobre o próprio KB.
- **Intercom Articles + Fin AI** foca em deflexão de ticket externo. Pouco para uso interno operacional de gráfica.

### Onde competimos perigosamente

- **Glean / Mem (2026+, IA-first KB enterprise)** — concorrentes diretos no "RAG sobre todo conhecimento da empresa". **Diferencial nosso**: a integração nativa de ERP (eles são plataformas de busca/IA federada sobre fontes externas; nós SOMOS a fonte). Atenção: Glean tem captação massiva (~US$ 4B valuation 2024) e pode lançar conectores Bling/Tiny/Conta Azul em 12-18 meses.
- **Notion AI 2026** continua melhorando — vigiar quarterly.

### Janela de oportunidade

PME brasileira (~1M empresas endereçáveis pela visão modular oimpresso — [ADR 0121](../../decisions/0121-oimpresso-modular-especializado-por-vertical.md)) **não tem produto KB-IA decente em pt-BR hoje**. Janela ~18-24 meses até players globais portarem. Vencer com fit local + verticalização (gráfica, oficina auto, comunicação visual) + integração nativa ERP é estratégia viável.

## 6. Backlog de US (proposta — gerado via skill `/comparativo` após aceite ADR 0149)

**ONDA 1 (backend):**
- US-KB-001 Migrations kb_* (11 tabelas) — P0, ~6h
- US-KB-002 Models + global scope business_id + observers — P0, ~4h
- US-KB-003 KbBridgeFromMcpJob (assíncrono + state) — P0, ~6h
- US-KB-004 Controllers + endpoints REST + Inertia routes — P0, ~8h
- US-KB-005 Permissions (PermissionRegistry + Spatie mapping) — P0, ~2h
- US-KB-006 Seeders (categorias + bridge primeiro run + 18 artigos op + 3 trilhas + 3 troubleshooters) — P1, ~4h
- US-KB-007 Pest tests cross-tenant + RBAC + bridge idempotência — P0, ~6h

**ONDA 2 (frontend tri-pane):**
- US-KB-010 Pages/kb/Index.tsx tri-pane Cockpit V2 — P0, ~10h
- US-KB-011 Sub-components (CategorySidebar, ArticleList, ArticleReader, CommandPalette) — P0, ~12h
- US-KB-012 PathsDialog + TroubleshooterDialog (read-only V1, edição em ONDA 3) — P1, ~6h
- US-KB-013 Block renderer (para/h2/list/callout/image) — P0, ~4h
- US-KB-014 Inertia::defer em props caras + skeleton loaders — P0, ~3h

**ONDA 3 (composer + autoria):**
- US-KB-020 Composer + KBBlockEditor port — P1, ~10h
- US-KB-021 Comments inline (kb_comments) — P1, ~5h
- US-KB-022 Versions dialog (kb_node_versions + diff) — P1, ~6h
- US-KB-023 KBTroubleEditor port (editor visual de árvore) — P2, ~8h
- US-KB-024 Auto-tag IA no composer (kbSuggestMeta endpoint + UX) — P1, ~4h

**ONDA 4 (IA RAG):**
- US-KB-030 KbRagService consumindo Modules/Copiloto/Ai/ — P0, ~10h
- US-KB-031 Endpoints /kb/ai/{ask,summarize,suggest-meta} — P0, ~6h
- US-KB-032 UX "Perguntar ao KB" + citações clicáveis — P0, ~5h
- US-KB-033 Cache de embeddings + cache de respostas curtas — P1, ~4h

**ONDA 5 (vis-grafo + impressão + favoritos):**
- US-KB-040 KbGraphController + endpoint /kb/graph/data — P0, ~6h
- US-KB-041 Pages/kb/Graph.tsx com Cytoscape.js (dagre layout) — P0, ~10h
- US-KB-042 KBPrintSOP port (CSS @media print + opcional Spatie\Browsershot) — P2, ~5h
- US-KB-043 useKBFavorites port + estrela em ArticleRow — P1, ~3h

**Total ONDAS 1-5:** ~28 US, ~133h estimadas. Com fator 10x IA-pair ([ADR 0106](../../decisions/0106-recalibracao-velocidade-fator-10x-ia-pair.md)) e paralelização 4-5 agents = ~12-18 dias úteis.

## 7. Métricas pra acompanhar pós-release

- **Adoção:** % de days/week com pelo menos 1 leitura em /kb (por user de biz=1)
- **RAG quality:** % de respostas IA com pelo menos 1 citação clicada (proxy de utilidade)
- **Latência:** P50/P95 de `/kb/ai/ask` (alvo P95 ≤2s pra Sonnet)
- **Custo:** R$/business/mês de tokens consumidos pelo RAG
- **Grafo richness:** kb_edges_count / kb_nodes_count (alvo ≥4 — ADRs têm em média 4-5 edges)
- **Trilha completion rate:** % de trilhas iniciadas que chegam ao último passo
- **Troubleshooter resolution rate:** % de sessões de troubleshooter que terminam em fix (não abandonadas)
