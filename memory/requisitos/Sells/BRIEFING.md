# Sells — BRIEFING (estado consolidado)

> Mini-pointer canônico (pré-briefing completo). Atualizado por PR (skill `brief-update` Tier B).
> Doc mãe: [SPEC.md](SPEC.md) · [UI-CATALOG.md](UI-CATALOG.md) · [OBSERVABILITY.md](OBSERVABILITY.md)
> Última atualização: 2026-05-25 (Wave Z-2 — Integração Vendas × Oficina mergeada em main)

## O que é

Módulo de vendas — wrapper KB-9.75 sobre `Transaction(type='sell')` UltimatePOS legacy. Páginas Inertia/React: Create · Edit · Index · Drafts · Quotations · Show · Subscriptions · Caixa (Wave Z-2).

## Capacidade canônica

- **Sells/Index** (`A+ · 9,75/10` KB-9.75 v2 · PR #1064) — lista vendas com KPI hero, saved tree, filtros, hover-reveal actions
- **Sells/Create** (`A+ · 9,75/10`) — checkout balcão com search produto + cliente + payment
- **Veículo na venda** ([ADR 0251](../../decisions/0251-veiculo-na-venda-direta-oficina.md) · 2026-06-05) — venda direta de oficina seleciona/cadastra o veículo do cliente sem abrir OS: seletor com plaquinha Mercosul (reusada do OficinaAuto) + `QuickAddVehicleSheet` (cadastro rápido sem perder a venda) na `Sells/Create`, placa na consulta (Index + Show). Schema `transactions.vehicle_id` nullable + FK. **Gated per-business** (vestuário/ROTA LIVRE não vê)
- **Sells/Edit** — edição venda existente + Wave Z-2 `commission_split` editor (2 selects mecânico/balcão + 2 inputs % · validation total=100 server-side)
- **Sells/Quotations / Drafts / Show / Subscriptions** — variações da entidade `Transaction`
- **Integração Vendas × Oficina** ([ADR 0192](../../decisions/0192-auto-faturar-os-venda-jobsheet-observer.md) · Wave Z-2 mergeada 2026-05-25):
  - Coluna **Origem** em Sells/Index (pill Balcão/Oficina/Online + cross-link `↗ #OS-NNNN`)
  - Saved tree branch **"Por origem"** filtra por source
  - KPI hero breakdown **Balcão R$ X · Oficina R$ Y · Online R$ Z** quando Foco=Faturamento
  - Listener `oimpresso:open-venda` cross-módulo abre drawer/sheet
  - Schema: `transactions.source ENUM` + `os_ref VARCHAR(20)` + `commission_split JSON` + `cancelled_at TIMESTAMP`
- **Sells/Caixa** (rota nova `/vendas/caixa` · Wave Z-2 W1 PR #1513) — caixa do dia com seção "Por origem" (barras de progresso + drill-down) · coexiste com Index legacy
- **Botão Compartilhar** (W3 PR #1508) — Web Share API nativa mobile + clipboard fallback desktop

## Stack arquitetural

- `app/Http/Controllers/SellController.php` — wrapper KB-9.75 + payload `/sells-list-json` expand items_list + fiscal (W2 PR #1510)
- Sells (núcleo UltimatePOS, não é módulo) — thin layer (rota + payload + service)
- `app/Transaction.php` model legacy UPOS — multi-tenant `business_id` global scope
- Integração com `Modules/Repair`:
  - `JobSheetObserver@updated` auto-cria `Transaction(source='oficina')` em terminal transition
  - Reverse hook (`cancelled_at`) cancela Transaction se OS reaberta
  - Idempotência por `(business_id, os_ref)`

## Clientes piloto

- **biz=1 (Wagner WR2 Sistemas)** — sandbox dev + canary Wave Z-2 deploy 2026-05-25 ([smoke checklist 8 blocos](../../sessions/2026-05-25-wave-z2-smoke-checklist.md))
- **biz=4 (Larissa ROTA LIVRE vestuário)** — habilitar após 7 dias canary biz=1 verde

## ROI / próximos passos

| Gap | Próximo PR | Owner |
|---|---|---|
| Smoke prod biz=1 8 blocos verde | Wave Z-2 SSH | Wagner |
| Canary 7 dias biz=1 → biz=4 Larissa | Pós-canary | Wagner |
| Dashboard `/relatorios/vendas-origem` | Backlog | Claude |
| Reverse Tx UI ("Restaurar venda cancelled") | Backlog | Claude |
| Performance Observer >50ms p95 → mover pra Job | Review trigger ADR 0192 | Claude |

## ADRs canônicos

- [0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) Multi-tenant Tier 0 IRREVOGÁVEL
- [0192](../../decisions/0192-auto-faturar-os-venda-jobsheet-observer.md) Auto-faturar OS→Venda via JobSheetObserver (Wave Z-2 marco 2026-05-25)
- [0178](../../decisions/0178-sells-unified-tabs-visao-supersede-0136.md) Sells unified tabs (supersede 0136)
- [0114](../../decisions/0114-prototipo-ui-cowork-loop-formalizado.md) Loop Cowork ↔ Claude Code formalizado
