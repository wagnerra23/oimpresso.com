# Connector — BRIEFING (estado consolidado)

> **Última atualização:** 2026-05-16 (Wave 18 RETRY)
> **Wave 18 RETRY score (target 97):** D5=15/15 · D8=8/8 (saturado)

## O que é

API external pra POS / Delphi / app móvel. Bridge entre clientes externos (apps móveis, ERP Delphi legacy) e o núcleo oimpresso. Roda sobre Laravel Passport (OAuth2 + token) e expõe ~25 endpoints REST.

## Capacidades atuais (Wave 18 RETRY)

- **Auth Passport** — token resolve business_id (NUNCA do input)
- **CRUD básico** — Contact, Product, Sell, Expense, Attendance, CashRegister
- **Delphi sync** — `DelphiSyncService` detecta 3 formatos (pipe / json_flat / json_nested)
- **Validação BR** — `ContactPayloadValidatorService` (CPF/CNPJ/email/mobile BR)
- **OTel** — spans `connector.contact.validate`, `connector.delphi.sync.*` via `App\Util\OtelHelper`
- **Customer Journey** — fluxo end-to-end onboarding → edição → catálogo → venda (validado em CustomerJourneyTest)

## FormRequests (D8 Wave 18 RETRY = 13)

Wave 17 (8): RegisterUser, StoreContactApi, StoreFollowUp, StoreLicencaComputador, StoreOauthClient, UpdateFollowUp, StoreCashRegisterApi, StoreExpenseApi.

**Wave 18 RETRY (+5)**:

| FormRequest | Endpoint | Uso |
|---|---|---|
| **StoreSyncRequest** | POST /api/connector/delphi-sync | Lote Delphi (cnpj + payload raw OR json) |
| **UpdateContactApiRequest** | PUT /api/contactapi/{id} | PATCH parcial Contact |
| **StoreProductApiRequest** | POST /api/product | Cria Produto (whitelist type) |
| **StoreSellPosApiRequest** | POST /api/sell | Venda PDV (products[] + payments[]) |
| **StoreAttendanceApiRequest** | POST /api/attendance | Clock in/out (required_without) |

## Customer Journey (CustomerJourneyTest)

Fluxo típico POS móvel:
1. `POST /api/contactapi` → cria Customer (StoreContactApiRequest)
2. `PUT /api/contactapi/{id}` → ajusta (UpdateContactApiRequest sometimes)
3. `POST /api/product` → catálogo (StoreProductApiRequest)
4. `POST /api/sell` → fechamento de venda (StoreSellPosApiRequest)

Cada step tem FormRequest dedicado + mensagens PT-BR + ownership via token Passport.

## Anti-patterns proibidos (Tier 0)

- ⛔ Aceitar `business_id` do payload — sempre derivar via token Passport
- ⛔ Bypass `ContactPayloadValidatorService` em gravação Contact via API (formato BR)
- ⛔ Persistir Marcacao (ponto) sem `Marcacao::anular()` (Portaria 671/2021)
- ⛔ Hardcode CPF/CNPJ teste em produção (`12345678000190` é dev-only)
- ⛔ Crud sem token Passport (middleware obrigatório)

## Tests (Pest Feature — 7 arquivos)

- `AuthApiTest` — token Passport workflow
- `MultiTenantIsolationTest` — biz=1 vs biz=99 real
- `ObservabilityTest` — OTel spans em Services
- `ScaffoldConnectorTest` — module boot
- `SmokeApiRoutesTest` — rotas registradas
- `Wave18SaturationTest` — saturação D5 + D6 + D8 (Wave 17 + RETRY)
- `CustomerJourneyTest` (W18 RETRY) — journey end-to-end FormRequests

## Referências

- ADR 0093 — Multi-tenant Tier 0 IRREVOGÁVEL
- ADR 0101 — Tests business_id=1 (NUNCA cliente real)
- ADR 0155 — Module Grade v3 (D5/D8)
- `Modules/Connector/SCOPE.md`
- `Modules/Connector/README.md` (W18 RETRY)
