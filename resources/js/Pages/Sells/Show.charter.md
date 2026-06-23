---
page: /sells/{id}
component: resources/js/Pages/Sells/Show.tsx
owner: wagner
status: draft
last_validated: "2026-05-15"
parent_module: Sells
related_adrs: [104, 107, 110, 143, 149, 93]
tier: A
charter_version: 1
mwart_pattern_reuse:
  blueprint_cowork: "prototipo-ui/cowork/vendas-page.jsx"
  blueprint_screenshot_approval: "SYNC_LOG pendente verificar Wagner (ADR 0149)"
  derived_screens: [Show]
  divergence_from_blueprint: "Show é full-page detail (não drawer SaleSheet). Layout 2-col 8/4 espelha pattern Cockpit + sidebar FSM."
---

# Page Charter — /sells/{id}

> **Status:** wave1-draft. Migração MWART Wave 1 W1-A (2026-05-15).
> Reusa pattern Cockpit V2 ADR 0110 via ADR 0149 (screen-pattern reuse).

---

## Mission

Mostrar detalhe completo de uma venda — linhas, pagamentos, frete, atividades — em página full-page Inertia com pattern visual derivado de SaleSheet drawer (Index canon). Substitui `sale_pos.show.blade.php` legacy modal-grande.

---

## Goals — Features (faz)

- AppShellV2 + breadcrumb implicit via PageHeader pattern
- Header h1 24px "Venda #{invoice_no}" + data + location subtitulo
- 4 KPIs grandes (canon V2): Total / Pago / Falta / Status pgto
- Layout 2-col: 8/12 esquerda (cliente + linhas + pagamentos + frete + histórico) + 4/12 direita (FSM panel + atalhos)
- Detail prop deferred via `Inertia::defer()` (RUNBOOK-inertia-defer-pattern Tier 0)
- `<Deferred data="detail" fallback={<DetailSkeleton/>}>` wrap frontend
- Tabela linhas zebra-strip leve com tabular-nums em valores
- Cards seção `bg-card border-border rounded-lg p-5`
- Atalhos: `E` (edit), `P` (print), `Esc` (voltar /sells)
- Multi-tenant Tier 0: scope `business_id` no controller (ADR 0093)
- Permission gate: `sell.view` OR `direct_sell.access` OR `view_own_sell_only`

---

## Non-Goals — Features (NÃO faz)

- ❌ Edição inline (vai pra `/sells/{id}/edit`)
- ❌ Mudança de stage FSM direto (`current_stage_id` é trait-protected ADR 0143 — vai pelo `FsmActionPanel` futuro)
- ❌ Print inline (window.open `/sells/{id}/print` legacy)
- ❌ Drawer SaleSheet (reservado pro /sells Index)
- ❌ Bulk actions (não aplicável a 1 venda)
- ❌ Real-time updates

---

## UX Targets

- p95 first-paint (headline) < 800ms
- p95 detail render (deferred) < 1500ms
- 0 erros JS console
- Cabe em monitor 1280px sem scroll horizontal (Larissa)
- 4 KPIs grandes legíveis
- Tipografia canon: h1 24px font-semibold, label KPI 11px uppercase, valor KPI 36px (large)

---

## UX Anti-patterns

- ❌ Modal Bootstrap legacy
- ❌ DataTables jQuery
- ❌ Cor crua `bg-blue-500`
- ❌ `font-bold` em h1
- ❌ AppShell sem V2
- ❌ Tabs `border-b-2`
- ❌ 8 with() eager-load síncrono (defer obrigatório)

---

## Endpoints alimentadores

| Método | Rota | Retorna |
|---|---|---|
| GET | `/sells/{id}` (X-Inertia) | Inertia render Sells/Show (headline eager + detail deferred) |
| GET | `/sells/{id}` (sem X-Inertia) | Blade legacy `sale_pos.show` (fallback) |
| GET | `/sells/{id}/sheet-data` | drawer JSON (reuso futuro) |
| GET | `/sells/{id}/print` | Blade legacy print |

---

## Tests anti-regressão

- [tests/Feature/Sells/Wave1ShowBaselineTest.php](../../../../tests/Feature/Sells/Wave1ShowBaselineTest.php) — 8 estruturais (baseline F2)
- [tests/Feature/Sells/Wave1ShowInertiaTest.php](../../../../tests/Feature/Sells/Wave1ShowInertiaTest.php) — Inertia render + cross-tenant Tier 0

---

## Cutover plan (parent agent executa)

- Smoke biz=1 ROTA LIVRE: `/sells/{id}` com X-Inertia header → confirma visual + dados
- Canary 7d antes de remover Blade legacy `resources/views/sale_pos/show.blade.php`
- Comunicação Larissa via WhatsApp pré-cutover (ROTA LIVRE 99% volume)
- Rollback: NÃO requer flag, basta NÃO mandar X-Inertia (Blade fallback default)

---

## Refs

- [ADR 0149](../../../../memory/decisions/0149-mwart-screen-pattern-reuse-cowork.md)
- [ADR 0143 FSM Pipeline](../../../../memory/decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md)
- [RUNBOOK-show.md](../../../../memory/requisitos/Sells/RUNBOOK-show.md)
- [show-visual-comparison.md](../../../../memory/requisitos/Sells/show-visual-comparison.md)
- Blueprint Cowork: `prototipo-ui/cowork/vendas-page.jsx`
- Parent visual: `resources/js/Pages/Sells/Index.charter.md`

## UCs cobertos (PRECISA TER · rastreável · §10.4 [CC])

> Casos de Uso ("A tela precisa:") amarrados a GUARD Pest `uc-<id>` via [`prototipo-ui/audit/uc-registry.json`](../../../../prototipo-ui/audit/uc-registry.json).
> ✅ presente+travado · 🟡 gap (acende no `protocol_freshness`). Show é `wave1-draft` — os UCs de gestão pós-venda ainda são gaps.

- 🟡 **UC-V04** — estado "Aguardando aprovação" visível + registro da aprovação do cliente. _(sem cobertura)_
- 🟡 **UC-V05** — campo de transportadora/rastreio, foto de entrega, confirmação de recebimento, tentativas frustradas. _(sem cobertura)_
- 🟡 **UC-V06** — seleção de tipo de NF, forma de pagamento c/ parcelas, integração fiscal, baixa de estoque/insumos. _(sem cobertura)_
- 🟡 **UC-V07** — histórico de pedidos no perfil, filtro por período/estado/tipo, repetir pedido, ticket médio/frequência. _(sem cobertura)_
