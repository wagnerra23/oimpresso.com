---
date: 2026-05-19
authors: [W, CL]
topic: F3 PaymentGateway UI completa + Onda 4d.5 wire-up + Onda 4d.6 cobrarCartao + 3 hotfixes prod
related_adrs:
  - 0144-paymentgateway-extracao-camada-cobranca
  - 0170-paymentgateway-module-extraction
  - 0093-multi-tenant-isolation-tier-0
  - 0104-processo-mwart-canonico-unico-caminho
duration_hours: 4
prs_merged:
  - 1135
  - 1139
  - 1140
  - 1141
  - 1142
  - 1144
---

# F3 PaymentGateway UI completa — sessão 2026-05-19

## Resumo executivo

Sessão entregou ADR 0170 **Ondas 4d.2 → 4d.6 completas** em prod biz=WR2:

- **3 telas Inertia/React** port do Cowork F1.5 (score 96/93/93)
- **Wire-up emissão real** (POST /financeiro/cobranca/emitir + /sells/{id}/emitir-cobranca)
- **cobrarCartao + CardToken** endpoint dedicado (PCI-DSS SAQ-A)
- **Sidebar refactor arquitetural** — grupo via DataController (não hardcode frontend)
- **Cleanup Pages/Boletos legacy** + redirect 301
- **6 PRs mergeados** em ~4h

## PRs

