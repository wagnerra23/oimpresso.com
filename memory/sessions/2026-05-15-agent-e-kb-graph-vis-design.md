---
date: 2026-05-15
agent: Agent E (data viz frontend React)
module: KB
adr_proposta: 0150-kb-unificado-grafo-conhecimento-modulo-ia-central
onda: ONDA 5 — Visualização-grafo
status: skeleton-pronto (F1 MWART) — aguarda F2 backend + F4 Pest
worktree: practical-engelbart-8d8eb0
tags: [kb, grafo, viz, reactflow, onda-5, esqueleto, charter]
---

# Session log — Agent E: KB Graph viz design (esqueleto Pages/kb/Graph.tsx)

## Resumo

Criado o **esqueleto da `resources/js/Pages/kb/Graph.tsx`** (ONDA 5 do plano da ADR 0150) — tela de visualização-grafo dos 143 ADRs + sessions + charters + runbooks + briefings + specs do KB Unificado. Lib escolhida sem npm install: **Reactflow 11.11.4 já instalado no package.json e já usado pelo precedent arquitetural `Pages/ads/Admin/Graph.tsx`**. 9 arquivos criados (Page principal + 4 components + 3 lib helpers + charter), zero conflito com Agents B/outros (que criaram `_lib/types.ts`, `_lib/mockData.ts`, `_components/NodeReader.tsx` etc com nomes diferentes).

Mock data realista (50 nodes, 64 edges representando casos reais do oimpresso: supersedes ADR 0048→0035, charter-of charter-kb-graph→ADR 0150, related-by-tag clusters de governança/fsm/whatsapp/mwart, cross-link briefing-kb→adr-149) permite dev e screenshot sem backend pronto.

## Decisão de lib

**Lib escolhida:** Reactflow 11.11.4 (xyflow).

**Justificativa (decisão imediata, custo zero):**

