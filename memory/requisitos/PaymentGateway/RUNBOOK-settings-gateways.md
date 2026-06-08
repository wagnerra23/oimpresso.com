---
slug: runbook-settings-gateways
title: "RUNBOOK — /settings/payment-gateways (Tela 2 PaymentGateway UI F3)"
type: runbook
authority: canonical
lifecycle: ativo
related_adrs: [0093, 0094, 0104, 0144, 0170]
related_us: [US-PG-F3-SETTINGS]
parent_module: PaymentGateway
persona: Wagner (superadmin / dono)
session_date: '2026-05-19'
---

# RUNBOOK · `/settings/payment-gateways` — F3 PaymentGateway UI Tela 2

> Settings de credenciais gateway. Persona Wagner. Origem: Cowork F1+F1.5 (score 93/100) aprovado [W] 2026-05-19. Handoff canon: [`COWORK_HANDOFF.paymentgateway-ui.md`](../../../COWORK_HANDOFF.paymentgateway-ui.md).

## Mission

Wagner gerencia credenciais dos 5 drivers (Inter + C6 + Asaas + BCB Pix + PesaPal deprecated) — config inicial, ativar/desativar com confirmação (Trust L3), health check real, rotação de mTLS, link público webhook por driver.

## Models reais

- `Modules\PaymentGateway\Models\PaymentGatewayCredential` (HasBusinessScope) — `payment_gateway_credentials` (Onda 2)
- `App\Account` (UPOS core) — conta destino FK via `conta_bancaria_id`

## Backend canônico

### Controller
`Modules\PaymentGateway\Http\Controllers\Settings\PaymentGatewaysController`
- `index(Request): Response` — lista credenciais + KPIs + accounts
- `healthCheck(Request, int $credentialId): JsonResponse` — endpoint async chamado via fetch direto
- `toggle(Request, int $credentialId): JsonResponse` — toggle ativo/inativo (Trust L3 confirma frontend)
- middleware `auth` — granular permission `paymentgateway.credenciais.*` em backlog

### Service
`Modules\PaymentGateway\Services\HealthCheckService`
- `resolveDriver(string $key): PaymentDriverContract` — map gateway_key → driver concreto
- `check(PaymentGatewayCredential $c): DriverHealth` — roda + atualiza health_status + health_checked_at
- `checkAll(int $businessId): array` — para botão "Testar todos"

### Rotas
- GET `/settings/payment-gateways` → `index`
- POST `/settings/payment-gateways/health-check` → `healthCheck` (all)
- POST `/settings/payment-gateways/{id}/health-check` → `healthCheck` (1)
- POST `/settings/payment-gateways/{id}/toggle` → `toggle`

## Frontend canônico

### Arquivos
- `resources/js/Pages/Settings/PaymentGateways/Index.tsx` (root)
- `resources/js/Pages/Settings/PaymentGateways/Index.charter.md`
- `resources/js/Pages/Settings/PaymentGateways/_lib/gateway-shared.ts`
- `resources/js/Pages/Settings/PaymentGateways/_components/atoms-settings.tsx` (DriverChip + HealthBadge + Toggle + FileField)
- `resources/js/Pages/Settings/PaymentGateways/_components/DrawerGateway.tsx` (4 tabs: Identificação · Credenciais · Webhook · Health)
- `resources/js/Pages/Settings/PaymentGateways/_components/SheetNovoGateway.tsx` (wizard 3 steps: Driver · Credenciais · Vínculo)
- `resources/js/Pages/Settings/PaymentGateways/_components/ConfirmToggleModal.tsx` (Trust L3 modal — N cobranças afetadas)
- `resources/js/Pages/Settings/PaymentGateways/_components/CheatSheetSettings.tsx`

Reusa: atoms canon (`Btn`, `KpiCard`, `PageHeader`, `Field`) via `@/Pages/Financeiro/Cobranca/_components/atoms`.

### Layout
`<AppShellV2>` wrappeado em `<div className="pg-shell-scope">`.

## Tier 0 Multi-tenant ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md))

- `PaymentGatewayCredential::query()` global scope automático
- Health endpoints filtram findOrFail com `where('business_id', $bizId)` antes

## Pest GUARDs obrigatórios (8)

1. `renderiza Inertia component Settings/PaymentGateways/Index`
2. `expõe Props no shape esperado (gateways, accounts, kpis, today)`
3. `expõe 3 KPIs (ativos, fail, cobs_hoje)`
4. `lista gateways do business + warn pra drivers deprecated`
5. `health-check endpoint atualiza health_status no DB`
6. `toggle endpoint inverte ativo do credential`
7. `Tier 0 IRREVOGÁVEL: PaymentGatewayCredential respeita business_id global scope`
8. `Trust L3: toggle ativo retorna confirm count cobranças em aberto`

## Cores semânticas

- emerald (OK/ativo) · amber (degraded) · rose (down) · violet (BCB PIX Aut.) · stone (default)

## Acessibilidade WCAG 2.1 AA

- ESC fecha drawer/sheet/modal/cheat
- Focus trap em drawer + sheet
- aria-labels em ícones somente-ícone
- Trust L3 modal `role="dialog"` + `aria-modal="true"`

## Refs

- ADR 0144 + ADR 0170 PaymentGateway
- Charter live: `resources/js/Pages/Settings/PaymentGateways/Index.charter.md`
- Cowork F1: `prototipo-ui/prototipos/payment-gateway-ui/components/pg-payment-gateways-page.jsx`
- LICOES_F3 + KB-9.75
