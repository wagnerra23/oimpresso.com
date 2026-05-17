# CHANGELOG — Modules/Admin

Formato baseado em [Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/).
Versionamento alinhado a Wave governance ([ModuleGradeService](../Governance/Services/ModuleGradeService.php) D3.d).

## [Não publicado]

### Wave 18 governance (2026-05-16)

- **ADD D3.d** `CHANGELOG.md` (este arquivo) — histórico per-wave.
- **ADD D3.a** `BRIEFING.md` — estado consolidado Admin Center 1 página
  executiva (regra Tier 0 2026-05-15 — `memory/proibicoes.md`).
- **ADD D2** `Tests/Feature/AdrAlertReaderTest.php` — 5 testes PHPUnit cobrindo
  os 4 caminhos de W4 (snapshot indisponível, all green, 1 fail Tier 0 com
  ADR map, 1 check não-Tier 0 ignorado, múltiplos fails). Usa subclasse
  anônima de `HealthSnapshotReader` para mock sem framework adicional.
- **ADD D9.a** `OtelHelper::spanBiz` em `IndexController::__invoke` (span
  `admin.index.widgets` agrega 10 widgets numa métrica única — pra detectar
  regressão de latência cross-widget) e em `FeatureFlagsController::index`
  (span `admin.feature_flags.index` cobre REST GrowthBook + DB audits).
- **UPDATE `module.json`** — `governance.fsm_n_a: true` (Admin Center é
  painel read-mostly cross-tenant — as 3 ações mutacionais são atômicas
  auditáveis, não fluxos multi-stage).
- **DOC D6 defer** — `IndexController` documenta porque mantemos eager load
  até frontend `Admin/Index.tsx` ter `<Deferred>` wrapping (par MWART
  controller+page atualizados juntos — ver `BRIEFING.md §"D6 defer"`).
- **NOOP D1** — Admin services-only (sem Eloquent Models de negócio próprias —
  consome `users`, `mcp_briefs`, `mcp_tokens`, `feature_flag_audits` core).
  Models trait `business_id` global scope não aplica (cross-tenant intencional
  Wagner-only middleware).

### Wave 17 governance (2026-05-13)

- **ADD D9.a** `OtelHelper::spanBiz` em **10 services** (`BriefAdapter`,
  `AdrAlertReader`, `BrainBCostReader`, `CuradorStatsReader`, `CyclesAggregator`,
  `HealthSnapshotReader`, `InfraStatusReader`, `McpServerHealthReader`,
  `SessionsReader`, `VaultwardenReader`).

### Sprint 2 (2026-05-13)

- **ADD** `MutationsController` — 3 ações double-confirmation (applyCurador,
  regenerateMcpToken, runHealthCheckNow).
- **ADD** `FeatureFlagsController` (US-INFRA-008) — painel GrowthBook.
- **ADD** `AdminAuditLogger` Service + tabela `mcp_admin_audit_log`.

### Sprint 1 (2026-05-10 — ADR 0122)

- Módulo Admin scaffold + middleware stack (`tailscale-only` → `auth` → `is-wagner`).
- `IndexController` com 10 widgets read-mostly (Brief, Health, Cycles,
  ADR alerts, Curador, MCP, Vaultwarden, Sessions, Infra, Brain B cost).
- 10 services adapter layer.
- Page Inertia `Admin/Index.tsx` com charter.
