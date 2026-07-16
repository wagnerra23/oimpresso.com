---
page: /ads/admin/graph
component: resources/js/Pages/ads/Admin/Graph.tsx
related_prototype: n/a (visualização de grafo bespoke ReactFlow — não segue um dos 5 Padrões de Tela)
owner: wagner
status: draft
last_validated: "2026-07-11"
parent_module: ADS
related_adrs: [114, 101, 93]
tier: B
charter_version: 1
---

# Page Charter — /ads/admin/graph (DRAFT)

> **Status:** draft criado em 2026-07-11 no lote de cobertura de charters. Wagner aprova **Non-Goals + Anti-hooks** ANTES de virar `status: live`.
>
> Backend: `Modules/KB/Http/Controllers/Admin/GraphController@index` (rota `ads.admin.graph.index`, middleware `auth` — V1 superadmin). Renderiza a Page `ads/Admin/Graph` mas o controller vive em `Modules/KB`. Escopa `where('business_id', $businessId)` da sessão em `mcp_decision_patterns` + `mcp_memory_documents`; junta meta-skills, tools e policy. Span OTel `kb.graph.index`.
>
> Silêncio de PT é honesto: o dominante é um canvas ReactFlow (grafo interativo), não um dos 5 Padrões. O `KpiGrid` presente é só uma faixa-cabeçalho; declarar PT-04 mascararia a natureza bespoke.

---

## Mission
Cognitive Control Panel #3: dar a Wagner um mapa visual das relações do cérebro do ADS — Memory (centro) ↔ Skills ↔ Meta-skills ↔ Tools ↔ Policy. Cada nó carrega dado real (taxa de sucesso, triggers, read-only, count) e as arestas mostram derived_from / promotes_to / archives, pra entender de relance como o sistema aprende e governa.

---

## Goals — Features (faz)
- 4 KPIs de topo: docs em Memory, skills (top 15 por uso), meta-skills, tools.
- Canvas ReactFlow com layout determinístico (memory no centro, círculos concêntricos), nós arrastáveis, zoom scroll/pinch, Background, Controls e MiniMap.
- Cor por tipo de nó via tokens DS (CSS vars — memory=primary roxo 295), nunca hex cru; legenda no header do card.
- Arestas com marcador de seta + label (derived_from, promotes_to, archives) e animação pra promotion.

---

## Non-Goals — Features (NÃO faz)
- ❌ NÃO persiste posição dos nós — arrastar é só visual, não salva layout.
- ❌ NÃO edita skills/meta-skills/policy pelo grafo (é read-only; edição é nas telas próprias).
- ❌ NÃO cruza dados entre businesses — patterns e memory docs escopados por business_id da sessão.
- ❌ NÃO usa `Inertia::defer` ainda (nós montados em useMemo no top-level; rollback PR #963 documenta regressão de defer aqui).

---

## UX targets
- p95 < 1500ms (admin) ; cabe em 1280px (ROTA LIVRE) ; AppShellV2 ; canvas 700px de altura com fitView.

---

## Automation hooks (faz)
- Layout de nós recomputado via `useMemo` sobre nodes/edges; span OTel envolve a montagem (correlaciona spike de patterns/skills com latência).

---

## Anti-hooks (NÃO faz automaticamente)
- ❌ Sem polling / auto-refresh do grafo.
- ❌ Arrastar nó não dispara escrita nem persiste (posição efêmera).
- ❌ Sem mutação em GET.

---

## Pendências antes de `status: live`
- [ ] Wagner aprova Non-Goals + Anti-hooks
- [ ] Smoke visual 1280/1440 (screenshot) — validar legibilidade do grafo em 1280px
- [ ] Decidir se vale migrar pra `Inertia::defer` (peso das 5 fontes) sem reincidir na regressão #963
