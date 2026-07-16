---
page: /ads/admin/metricas
component: resources/js/Pages/ads/Admin/Metricas.tsx
related_prototype: n/a (herda PT-04 Dashboard; segue o Padrão de Tela)
owner: wagner
status: draft
last_validated: "2026-07-11"
parent_module: ADS
related_adrs: [114, 101, 93]
tier: B
charter_version: 1
---

# Page Charter — /ads/admin/metricas (DRAFT)

> **Status:** draft criado em 2026-07-11 no lote de cobertura de charters. Wagner aprova **Non-Goals + Anti-hooks** ANTES de virar `status: live`.
>
> Backend: `Modules/ADS/Http/Controllers/Admin/MetricasController@index` (rota `ads.admin.metricas.index`, middleware `auth` — V1 superadmin). Escopa `where('business_id', $businessId)` da sessão. `Inertia::defer` pra `kpis` (10 aggregations), `distribuicao`, `por_dominio` e `por_event_type` (GROUP BY top 10) sobre `mcp_dual_brain_decisions`.

---

## Mission
Dashboard de adoção e custo do Adaptive Decision System: quanto o sistema decide sozinho (autonomia), quanto escala pra humano, quanto o firewall bloqueia, e o custo em USD/tokens do Brain B. É o painel executivo que Wagner usa pra medir se o ADS está pegando tração e a que preço.

---

## Goals — Features (faz)
- 4 KPIs primários: total de decisões (+30d), taxa de autonomia (Brain A), taxa que exige humano, % bloqueado por firewall.
- Barra empilhada "Distribuição por destino" (Brain A / Brain B / bloqueado / pendente) com legenda e %.
- 4 KPIs secundários: aprovadas+executadas, aprovadas com modificação (peso 3× no aprendizado), rejeitadas, custo Brain B em USD (+ tokens).
- Top 10 domínios e Top 10 tipos de evento mais frequentes (barras proporcionais).
- EmptyState quando total=0 (dica de checar o daemon ads-brain-a no CT 100); skeleton via `<Deferred>`.

---

## Non-Goals — Features (NÃO faz)
- ❌ NÃO filtra por período customizado nem por domínio na UI (janelas fixas: total, 30d).
- ❌ NÃO exporta CSV/relatório nem agenda envio.
- ❌ NÃO cruza businesses — todas as métricas escopadas por business_id da sessão.
- ❌ NÃO é fonte de verdade financeira — custo_usd é telemetria do Brain B, não contabilidade.

---

## UX targets
- p95 < 1500ms (admin) ; cabe em 1280px (ROTA LIVRE) ; AppShellV2 ; skeleton evita bloquear no peso das 10 aggregations.

---

## Automation hooks (faz)
- `Inertia::defer` dispara os 4 blocos de aggregation sob demanda (partial reload), fora do initial render.

---

## Anti-hooks (NÃO faz automaticamente)
- ❌ Sem polling / auto-refresh — métricas só atualizam no reload manual.
- ❌ Sem mutação em GET — tela 100% read-only.

---

## Pendências antes de `status: live`
- [ ] Wagner aprova Non-Goals + Anti-hooks
- [ ] Smoke visual 1280/1440 (screenshot) — cobrir estado com dados e EmptyState
- [ ] Confirmar se falta filtro de período (30d/90d/custom) pro uso real
