---
page: kb/Graph
controller: Modules\KB\Http\Controllers\KbGraphController@index (TODO Agent A — ONDA 5 backend)
route: kb.graph
status: draft
owner: [W] Wagner
persona_principal: Wagner / governança (1440px desktop)
persona_secundaria: Larissa / operacional gráfica (1280px balcão, ONDA 6+)
charter_version: 1.0
charter_at: 2026-05-15
related_adrs:
  - 0150-kb-unificado-grafo-conhecimento-modulo-ia-central (proposta)
  - 0094-constituicao-v2-7-camadas-8-principios
  - 0093-multi-tenant-isolation-tier-0
  - 0104-processo-mwart-canonico-unico-caminho
  - 0114-prototipo-ui-cowork-loop-formalizado
related_briefing: ../../../memory/requisitos/KB/BRIEFING.md
related_schema: ../../../memory/requisitos/KB/SCHEMA-DB-V1.md
related_index_charter: ./Index.charter.md
---

# Charter — `resources/js/Pages/kb/Graph.tsx`

## Mission

A tela `/kb/graph` é o **coração visual do KB Unificado** — onde Wagner vê **as conexões entre os 143 ADRs + ~500 sessions + ~30 charters + ~50 runbooks + ~10 briefings** como um grafo navegável, com nodes coloridos por tipo, edges tipadas (supersedes, charter-of, cross-link, related-by-tag, ai-related), filtros sidebar e detalhe lateral por click.

Cumpre a promessa central da ADR 0150: *"ter essa visualização sobre meus dados e arquivos mais importantes"* (Wagner, 2026-05-15).

## Goals (o que esta tela DEVE fazer bem)

1. **Renderizar até 5k nodes / 20k edges** sem degradar performance (target: First Contentful Paint <800ms; layout client-side <100ms; click→detail <50ms; double-click→focus subgraph <150ms).
2. **Filtrar grafo** por (a) busca textual em título/slug/tags, (b) tipos de nó visíveis (toggles), (c) tipos de aresta visíveis (toggles), (d) atalhos "Governança apenas" / "ERP apenas".
3. **Modo focus** com depth-slider 1-3 hops — double-click num node faz BFS depth-cap e re-layout dagre top-down (árvore supersedes/charter-of).
4. **Click num node** abre side panel `GraphNodeDetail` com meta (módulo, tags, status, edges_count, last_verified_at) + lista de conexões agrupadas por tipo + botões "Focar aqui" e "Abrir leitor" (vai pra `/kb?slug=X`).
5. **Layout switcher** entre `force-radial` (default, visão geral) / `dagre-tb` (focus em ADR) / `concentric` (fallback determinístico).
6. **Legenda fixa** no topo do canvas — cores por tipo de nó + padrões de aresta.
7. **Keyboard shortcuts:** `/` foca busca, `Esc` limpa focus, futuro `J/K` navega entre nodes ordenados.
8. **KPIs no topo:** total nodes, total edges, outdated_count, last_bridge_at.

## Non-Goals (o que esta tela NÃO faz)

- ❌ Edição de nó (cria/edita artigo) — vive em `Pages/kb/Composer.tsx` (ONDA 3, Agent C)
- ❌ Leitor markdown completo — vive em `Pages/kb/Index.tsx` (Agent B); botão "Abrir leitor" navega pra lá
- ❌ Editor visual de árvore (decision tree) — vive em `Pages/kb/TroubleEditor.tsx` (ONDA 3)
- ❌ Chat IA livre — vive em `Pages/Copiloto/Chat.tsx`; aqui pode haver botão "Perguntar à IA sobre este node" (TODO ONDA 4)
- ❌ Imprimir SOP — fica em modal `KBPrintSOP` invocado da tela leitor
- ❌ Admin de categorias/edges — fica em `Pages/kb/Admin/*` (ONDA 3, baixa prio)
- ❌ Real-time updates via WebSocket — TODO ONDA futura (Centrifugo channel `kb.{biz}.graph`)
- ❌ Virtualização >5k nodes — TODO ONDA futura (renderizar viewport only via reactflow `onlyRenderVisibleElements`)

## UX targets

- **Layout tri-pane** (filters esquerda 240px / canvas centro flex / detail direita 320px quando node selecionado).
- **Persona-principal Wagner 1440px:**
  - Filtros sidebar visível por padrão
  - Detail panel abre lateral (não modal full-screen — charter Index.charter.md §"Anti-padrões")
  - Densidade `comfortable`
- **Persona-secundária Larissa 1280px (ONDA 6+):**
  - Filtros sidebar pode colapsar (TODO[CL])
  - Densidade `dense`, sem rounded-xl
- **Animação de transição entre layouts <300ms** (Reactflow `fitView` duration 250ms padrão).
- **Hover do node** mostra tooltip nativo com title + tipo + count of edges (TODO[CL]: Radix `<Tooltip>` pra estilo consistent).
- **MiniMap** bottom-right pra navegação macro em viewport.

## Cores por tipo de nó (OKLCH hue)

