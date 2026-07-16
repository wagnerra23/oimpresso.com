---
page: /ads/admin/conflicts
component: resources/js/Pages/ads/Admin/Conflicts.tsx
related_prototype: n/a (herda PT-04 Dashboard; segue o Padrão de Tela)
owner: wagner
status: draft
last_validated: "2026-07-11"
parent_module: ADS
related_adrs: [114, 101, 93]
tier: B
charter_version: 1
---

# Page Charter — /ads/admin/conflicts (DRAFT)

> **Status:** draft criado em 2026-07-11 no lote de cobertura de charters. Wagner aprova **Non-Goals + Anti-hooks** ANTES de virar `status: live`.
>
> Backend: `Modules/ADS/Http/Controllers/Admin/ConflictsController@index` (rota `ads.admin.conflicts.index`, middleware `auth` — V1 superadmin). Escopa por `business_id` da sessão (default 1). Usa `Inertia::defer` pra 4 payloads pesados (3 detectores + KPIs) sobre `mcp_dual_brain_decisions` (janela 7 dias) + `PatternLearningService`.

---

## Mission
Painel de detecção automática de conflitos do ADS (Cognitive Control Panel #5): sem ele o sistema quebraria silenciosamente. Detecta 3 tipos de discrepância — file lock (2 decisões tocando o mesmo arquivo em <1h), drift de padrão (taxa recente caiu >25pp) e humano × IA (Wagner aprovou mas ReviewerAgent deu nota <50) — pra Wagner investigar antes que virem incidente.

---

## Goals — Features (faz)
- 4 KPIs de topo: total de conflitos (7 dias), file lock, drift, humano × IA.
- Seção File lock: pares de decisões concorrentes com gap em minutos + links pra `/ads/admin/decisoes/{id}` + recomendação.
- Seção Drift: par domínio × event_type com taxa histórica → recente + amostras + recomendação.
- Seção Humano × IA: decisão onde Wagner aprovou mas review_score <50, com issues apontadas pela IA + link de detalhe.
- EmptyState "nenhum conflito detectado" quando total = 0.
- Skeleton via `<Deferred>` enquanto os detectores resolvem (props chegam undefined no first render).

---

## Non-Goals — Features (NÃO faz)
- ❌ NÃO resolve conflitos automaticamente — só detecta e recomenda; a ação humana acontece na tela de decisões.
- ❌ NÃO edita nem descarta um conflito pela UI (sem estado persistido de "conflito revisado").
- ❌ NÃO cruza dados entre businesses — cada detector escopa `where('business_id', $businessId)` da sessão.
- ❌ NÃO alerta ativamente (sem push/e-mail) — a tela precisa ser aberta pra ver os conflitos.

---

## UX targets
- p95 < 1500ms (admin) ; cabe em 1280px (ROTA LIVRE) ; AppShellV2 ; skeleton evita TTFB alto dos 3 detectores.

---

## Automation hooks (faz)
- `Inertia::defer` dispara os 3 detectores + KPIs sob demanda (partial reload), fora do initial render.
- Detecção roda a cada carregamento sobre a janela de 7 dias — recomputa file_lock/drift/human-ai on-load.

---

## Anti-hooks (NÃO faz automaticamente)
- ❌ Sem polling / auto-refresh — não recarrega sozinho depois de aberto.
- ❌ Sem auto-resolução, auto-reject ou auto-rollback de nenhum conflito.
- ❌ Sem mutação em GET.

---

## Pendências antes de `status: live`
- [ ] Wagner aprova Non-Goals + Anti-hooks
- [ ] Smoke visual 1280/1440 (screenshot)
- [ ] Confirmar se conflitos deveriam gerar alerta (task/inbox) além do painel passivo
