---
page: /admin/screen-review/dashboard
component: resources/js/Pages/Admin/ScreenReviewDashboard.tsx
page_id: admin-screen-review-dashboard
route: /admin/screen-review/dashboard
status: live
owner: wagner
adrs: [0104, 0122, 0160]
related_pages: [admin.screen-review, admin.governance.v4]
created: 2026-05-17
---

# Charter — Screen Review · Dashboard

## Mission

Visão executiva (read-only) do estado PDCA das 201+ telas Inertia/React do
oimpresso. Wagner abre essa tela em 2 segundos pra saber se vale entrar na
operação (tri-pane `/admin/screen-review`) ou se está tudo em dia.

## Goals

- 5 KPIs PDCA em 1 viewport sem scroll (Total / Pendentes / Aprovadas /
  Iteração / Rejeitadas)
- Alerta visual quando pending_over_7d > 0 (Wagner deixando bola passar)
- Nav clara pra tri-pane operacional (`/admin/screen-review`) via header
- Tempo render < 200ms (sem queries pesadas — só meta agregada do controller)

## Non-Goals

- Listar telas (isso é tri-pane)
- Mostrar charters/screenshots
- Editar status (Wagner faz na tri-pane)

## UX targets

- Header com PageHeader canon (icon `layout-dashboard`)
- Actions header: `Reload` (ghost) + `Screen Review` (default — primária)
- KpiGrid cols={5} responsivo (mobile empilha)
- Drift alert amber só quando triggered
- Cores tone semânticas (success/warning/info/danger/default) — sem hex cru

## Anti-hooks

- ⛔ NÃO duplicar lista de screens do `ScreenReview.tsx`
- ⛔ NÃO chamar `Inertia::defer` no controller dashboard — payload trivial
- ⛔ NÃO adicionar breadcrumb topnav (Wagner removeu intencionalmente 2026-05-17)
- ⛔ NÃO criar atalhos de teclado nessa tela (atalhos vivem na tri-pane)

## Related

- `ScreenReview.tsx` — tri-pane operacional (origem do split)
- `ScreenReviewController::dashboard()` — endpoint
- `GovernanceV4.tsx` — mesmo padrão tri-pane (não dashboard split)
