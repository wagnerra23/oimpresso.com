# Changelog — Modules/TeamMcp

Formato: [Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/) · [Semver](https://semver.org/).

## [0.4.0] - 2026-05-16 — Wave 18 RETRY SATURATION

### Added (D4 — Services novas, ratio +2)

- `Services/CcIngestService` — extrai upsert session + ingest messages do `CcIngestController` (3 spans OTel canônicos `teammcp.cc.ingest_session/messages`).
- `Services/McpActorRepository` — Repository pattern thin pra `mcp_actors` (4 lookups: findActiveBySlug, listHumansByTrust, listAiChildren, revokedSince).

### Added (D8 — FormRequests novos, ratio 3 → 5)

- `Http/Requests/StoreActorRequest` — criação McpActor (Identity Mesh ADR 0081), allow-list `type=human|ai`, `trust_level=0..4`, slug kebab-case regex.
- `Http/Requests/CcIngestRequest` — extrai validate inline do `CcIngestController::ingest` (cap 5000 messages/batch).

### Added (tests Wave 18 RETRY)

- `Tests/Feature/Wave18RetryTeamMcpSaturationTest` — smoke das 2 Services novas + 2 FormRequests novos + cobertura OTel reflection.

### Changed

- `module.json`: adiciona `fsm_n_a:true` + razão (governança/audit, não state machine de domínio).

## [0.3.0] - 2026-05-16 — Wave 18 base SATURATION

### Added

- `Services/TeamUsageAggregator` + `Services/McpTokenIssuer` + `Services/UsageCsvExporter` (extracted do `TeamController`).
- `Http/Requests/UpdateQuotaRequest` + `Http/Requests/ExportUsageCsvRequest`.
- Pest: `Wave18ServicesExtractionTest`.

## [0.2.0] - 2026-05-16 — Wave 15 LGPD governance v3

### Added

- `LogsActivity` em `McpActor` (audit trail Identity Mesh).
- `PiiRedactor` em `CcIngestController` error path.
- Tests: `LgpdComplianceTest`, `MultiTenantTokenIsolationTest`.

## [0.1.0] - 2026-04-15 — Bootstrap

- Module scaffold (3 rotas Install + Routes/ + Controllers/Admin + Mcp).
- Entity `McpActor` + seed Identity Mesh (5 humanos: Wagner L0, Felipe/Maira L2, Luiz/Eliana L3).
- Endpoints `/team-mcp/team` (TeamController) + `/api/cc/ingest` (CcIngestController) + webhook git.