| PR | Onda | Conteúdo | Linhas |
|---|---|---|---|
| [#1135](https://github.com/wagnerra23/oimpresso.com/pull/1135) | 4d.2-4d.4 | F3 UI 3 telas (Cobrança + Settings/PaymentGateways + Sells chip) | +8592 |
| [#1139](https://github.com/wagnerra23/oimpresso.com/pull/1139) | 4d.5 | Wire-up emissão real PaymentGatewayContract::emitirX() | +442 |
| [#1140](https://github.com/wagnerra23/oimpresso.com/pull/1140) | 4d.6 | cobrarCartao + CardToken endpoint dedicado | +143 |
| [#1141](https://github.com/wagnerra23/oimpresso.com/pull/1141) | hotfix | Null-guard Inertia::defer first paint | +16/-6 |
| [#1142](https://github.com/wagnerra23/oimpresso.com/pull/1142) | hotfix | Sidebar Cobrança + cleanup Pages/Boletos | +18/-824 |
| [#1144](https://github.com/wagnerra23/oimpresso.com/pull/1144) | refactor | Grupo sidebar via DataController (Wagner regra) | +57/-10 |

(PR #1143 hardcode SIDEBAR_GROUPS — reverted via #1144 arquitetural)

## 3 Hotfixes em prod aprendizados

### 1. `TypeError: undefined.filter()` — Inertia::defer first paint

**Bug**: Tela `/financeiro/cobranca` renderiza **completamente branca** em prod biz=WR2. Console mostra `TypeError: Cannot read properties of undefined (reading 'filter')` em Index.tsx useMemo.

**Causa**: `cobrancas`/`kpis`/`funil` são `Inertia::defer` props — chegam **undefined** no first paint até promise resolver. `useMemo` chamava `.filter()` direto sem null-guard.

**Fix**: `(cobrancas ?? []).filter(...)` + `kpis = kpis ?? KPI_FALLBACK` no body do component.

**Aprendizado**: Inertia::defer DEFAULT (RUNBOOK-inertia-defer-pattern.md) é Tier 0 mas exige null-guards CONSISTENTES no consumidor — defaults inline da signature `{ cobrancas = [] }` **NÃO funcionam** quando prop é literalmente passado como undefined pelo Inertia bridge (vs ausente).

**Catalogado em**: [`memory/reference/feedback-inertia-defer-null-guard-first-paint.md`](../reference/feedback-inertia-defer-null-guard-first-paint.md)

### 2. SIDEBAR_GROUPS hardcode label antigo

**Bug**: Mesmo após DataController declarar label "Cobrança", sidebar não mostrava item — `SIDEBAR_GROUPS['fin'].items` ainda tinha string `'Gateway de Pagamento'` (label legacy).

**Tentativa errada (#1143)**: Adicionar 'Cobrança' direto no hardcode SIDEBAR_GROUPS.

**Wagner regra textual**: *"deve ser mudado no datacontroller do modulo, nunca hardcode"*.

**Refactor correto (#1144)**:
1. `LegacyMenuAdapter.convertItem()` lê `$props['group']` / `$props['attributes']['group']` e propaga pro shape
2. `ShellMenuItem` ganha optional `group?: string`
3. `findGroupKey(item)` prioriza `item.group` antes do label match
4. DataController do módulo: `'group' => 'fin'` no array attributes

**Catalogado em**: [`memory/reference/feedback-sidebar-grupo-via-datacontroller.md`](../reference/feedback-sidebar-grupo-via-datacontroller.md)

### 3. Deploy SSH concurrency lock

**Sintoma**: Deploy #3 (run 26114939389) falhou em Setup SSH step após 49s, enquanto deploy #2 estava `in_progress`.

**Causa provável**: `concurrency.group: deploy-production` + `cancel-in-progress: false` no workflow — múltiplos deploys disparados em sequência rápida bloqueiam SSH lock concorrente.

**Mitigação**: aguardar deploy anterior completar antes de disparar próximo (~3-5min). Hotfix urgente OK disparar concorrente mas pode falhar — refazer manualmente se preciso.

## Validação visual prod biz=WR2 (Chrome MCP)

1. ✅ `/financeiro/cobranca` renderiza — header + funil 5 etapas + 4 KPIs + filtros + empty state + bundle CSS `.pg-shell-scope`
2. ✅ `/settings/payment-gateways` renderiza — 3 KPIs + 5 cards drivers (Inter/C6/Asaas/BCB Pix/PesaPal deprecated)
3. ✅ Sidebar mostra **"Cobrança"** entre "DRE / Relatórios" e "Cobrança Recorrente" no grupo FINANCEIRO
4. ✅ Link sidebar Cobrança → `/financeiro/cobranca` (não /boletos)
5. ✅ Redirect `/financeiro/boletos` → 301 → `/financeiro/cobranca`

## Backend canônico produzido

### Novos arquivos
- `Modules/PaymentGateway/Repositories/CobrancaQuery.php` (200+ linhas)
- `Modules/PaymentGateway/Services/HealthCheckService.php`
- `Modules/PaymentGateway/Http/Controllers/Settings/PaymentGatewaysController.php`
- `Modules/Financeiro/Http/Controllers/CobrancaController.php` (com store + storeCartao)
- `Modules/Financeiro/Tests/Feature/CobrancaControllerTest.php` (12 GUARDs)
- `Modules/PaymentGateway/Tests/Feature/Settings/PaymentGatewaysControllerTest.php` (8 GUARDs)
- `Modules/PaymentGateway/Tests/Feature/Settings/SellsCobrancaChipTest.php` (7 GUARDs)

### Endpoints novos
- `GET /financeiro/cobranca`
- `POST /financeiro/cobranca/emitir` (Onda 4d.5 wire-up)
- `POST /financeiro/cobranca/cartao` (Onda 4d.6 cartão + CardToken)
- `GET /settings/payment-gateways`
- `POST /settings/payment-gateways/{id}/health-check`
- `POST /settings/payment-gateways/{id}/toggle`
- `POST /sells/{id}/emitir-cobranca`
- `GET /financeiro/boletos` → redirect 301 /financeiro/cobranca

## Frontend canônico produzido

### Pages novas
- `resources/js/Pages/Financeiro/Cobranca/Index.tsx` + 7 sub-componentes em `_components/`
- `resources/js/Pages/Settings/PaymentGateways/Index.tsx` + 5 sub-componentes
- `resources/js/Pages/Sells/_components/CobrancaChip.tsx` + `CobrancaDrawer.tsx`

### Shared
- `resources/js/Pages/Financeiro/Cobranca/_lib/cobranca-shared.ts` — tokens DRIVERS/TIPOS/STATUS/ORIGENS + helpers
- `resources/js/Pages/Settings/PaymentGateways/_lib/gateway-shared.ts`
- `resources/css/cowork-payment-gateway-bundle.css` (bundle Cowork inteiro)

### Charters
- `Index.charter.md` por tela (3 charters)

### Páginas removidas (cleanup)
- `resources/js/Pages/Financeiro/Boletos/` (deletado — substituído por Cobranca)
- `Modules/Financeiro/Tests/Feature/BoletoControllerTest.php` (deletado — tela legacy removida)

## Próximas ondas backlog

- **Onda 4d.6.1**: Frontend widget Asaas JS (tokenização script.asaas.com)
- **Onda 4d.6.2**: SheetNovaCobranca extend pra UI tipo=card
- **Onda 5**: Dogfooding Superadmin (Plan "SaaS Oimpresso Premium" biz=1, tenants Contact biz=1, PesaPal deprecated)
- **Onda 6**: Cleanup colunas legacy (`rb_gateway_credential_id`, `gateway_*` accounts) + remover redirects 301 (após 90d)

## Tasks ondas — 13 completas

#1-7 F3 entrega · #8 validação prod Chrome MCP · #9 Onda 4d.6 · #10-#13 hotfixes + refactor sidebar

## Refs

- ADR 0144 PaymentGateway extração
- ADR 0170 PaymentGateway module
- [feedback-inertia-defer-null-guard-first-paint.md](../reference/feedback-inertia-defer-null-guard-first-paint.md)
- [feedback-sidebar-grupo-via-datacontroller.md](../reference/feedback-sidebar-grupo-via-datacontroller.md)
- COWORK_HANDOFF.paymentgateway-ui.md