| Tipo | Hue | Justificativa |
|------|-----|---------------|
| `article` | 240 | accent canon (Index.charter §"Restrições design") |
| `adr` | 60 | amarelo-mostarda, atenção governança |
| `session` | 145 | verde, evento histórico |
| `charter` | 200 | cyan, manifesto de tela |
| `runbook` | 30 | laranja, operacional |
| `briefing` | 280 | roxo, executivo |
| `spec` | 250 | azul-cinza, especificação |
| `comparativo` | 320 | magenta, benchmark |
| `os/customer/product/nfe/equipment` | 100 | verde-cinza, dado ERP |
| `external_file` | 320 | magenta, anexo |
| `reference` | 0 (neutral) | sem cor própria |

Tokens completos em `_lib/graphLayout.ts` constante `NODE_COLORS`.

## Estilos por tipo de aresta

| Tipo | Cor (hue) | Traço | Opacity | Sinal visual |
|------|-----------|-------|---------|--------------|
| `next-in-path` | 240 | solid | 0.9 | accent, trilha guiada |
| `fix-of-decision` | 60 | solid | 0.9 | governance, fix de árvore |
| `supersedes` | 25 (vermelho) | dashed | 0.85 | ATENÇÃO substituição |
| `charter-of` | 200 | solid | 0.85 | manifesto ↔ ADR |
| `references-data` | 100 | dotted | 0.7 | cita dado ERP |
| `ai-related` | 240 | dashed | 0.5 | gerada por embedding |
| `cross-link` | 240 | solid | 0.7 | link manual `#kb-XXX` |
| `related-by-tag` | 240 | dotted | 0.45 | overlap de tags (mais fraco) |

## Anti-padrões / Proibições visuais (tokens canon)

Conforme [`Index.charter.md`](Index.charter.md) §"Anti-padrões":

- ❌ `rounded-xl+` — usar `rounded-sm` (charter Index §"Anti-padrões")
- ❌ Cores fora dos tokens OKLCH
- ❌ Emoji em UI cliente-facing — usar ícones lucide (`Search`, `Compass`, `GitBranch`, `FileText`, `X`, `Maximize2`)
- ❌ Inglês em UI cliente-facing — tudo PT-BR
- ❌ Modal full-screen pra detalhe — usar side panel direito
- ❌ Animações >300ms
- ❌ Misturar familia tipográfica — IBM Plex Sans / IBM Plex Mono

## Automation hooks (eventos)

- `kb.graph.node.opened(node_id)` — incremento de `reads_count`
- `kb.graph.node.focused(node_id, depth)` — telemetria pra entender padrões de Wagner
- `kb.graph.layout.switched(mode)` — saber se dagre-tb dominará uso real
- `kb.graph.filter.cleared()` — pra empty-state UX iteração
- `kb.graph.detail.opened_in_reader(node_id)` — funnel pro `/kb` leitor

## Restrições Tier 0 IRREVOGÁVEIS

- `business_id` global scope no Controller — ADR 0093. Endpoint `/kb/graph/data` filtra por `session('business.id')`
- `Inertia::defer()` em props `nodes` e `edges` (queries com count + agregação) — RUNBOOK-inertia-defer-pattern
- F3 MWART canônico 5 fases — ADR 0104. Status atual: **F1 (skeleton+mock+charter)** completo; F2 (backend baseline) pendente Agent A; F3 (este arquivo Pages/kb/Graph.tsx) draft; F4 (Pest tests) pendente Agent C; F5 (cutover) escopo Wagner
- Gate visual screenshot Wagner antes de F4 merge — ADR 0114
- Pest tests biz=1 + cross-tenant biz=99 — ADR 0101

## TODOs catalogados (não-objetivos V1, registrados pra ONDA futura)

- TODO[CL]: instalar `@dagrejs/dagre@^1.1.4` pra layout dagre-tb/lr real (hoje fallback concentric)
- TODO[CL]: WebSocket Centrifugo channel `kb.{biz}.graph` pra updates real-time quando bridge job roda
- TODO[CL]: virtualização >5k nodes via `onlyRenderVisibleElements`
- TODO[CL]: Radix `<Tooltip>` no hover do node (estilo consistente)
- TODO[CL]: keyboard nav `J/K` entre nodes ordenados por relevância (PageRank ou edges_count)
- TODO[CL]: botão "Perguntar à IA sobre este node" no detail panel → /kb/ai/ask?focus={slug}
- TODO[CL]: focus trap WCAG no canvas + atalho `Tab` navega entre nodes
- TODO[CL]: export PNG/SVG do grafo atual (canvas.toBlob)
- TODO[CL]: persist filtros em URL query params (deep-link pra estado do grafo)
- TODO[CL]: fullscreen toggle (botão custom no `Controls` do Reactflow)

## Lib usada

**Reactflow 11.11.4** (xyflow) — JÁ INSTALADA no package.json + JÁ USADA em `Pages/ads/Admin/Graph.tsx` como precedent. Bundle ~150KB. Performance ~5k nodes confirmada.

Layout client-side em `_lib/graphLayout.ts`:
- `concentric` (default fallback, O(n))
- `force-radial` (BFS por hop, O(n + m))
- `dagre-tb` / `dagre-lr` (TODO[CL]: instalar `@dagrejs/dagre`, hoje fallback `concentric`)

## Versionamento desta charter

| Versão | Data | Mudança |
|---|---|---|
| 1.0 | 2026-05-15 | Draft inicial — Agent E ONDA 5 esqueleto. Aguarda F2 BACKEND BASELINE (Agent A) + F4 Pest (Agent C). |