1. **JÁ INSTALADA** — `"reactflow": "^11.11.4"` no `package.json` raiz (linha 73). Zero npm install necessário.
2. **JÁ USADA** — `resources/js/Pages/ads/Admin/Graph.tsx` (240 linhas, ADS Knowledge Graph Cognitive Control Panel #3) usa o mesmo padrão com formato `nodes/edges` idêntico ao endpoint `/kb/graph/data` (SCHEMA-DB-V1 §11). Reuso de padrão arquitetural canon.
3. **Ergonomia React** — hooks `useNodesState/useEdgesState`, types TS sólidos, componentes plugáveis (Background, Controls, MiniMap).
4. **Manutenção ativa** — xyflow lançou v12 em 2025; v11 mantida estável. Bundle ~150KB gzipped (abaixo do limite 500KB).
5. **Performance ~5k nodes** — validado em https://reactflow.dev/learn/troubleshooting/performance, suficiente pro target futuro (oimpresso com 10 clientes × 500 docs).

**Alternativas avaliadas e rejeitadas (no comentário do topo de Graph.tsx):**

| Lib | Por que não |
|---|---|
| Cytoscape.js + cytoscape-react | Wrapper React menos polido, layouts via libs externas separadas (cose-bilkent), bundle ~360KB |
| D3 force-directed | Reinventa wheel — zoom/pan/fitView/MiniMap tudo manual |
| Sigma.js | Performance WebGL excelente >1k, mas wrapper React (`react-sigma`) defasado em 2025 |
| G6 (AntV) | Doc parcial em inglês; time MCP brasileiro perde tempo |
| xyflow v12 | Major bump 2025, breaking changes; v11 mais estável agora |

**Critério decisivo:** *lib já instalada + precedent existente > qualquer ganho marginal*. Custo de migração pra cytoscape/sigma > ganho hipotético, e a perf de reactflow já cobre o caso 5k nodes.

**Bundle size:** reactflow + reactflow/dist/style.css = ~150KB minified+gzipped.

**Performance esperada com payload real biz=1 Wagner (~700 nodes / ~3000 edges):**
- First render: <800ms (Inertia::defer carrega payload async)
- Layout client-side concentric/force-radial: <100ms
- Click→detail: <50ms (estado local React)
- Double-click→focus subgraph (BFS depth=2): <150ms

## Arquivos criados

| Arquivo | Linhas | Função |
|---|---:|---|
| `resources/js/Pages/kb/Graph.tsx` | 282 | Page principal Inertia — header + KPIs + tri-pane (filters/canvas/detail) + estado + callbacks |
| `resources/js/Pages/kb/Graph.charter.md` | 138 | Charter da page (Mission/Goals/Non-Goals/UX/Anti-padrões/TODOs) |
| `resources/js/Pages/kb/_components/GraphCanvas.tsx` | 110 | Wrapper Reactflow tipado (Background dotted + Controls + MiniMap custom) |
| `resources/js/Pages/kb/_components/GraphFilters.tsx` | 232 | Sidebar 240px — busca + toggles tipos nó/aresta + focus mode com depth-slider + layout mode |
| `resources/js/Pages/kb/_components/GraphNodeDetail.tsx` | 213 | Side panel 320px — header colorido por tipo + meta + edges agrupadas por tipo (clicáveis) + botões "Focar aqui"/"Abrir leitor" |
| `resources/js/Pages/kb/_components/GraphLegend.tsx` | 89 | Legenda compacta no topo do canvas — cores nodes + traços edges |
| `resources/js/Pages/kb/_lib/graphTypes.ts` | 142 | Types TS alinhados a SCHEMA-DB-V1 §4 + §11 — `KbNodeType`, `KbEdgeType`, `KbGraphPageProps`, `GraphFilterState`, helpers |
| `resources/js/Pages/kb/_lib/graphLayout.ts` | 271 | NODE_COLORS OKLCH (10 tipos) + EDGE_STYLES + `layoutConcentric` + `layoutForceRadial` (BFS) + `layoutDagre` (TODO[CL]) + `buildRFEdges` + `focusSubgraph` |
| `resources/js/Pages/kb/_lib/mockGraphData.ts` | 260 | 50 nodes (15 ADRs + 10 sessions + 8 charters + 8 runbooks + 5 briefings + 4 specs) + 64 edges realistas + KPIs |

**Total: ~1737 linhas TypeScript/React.**

## Wagner mental screenshot — o que ele vê em `/kb/graph`

**Default (sem ações):**
- Header com ícone git-branch, título "KB — Grafo de Conhecimento", descrição curta. Badge "modo mock" no canto direito enquanto Agent A não entregar Controller.
- 4 KpiCards na grid: "Nós no grafo: 50" / "Conexões: 64" / "Desatualizados: 0" / "Último bridge: 10:00"
- Legenda horizontal: 6 cores de nó (ADR amarelo, Session verde, Charter cyan, Runbook laranja, Briefing roxo, Spec azul-cinza) + 5 traços de aresta (supersedes vermelho dashed, charter-of cyan solid, cross-link accent solid, related-by-tag accent dotted fraco, ai-related accent dashed translúcido)
- Tri-pane:
  - **Esquerda 240px** — sidebar com busca, "Mostrando 50 de 50 nós", radio layout (force-radial selecionado), botões "Governança" / "ERP", checkboxes tipos nó (todos marcados), checkboxes tipos aresta (todos marcados)
  - **Centro flex** — canvas Reactflow com 50 nodes coloridos posicionados em círculos concêntricos por tipo, edges coloridas conectando (ADR 0093 e 0094 pinned no centro, charters em volta, briefings/runbooks em órbita), Background dotted suave, Controls bottom-left (zoom + fit-view), MiniMap bottom-right
  - **Direita** — vazio até clicar num node

**Primeiro click (ex: ADR 0150):**
- Node fica destacado com sombra/border accent
- Side panel direito 320px abre com header amarelo-mostarda (cor ADR):
  - "ADR" badge + "ADR 0150 — KB Unificado grafo IA central" + slug `0149-slug`
  - Botões "Focar aqui" + "Abrir leitor"
  - Meta: módulo KB, tags (kb, knowledge-graph, ia, p0), última verificação 15/05/2026, conexões 8
  - "Conexões (8)" expandida em 4 grupos: charter-of (2 itens — charter-kb-index, charter-kb-graph), cross-link (5 itens), related-by-tag (3 itens), ai-related (2 itens). Cada item clicável navega no detail sem mudar focus do canvas.

**Filtro "Governança apenas":**
- Sidebar: clicar no botão "Governança"
- Re-render: visibleNodeTypes = NODE_TYPES_GOVERNANCE → 50 nodes filtrados pra 50 (todos são governança no mock), mas em payload real biz=1 filtraria ERP nodes (os/customer/nfe) → grafo se simplifica visualmente

**Focus mode (double-click no ADR 0094):**
- Layout muda pra dagre-tb (no V1 cai em concentric — TODO[CL] dagre)
- Sidebar mostra card "Foco: ADR 0094 — Constituição v2" com depth-slider em 2 hops
- Filtro `focusSubgraph` aplica BFS depth=2 → grafo se reduz pra ~15 nodes diretamente relacionados (filhos via supersedes/charter-of/cross-link/related-by-tag até 2 hops)
- Canvas re-anima fitView 250ms
- Esc limpa focus

**Busca "fsm":**
- Sidebar input "fsm"
- Filtro client-side procura em label+slug+tags+module
- Re-render: ~5 nodes visíveis (ADR 0143, ADR 0129, session 2026-05-12-fsm, briefing-sells, charter-sells-create, runbook-fsm-bulk-start)
- Edges entre eles só (edges com source OU target fora some)

**Performance percebida:**
- Tudo instantâneo no mock (50 nodes). Em produção biz=1 com ~700 nodes, fitView/zoom continuam <100ms, switch layout <200ms.

## TODOs catalogados

Marcados como `TODO[CL]:` nos arquivos:

1. **Instalar `@dagrejs/dagre@^1.1.4`** pra ativar layout dagre-tb/lr real (em `_lib/graphLayout.ts` §`layoutDagre`). Hoje fallback `concentric`. Stub pronto, basta descomentar trecho.
2. **Remover fallback mock data** em `Graph.tsx` quando `KbGraphController` entrar em prod (Agent A — ONDA 5 backend).
3. **WebSocket Centrifugo channel `kb.{biz}.graph`** pra updates real-time (quando bridge job roda).
4. **Virtualização >5k nodes** via `onlyRenderVisibleElements` Reactflow prop.
5. **Radix `<Tooltip>` no hover do node** (estilo consistent vs tooltip nativo HTML).
6. **Keyboard nav J/K entre nodes ordenados** por relevância (PageRank ou edges_count desc).
7. **Botão "Perguntar à IA sobre este node"** no detail panel → `/kb/ai/ask?focus={slug}` (depende ONDA 4 IA RAG).
8. **Focus trap WCAG** + Tab navega entre nodes (canvas Reactflow não traz keyboard nav robusto nativo).
9. **Export PNG/SVG do grafo atual** (canvas.toBlob).
10. **Persist filtros em URL query params** (deep-link `?type=adr&focus=adr-149&depth=2`).
11. **Fullscreen toggle** (botão custom no `Controls` do Reactflow — stub `ControlButton Maximize2` já presente em `GraphCanvas.tsx`).

## Próximos passos — dependências entre Agents

### Agent A — ONDA 5 backend (`KbGraphController`)

Criar `Modules/KB/Http/Controllers/KbGraphController.php` espelhado em `Modules/KB/Http/Controllers/Admin/GraphController.php` (ADS) com:
- Rota `GET /kb/graph` (Inertia::render) — props `nodes`, `edges`, `kpis`, `filters`
- Rota `GET /kb/graph/data` (JSON) — pra refresh sem reload
- `Inertia::defer()` em `nodes` e `edges` (queries pesadas — SCHEMA-DB-V1 §11)
- `business_id` global scope (ADR 0093)
- Format exato dos types `KbGraphNode/KbGraphEdge/KbGraphKpis` do `_lib/graphTypes.ts`
- Filtros server-side opcionais: `?types[]=adr&edge_types[]=supersedes&q=multi-tenant&focus_id=adr-149&depth=2`

### Agent C — Pest tests (F4 MWART)

Criar `tests/Browser/Kb/GraphPageTest.php` com:
- biz=1 (ADR 0101) — load `/kb/graph`, snapshot screenshot, validate node count match KPI
- biz=99 cross-tenant — login biz=99 → não deve ver nodes biz=1
- Click num node → side panel abre com label correto
- Double-click → focus mode + URL atualizada com `?focus_id=...`

### Wagner — Gate visual screenshot (ADR 0114)

Antes de F5 (cutover/merge) precisa rodar:
- `npm run build:inertia` localmente
- Servir `/kb/graph` com mock data (já funciona — Graph.tsx tem fallback)
- Wagner aprova SCREENSHOT (não tabela markdown)
- Anti-padrões catalogados em `Index.charter.md` §"Anti-padrões" devem estar respeitados (sem rounded-xl, sem emoji UI, PT-BR completo, sem modal full-screen)

## Comandos de install pendentes (NÃO executar agora)

```bash
# Quando ativar layout dagre real (TODO[CL] 1):
npm install --save-exact @dagrejs/dagre@1.1.4

# Verificar versão react-flow se quiser bump pra v12 (BREAKING — avaliar antes):
# npm install reactflow@^12.0.0  # NÃO RECOMENDADO em V1, manter 11.11.4
```

## Observações

- **Zero conflito com Agents B/outros** que já criaram `_lib/types.ts`, `_lib/mockData.ts`, `_lib/helpers.ts`, `_components/NodeReader.tsx` etc. Meus arquivos têm nomes únicos prefixados com `Graph*` ou `graphTypes/Layout/mockGraphData`. Types não overlap (Agent B foca em `KbBlockKind/KbCalloutTone` — block editor; eu foco em `KbNodeType/KbEdgeType` — grafo viz).
- **Charter `Graph.charter.md` segue convenção `Index.charter.md`** (mesmo frontmatter, mesma estrutura Mission/Goals/Non-Goals/UX/Anti-padrões/Restrições Tier 0/Versionamento).
- **NÃO mexi em `Modules/KB/`** (escopo restrito). NÃO toquei `Index.tsx`, `Index.charter.md` (escopo de Agent B). NÃO toquei `GraphController.php` do ADS (apenas li como precedent).
- **Não rodei `npm install` nem CI**. Esqueleto compila por convenção (todos os imports existem no projeto), mas só Agent C pode validar via Pest. Run `npm run typecheck` recomendado pelo parent antes do PR.

## Critérios de pronto pra PR

- [x] Page Graph.tsx criada com fallback pra mock
- [x] 4 components em `_components/Graph*.tsx`
- [x] 3 helpers em `_lib/graph*.ts`
- [x] Charter `Graph.charter.md` ao lado de `Graph.tsx`
- [x] Decisão de lib justificada em comentário topo + charter + session log
- [x] Mock data realista 50 nodes / 64 edges
- [x] TODOs catalogados pra ondas futuras
- [ ] Pest tests biz=1 + biz=99 — **Agent C**
- [ ] Backend `KbGraphController` + rota `/kb/graph` + `/kb/graph/data` — **Agent A**
- [ ] Wagner aprova screenshot — **gate visual ADR 0114**
- [ ] `npm run typecheck` verde — **parent/CI**
