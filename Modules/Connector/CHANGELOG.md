# Modules/Connector — CHANGELOG

## [Wave 18 RETRY] - 2026-05-16

### Added (D8 — FormRequests +5)
- `Http/Requests/StoreSyncRequest.php` — POST /api/connector/delphi-sync (cnpj + payload raw OR json).
- `Http/Requests/UpdateContactApiRequest.php` — PATCH parcial Contact (sometimes em todos campos).
- `Http/Requests/StoreProductApiRequest.php` — cria Produto (whitelist type=single|variable|combo|modifier).
- `Http/Requests/StoreSellPosApiRequest.php` — venda PDV (products[] obrigatório min:1 max:500 + sub-rules + payments[]).
- `Http/Requests/StoreAttendanceApiRequest.php` — clock in/out (required_without entre clock_in_time/clock_out_time).

### Added (D5 — Tests)
- `Tests/Feature/CustomerJourneyTest.php` — smoke journey end-to-end (Contact → Update → Product → Sell). 12 testes + dataset journey.
- `README.md` (novo) — documenta endpoints + journey + anti-patterns.

### Test (D6 — Pest saturado)
- `Tests/Feature/Wave18SaturationTest.php` — +5 testes Wave 18 RETRY (StoreSync/StoreSellPos/StoreProduct/StoreAttendance/UpdateContact rules).
- Dataset `connector_form_requests` ampliado de 8 → 13 FormRequests.

### Docs
- `BRIEFING.md` (novo) — estado consolidado Wave 18 RETRY.
- `CHANGELOG.md` (este arquivo).
- `module.json` atualizado com `governance.fsm_n_a: true`.

## [Wave 18] - 2026-05-16

### Added
- `Services/ContactPayloadValidatorService.php` — validação BR CPF/CNPJ/email/mobile.
- `Services/DelphiSyncService.php` — detect 3 formatos payload Delphi.
- 2 FormRequests: StoreCashRegisterApiRequest, StoreExpenseApiRequest.
- OTel spans `connector.*` via `App\Util\OtelHelper` canônico.

### Tests
- `Wave18SaturationTest` (D5 + D6 + D8 base).
- `ObservabilityTest` (OTel smoke).
