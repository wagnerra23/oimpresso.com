# SPEC — Modulo Superadmin

> Status: legado UltimatePOS, mas com `/pricing` recem-redesenhado (Inertia/React). Recomendacao: **manter**.

## Proposito

Painel superadmin do UltimatePOS — gerencia businesses (tenants), packages
(planos), pagina publica de pricing, fluxo de subscription/checkout (PayPal,
Paystack, Flutterwave, PesaPal, Razorpay), comunicador (SMS/email em massa)
e CMS leve via `frontend-pages`.

## Mudanca recente: `/pricing` redesenhado

- `PricingController@index` agora retorna `Inertia::render('Site/Pricing', ['packages', 'permissions'])`.
- Rota Blade legada preservada em `/pricing/old` (`PricingController@indexLegacy`)
  para fallback durante migracao.
- Tiers ainda hardcoded em `PricingTiers.tsx` ate PR2 hidratar com `Package::listPackages(true)`.

## Rotas

### Publicas (sem auth)

| Verbo | Rota | Controller@metodo |
|---|---|---|
| GET | `/pricing` | `PricingController@index` (Inertia) |
| GET | `/pricing/old` | `PricingController@indexLegacy` (Blade) |
| GET | `/page/{slug}` | `PageController@showPage` |

### Protegidas (`web, auth, language, AdminSidebarMenu, superadmin`, prefix `/superadmin`)

- `/`, `/stats` — `SuperadminController`
- `/business/{id}/...`, `/users/{biz}` — `BusinessController` (resource + toggleActive + usersList + updatePassword)
- `/packages/*` — `PackagesController` (resource)
- `/settings` — `SuperadminSettingsController` (edit/update)
- `/edit-subscription/{id}`, `/update-subscription`, `/superadmin-subscription/*` — `SuperadminSubscriptionsController`
- `/communicator`, `/communicator/send`, `/communicator/get-history` — `CommunicatorController`
- `/frontend-pages/*` — `PageController` (resource)
- `/install`, `/install/update`, `/install/uninstall` — `InstallController`

### Pagamento (auth + stack normal, sem middleware superadmin)

- `/subscription/{id}/pay`, `/confirm`, `/register-pay`
- `/subscription/{id}/paypal-express-checkout`
- `/subscription/post-flutterwave-payment`
- `/subscription/pay-stack`, `/subscription/post-payment-pay-stack-callback`
- `/subscription/{id}/pesapal-callback`
- `/all-subscriptions`
- `/subscription` (resource)

## Middleware `superadmin`

Custom (nao eh `auth:superadmin` nem `can('superadmin')`). Resolve via App Kernel — confirmar bind antes de assumir comportamento.

## Testes (este PR)

- `Modules/Superadmin/Tests/Feature/PricingTest.php` — Inertia render `/pricing` (component `Site/Pricing` + props `packages`/`permissions`); fallback Blade `/pricing/old`; rotas `/superadmin/*` bloqueiam acesso anon.

## Pendencias

- Hidratar `PricingTiers.tsx` com `packages` reais do DB (PR2 do redesign).
- Avaliar consolidacao dos 5 gateways de pagamento (alguns sem clientes ativos).
