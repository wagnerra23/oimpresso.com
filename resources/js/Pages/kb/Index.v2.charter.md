---
page: /kb/v2
component: resources/js/Pages/kb/Index.v2.tsx
controller: Modules\KB\Http\Controllers\KbController@indexV2
route: kb.v2
status: draft
owner: wagner
persona_principal: Wagner / governança (1440px desktop)
persona_secundaria: Larissa / operacional gráfica (1280px balcão, ONDA 6+)
charter_version: 1.0
charter_at: 2026-05-16
related_adrs:
  - 0150-kb-unificado-grafo-conhecimento-modulo-ia-central # proposta
  - 0039-ui-chat-cockpit-padrao
  - 0104-processo-mwart-canonico-unico-caminho
  - 0107-emendation-0104-visual-comparison-gate-f3
  - 0114-prototipo-ui-cowork-loop-formalizado
related_briefing: ../../../memory/requisitos/KB/BRIEFING.md
related_schema: ../../../memory/requisitos/KB/SCHEMA-DB-V1.md
related_prototipo: ../../../prototipo-ui/prototipos/kb/
mwart_pattern_reuse:
  blueprint_cowork: prototipo-ui/prototipos/kb/kb-page.jsx
  blueprint_screenshot_approval: pendente (gate F1.5)
  derived_screens: [Index.v2]
  divergence_from_blueprint: "tri-pane sidebar+lista+leitor (port direto JSX→TSX)"
---

# Charter — `resources/js/Pages/kb/Index.v2.tsx` (DRAFT)

> Coexiste com `Index.tsx` (V3 atual) durante gate visual ADR 0114. Rota `/kb/v2` paralela até Wagner aprovar SCREENSHOT pro cutover.

## Mission

Port do protótipo Cowork `prototipo-ui/prototipos/kb/kb-page.jsx` pra Inertia React 19 + TS estrito + AppShellV2 + tokens OKLCH hue 240. Visualizar KB Unificado em layout **tri-pane** (sidebar categorias + lista nós + leitor markdown) com command palette ⌘K, troubleshooter dialog, paths dialog, e health panel.

## Goals (faz)

1. Tri-pane responsivo 1280px (Larissa balcão) sem scroll horizontal
2. CategorySidebar com contagem por categoria/subcategoria (fallback MOCK quando backend ausente)
3. NodeList ordenada por `pinned` + `updated_at` desc, click abre NodeReader sem reload
4. NodeReader render markdown com cross-link `#kb-XXX` + anchors + blocos de imagem
5. CommandPalette ⌘K fuzzy local (200 primeiros nós) + fallback IA
6. PathsDialog + TroubleshooterDialog + HealthPanel acionáveis pela header
7. Fallback MOCK_NODES quando rotas backend ausentes (ONDA 1 pendente)
8. Persistência localStorage prefix `oimpresso.kb.v2.*`

## Non-Goals (NÃO faz)

> Wagner aprova lista. Cada item vira Pest GUARD.

- Edição inline de body (drawer-only — vai pra Node.charter.md)
- CRUD de paths/trees (vai pra Paths/Troubleshooter charters)
- Substitui Index.tsx (V3 atual) — paralelo até cutover
- Carrega 1000+ nós sem virtualização (ONDA 6 fase 2)
- Sincronização tempo-real Centrifugo (ONDA 7)

## UX Targets

- First-paint < 1200ms (KPIs + 50 linhas)
- Render markdown < 200ms por nó
- 0 erros JS console
- 1280px sem scroll horizontal
- Cores semânticas Cockpit V2 (NÃO `bg-(red|green)-N` cru)
- Tipografia canon ADR 0110

## UX Anti-patterns

- Modal pra detalhe (canon = pane direito tri-pane)
- Tabs `border-b-2` em filter (canon = pills `rounded-full`)
- `sessionStorage` (canon = `localStorage` prefixed)
- Cor crua hardcoded sem semantic token
- Carregamento síncrono de body markdown na lista

## Automation Hooks

- `GET /kb/v2` — `KbController@indexV2` (rota pendente Agent A ONDA 1)
- `GET /kb/nodes` — `KbNodeController@index` (paginação cursor)
- `GET /kb/nodes/{slug}` — `KbNodeController@show` (incrementa reads_count atomic)
- `GET /kb/paths` — `KbPathController@index`
- `GET /kb/decision-trees` — `KbDecisionTreeController@index`
- `Inertia::defer` em props pesadas (nodes, paths, trees) — RUNBOOK-inertia-defer-pattern

## Automation Anti-hooks

> Wagner aprova lista. Vira Pest GUARD.

- NÃO envia emails/SMS/WhatsApp ao abrir
- NÃO escreve no DB no render (read-only — `reads_count++` é via show endpoint, não Inertia::render)
- NÃO dispara Jobs ao abrir
- NÃO chama Brain B/Sonnet (IA RAG fica em ação explícita "Perguntar ao KB")
- NÃO acessa nodes de outro `business_id` (multi-tenant Tier 0 — ADR 0093)
- NÃO loga PII em audit (sanitizer obrigatório)

## Métricas vivas (Pest GUARD pendente)

```php
it('renders /kb/v2 in <1200ms p95 with 50 nodes')
it('does not dispatch jobs on render')
it('does not mutate state on GET')
it('isolates nodes by business_id (biz=1 vs biz=99)')
it('renders at 1280px without horizontal scroll')
it('uses localStorage prefix oimpresso.kb.v2.* (never sessionStorage)')
it('falls back to MOCK_NODES when backend route missing')
it('keyboard ⌘K opens CommandPalette / opens search')
```

## Comparáveis canônicos (`mwart-comparative` V4)

- **Notion** (tri-pane navegação + leitor) — referência principal layout
- **Obsidian** (graph view + cross-link `#`) — referência cross-link UX
- **Linear** (command palette ⌘K densidade) — referência atalhos
- **Excluir:** Confluence (overhead enterprise), Wiki.js (sem palette), Outline (sem graph)

## Refs

- Blueprint Cowork: `prototipo-ui/prototipos/kb/kb-page.jsx`
- Charter v1: [`Index.charter.md`](Index.charter.md) (V3 atual)
- [ADR 0110 — Cockpit Pattern V2](../../../../memory/decisions/0110-cockpit-pattern-v2-canon-list-detail.md)
- [ADR 0114 — Loop Cowork formalizado](../../../../memory/decisions/0114-prototipo-ui-cowork-loop-formalizado.md)
- [SCHEMA-DB-V1 §11](../../../../memory/requisitos/KB/SCHEMA-DB-V1.md)
- Backend: `Modules/KB/Http/Controllers/KbController.php` (indexV2 pendente)

## Histórico

| Data | Autor | Mudança |
|---|---|---|
| 2026-05-16 | Wave J | Charter draft criado pra Index.v2.tsx (port Cowork). Pendente aprovação Wagner em Non-Goals + Anti-hooks pra `status: live`. |
