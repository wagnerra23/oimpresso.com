# BRIEFING — Modules/Connector

> **1-pager executivo** · Última atualização: **2026-05-16** · Owner: [F] Felipe + [W] Wagner

## O que é

Módulo `Modules/Connector` — **REST API externa** do oimpresso. Expõe 30 controllers HTTP sob prefixo `/connector/api/*` com middleware `['log.delphi', 'auth:api', 'timezone']`. É o ponto de entrada de **todos** os consumidores externos do oimpresso.

## Por que importa

**Migração OfficeImpresso → oimpresso depende dele.** 6 clientes saudáveis em transição (Vargas, Extreme, Gold, Zoom, Fixar, Mhundo, Produart — Modules/ComunicacaoVisual em construção) usam **Delphi WR Comercial legacy** que bate em `/connector/api/processa-dados-cliente` + `/oimpresso/registrar` + `/check-update` pra sincronizar cadastro/licença/atualização. Quebrar contrato = quebrar produção dos 6 clientes simultaneamente.

## O que tem hoje (estado real)

- **30 controllers REST** em `Modules/Connector/Http/Controllers/Api/` (BusinessController, ContactController, ProductController, SellController, LicencaComputadorController, OImpressoRegistroController, CheckUpdateController, BrandController, CategoryController, UnitController, TaxController, TableController, UserController, ExpenseController, CashRegisterController, AttendanceController, FieldForce, Crm/CallLogs, Crm/FollowUp, SuperadminController, etc)
- **Auth:** Passport `auth:api` (Bearer tokens) — token carrega `business_id` (Tier 0 isolation)
- **3 formatos de payload Delphi** suportados: `array_tabelas` (G1 legacy 3.7), `json_flat` (G2 atual), `pipe` (TThreadLicenca fallback)
- **Service novo** `DelphiSyncService` centraliza parser/formatter (3 formatos + response `S;msg`/`N;motivo`)
- **Middleware `log.delphi`** registra TODA chamada (mesmo 401) pra debug pós-hoc (request_headers + body_preview + body_format + business_location_id resolvido)
- **Tests Wave A:** `AuthApiTest` (5 specs auth pipeline), `MultiTenantIsolationTest`, `SmokeApiRoutesTest` + `tests/Feature/Connector/DelphiOImpressoContractTest.php` (~30 specs contract)
- **Test scaffold novo:** `ScaffoldConnectorTest` (Module::find + Route::has named + DelphiSyncService instanciável)

## Diferenciais vs concorrência

| Concorrente horizontal | Tem REST API documentada? | Suporta legacy desktop? |
|---|---|---|
| Bling | ✅ OpenAPI 3.0 | ❌ |
| Tiny | ✅ docs Swagger | ❌ |
| Conta Azul | 🟡 parcial | ❌ |
| Omie | ✅ docs API | ❌ |
| **oimpresso Connector** | 🟡 sem OpenAPI ainda | ✅ **único do mercado** que sincroniza Delphi legacy |

**Vantagem comercial:** capacidade de migrar cliente OfficeImpresso Delphi gradualmente (paralelo, sem big-bang). Concorrentes só oferecem migração completa-ou-nada.

## Gaps conhecidos (P0-P3)

| Prio | Gap | Impacto |
|---|---|---|
| **P0** | Documentação OpenAPI 3.0 ausente (scribe não rodado) | Felipe/Maiara não conseguem dar suporte sem mergulhar no código |
| **P1** | Rate limiting per-business ausente | Cliente Delphi com bug pode flood `/processa-dados-cliente` |
| **P2** | Webhook outbound (push em vez de poll Delphi) | Latência sync 30s+ |
| **P3** | SDK Delphi oficial (.pas helpers) | Cliente novo precisa rescrever parser |

## Nota módulo (estimada)

**48/100 (Médio)** · D1 controllers=8/15, D2 service+tests=11/20, **D3 SPEC/BRIEFING/CHARTER=0/15 antes desta entrega** → **15/15 agora**, D4 OpenAPI 3/20, D5 charter 5/10, D6 ADR 11/20.

Após esta Wave Massive: **~63/100 (Bom)** estimado.

## Cliente piloto / sinal qualificado

- **6 clientes OfficeImpresso saudáveis em migração** — usam Connector como ponte Delphi ↔ oimpresso
- **Modules/ComunicacaoVisual** (em construção) será o primeiro vertical a depender 100% do Connector
- **ROTA LIVRE biz=4** usa Connector apenas indireto via app mobile (volume baixo)

## ADRs canônicas

- [ADR 0021](../../decisions/0021-connector-delphi-restaurado.md) Endpoints Delphi restaurados do 3.7
- [ADR 0062](../../decisions/0062-separacao-runtime-hostinger-ct100.md) Runtime Hostinger ≠ CT 100
- [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) Multi-tenant Tier 0
- [ADR 0101](../../decisions/0101-tests-business-id-1-nunca-cliente.md) Tests biz=1

## Arquivos canônicos do módulo

- [`SPEC.md`](SPEC.md) — US-CONN-001..012
- [`BRIEFING.md`](BRIEFING.md) — este arquivo
- [`CHARTER-rest-api-external.md`](CHARTER-rest-api-external.md) — contrato API externa
- `Modules/Connector/Services/DelphiSyncService.php` — pattern sync centralizado
- `Modules/Connector/Tests/Feature/ScaffoldConnectorTest.php` — smoke estrutural
