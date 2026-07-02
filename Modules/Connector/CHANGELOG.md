# Modules/Connector — CHANGELOG

## [Wave 28] - 2026-05-17

### Test (D2 — Pest +2 sentry Delphi handshake)
- `Tests/Feature/Wave28PolishTest.php` — +2 testes sentry Wave 28:
  - `DelphiSyncService::detectBodyFormat` preserva whitelist canônica 4 formatos
    {`array_tabelas`, `json_flat`, `pipe`, `unknown`} (regression guard contrato
    Delphi G1 legacy 3.7 / G2 WR Comercial atual / TThreadLicenca fallback).
  - `AcceptDelphiTokenHandshakeRequest` (W23 D8) preserva anti-spoofing Tier 0
    (business_id NUNCA aceito do body Delphi — vem do CNPJ resolve server-side).
- Tier 0 IRREVOGÁVEL: resposta string `S;msg` / `N;motivo` canônica (Delphi parsa
  literal — NÃO mudar). Multi-tenant ADR 0093 + biz=4 intocado (ADR 0101).

### Governance
- Saturação 70-95 → 96 (polish final excelência).

## [Wave 27] - 2026-05-17

### Docs (D5 — README handshake sequence diagram + edge cases expand)
- `README.md` — seção "Como cliente Delphi usa" Wave 27:
  - **Mermaid sequence diagram** completo (4 fases: handshake → sync batch
    → renovação proativa → rotate manual via Vaultwarden)
  - **+5 edge cases** catalogados: clock skew sem NTP, estação offline > TTL,
    rede instável TCP RST mid-batch, backoff manutenção 502/503, SQLite local
    cheio (disk full)
  - Total agora: 10 edge cases canônicos (era 5 em Wave 25)

### Code (D9 — OTel spans completos DelphiSyncService)
- `Services/DelphiSyncService.php` — +4 spans novos pra completar instrumentação:
  - `connector.delphi.format_legacy_response` — rastreia taxa S/N (sinal de
    drift quando taxa N sobe abruptamente). Attrs: ok bool + msg_len.
  - `connector.delphi.log_drift` — wrap em log warning de drift (anti-padrão
    Delphi). Attrs: reason + context_keys (NÃO logs raw — PII LGPD).
  - `connector.delphi.extract_cnpj` (novo método público `extractCnpjFromRequest`)
    — wrapper observável de helper privado.
  - `connector.delphi.detect_body_format` (`detectBodyFormatWithSpan`) —
    **fix tech debt Wave 23**: método estava referenciado em
    `Wave23ConnectorSaturationTest.php:155` mas nunca implementado (deixava
    suite com 1 fail). W27 implementa o wrapper; suite Wave 23 volta a 14/14
    green. Span permite detectar drift de formato Delphi (cliente legacy
    mandando novo formato sem aviso).
- Pré-existentes: `extract_hd` + `resolve_by_cnpj` (Wave 18). Total agora: 6 spans
  (era 2 em Wave 18). Cobre 100% das superfícies públicas do Service.

### Preserved
- D6 Inertia::defer permanece **N/A** — Connector é módulo Blade puro
  (sem `Inertia::render`). Pattern defer é específico de Pages React.

### Docs
- `CHANGELOG.md` (entry Wave 27 atual).

## [Wave 25] - 2026-05-16

### Docs (D5 — README handshake passo-a-passo)
- `README.md` — seção "Como cliente Delphi usa" expandida:
  - 4 passos do handshake (initial → token → batch sync → renewal)
  - Exemplos request/response JSON canônicos
  - 4 anti-padrões Delphi catalogados (Tier 0)
  - 5 edge cases catalogados (HD trocado, token expirado, encoding latin1,
    idempotência venda, HD cruzado entre business)
  - Referência ADR 0021 (Connector Delphi bridge mãe) explícita

### Preserved
- D6 Inertia::defer **NÃO APLICÁVEL** — Connector é módulo Blade puro
  (sem `Inertia::render`). Pattern defer é específico de Pages React.
- ContactPayloadValidatorService + DelphiSyncService permanecem fonte de
  verdade (sem extração adicional — Wave 18 RETRY já fez split máximo SoC).
- AcceptDelphiTokenHandshakeRequest (Wave 23 D8) preservado intocado —
  `business_id` prohibited no body é Tier 0 IRREVOGÁVEL.

### Docs
- `CHANGELOG.md` (entry Wave 25 atual).

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
