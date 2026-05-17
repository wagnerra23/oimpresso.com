# Modules/Connector

> **API external pra POS / Delphi / app móvel.** Bridge entre clientes externos (apps móveis, ERP Delphi legacy) e o núcleo oimpresso. Roda sobre Laravel Passport (OAuth2 + token) e expõe ~25 endpoints REST.

## Status — Wave 18 RETRY (2026-05-16)

- **Multi-tenant Tier 0**: business_id sempre resolvido via token Passport (ADR 0093). NUNCA do input.
- **Pest tests**: 6 arquivos em `Tests/Feature/` (Auth, MultiTenant, Observability, Scaffold, SmokeApi, Wave18Saturation, CustomerJourney).
- **FormRequests**: 11 (RegisterUser, StoreContactApi, StoreFollowUp, StoreLicencaComputador, StoreOauthClient, UpdateFollowUp, StoreCashRegisterApi, StoreExpenseApi + Wave 18 RETRY: **StoreSync**, **UpdateContactApi**, **StoreProductApi**, **StoreSellPosApi**, **StoreAttendanceApi**).
- **Services**: ContactPayloadValidatorService (validação CPF/CNPJ/email/mobile BR), DelphiSyncService (3 formatos: pipe|json_flat|json_nested).
- **OTel**: spans `connector.contact.validate`, `connector.delphi.sync.*` via `App\Util\OtelHelper` canônico.

## Endpoints principais

| Rota | Método | FormRequest | Service |
|---|---|---|---|
| `/api/contactapi` | POST | StoreContactApiRequest | ContactController |
| `/api/contactapi/{id}` | PUT/PATCH | **UpdateContactApiRequest** (W18) | ContactController |
| `/api/product` | POST | **StoreProductApiRequest** (W18) | ProductController |
| `/api/sell` | POST | **StoreSellPosApiRequest** (W18) | SellController |
| `/api/attendance` | POST | **StoreAttendanceApiRequest** (W18) | AttendanceController |
| `/api/connector/delphi-sync` | POST | **StoreSyncRequest** (W18) | DelphiSyncService |
| `/api/expense` | POST | StoreExpenseApiRequest (W18) | ExpenseController |
| `/api/cashregister` | POST | StoreCashRegisterApiRequest (W18) | CashRegisterController |
| `/api/oauth/clients` | POST | StoreOauthClientRequest | (Passport) |
| `/api/connector/licenca-computador` | POST | StoreLicencaComputadorRequest | LicencaComputadorController |
| `/api/register-user` | POST | RegisterUserRequest | UserController |

## Customer Journey (validado em CustomerJourneyTest)

Fluxo típico POS móvel:

1. **Onboarding**: `POST /api/contactapi` → cria Customer.
2. **Edição**: `PUT /api/contactapi/{id}` → ajusta dados (PATCH parcial via `sometimes`).
3. **Catálogo**: `POST /api/product` → registra Produto novo no estoque.
4. **Venda**: `POST /api/sell` → fecha venda (products[] + payments[]).

Cada step tem FormRequest dedicado com `rules()` + mensagens PT-BR + ownership scoped via token Passport.

## Anti-patterns proibidos (Tier 0)

- ⛔ Aceitar `business_id` do payload — sempre derivar via token Passport.
- ⛔ Bypass `ContactPayloadValidatorService` ao gravar Contact via API (CPF/CNPJ formato BR).
- ⛔ Persistir Marcacao (ponto) sem `Marcacao::anular()` quando ajuste — Portaria 671/2021.

## Referências

- ADR 0093 — Multi-tenant Tier 0 IRREVOGÁVEL
- ADR 0101 — Tests business_id=1 (NUNCA cliente real)
- ADR 0155 — Module Grade v3 D5/D8 (FormRequests + Services extraction)
- `Tests/Feature/CustomerJourneyTest.php` — smoke journey end-to-end light
- `Tests/Feature/Wave18SaturationTest.php` — saturation D5+D6+D8 Wave 17+18
