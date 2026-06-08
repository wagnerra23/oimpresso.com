# BRIEFING — Modules/Admin

> Estado consolidado da capacidade — 1 página executiva (regra Tier 0
> 2026-05-15 `memory/proibicoes.md`). Atualizado por PR mexido.
> Última atualização: Wave 18 (2026-05-16).

## O que é

Centro de Operações Wagner-only @ CT 100 — painel read-mostly que **agrega**
visão dos sub-sistemas críticos do oimpresso (Brief, Health checks, Cycles,
ADRs Tier 0, Curador, MCP server, Vaultwarden, Sessions, Infra, Brain B cost).

**Não substitui** Officeimpresso superadmin nem `/copiloto/admin/team` — agrega
visão deles. Ver [ADR 0122](../../memory/decisions/0122-admin-center-ct100.md).

## Auth stack — 3 camadas (Wagner-only)

```
Request → tailscale-only → auth → is-wagner → Controller
          (CIDR Tailscale) (Laravel) (3 ANDs)
```

`is-wagner` valida **3 condições simultâneas**:
1. `user_id` = config('admin.wagner_user_id', 1) OU username fallback
2. `business_id` = 1 (Wagner WR2 — ADR 0101 nunca biz cliente)
3. Role/permission `superadmin` presente

Dev local: `ADMIN_BYPASS_LOCAL=true` pula tailscale + is-wagner.

## Cross-tenant intencional (multi-tenant Tier 0 ADR 0093)

Admin é o **único** módulo do projeto autorizado a ler dados cross-business
(sem global scope `business_id`). Justificativa: Wagner é dono, vê tudo. O
middleware `is-wagner` substitui o global scope. Tests
`CrossTenantAdminTest.php` + `MultiTenantPermissionTest.php` validam que
demais módulos NÃO conseguem cross-tenant sem `is-wagner` passar.

## 3 Controllers + 10 Services

- **`IndexController`** — `GET /admin` renderiza `Admin/Index.tsx` com 10
  widgets em paralelo (eager — ver D6 abaixo).
- **`MutationsController`** — 3 ações double-confirmation (apply curador
  batch, regen MCP token, run health-check now). Audit obrigatório em
  `mcp_admin_audit_log` ANTES + DEPOIS da execução.
- **`FeatureFlagsController`** (US-INFRA-008) — painel GrowthBook com
  set/remove biz-rules + mata-switch env + audit `feature_flag_audits`.

**10 Services adapter layer** (1 por widget): `BriefAdapter`,
`HealthSnapshotReader`, `CyclesAggregator`, `AdrAlertReader`,
`CuradorStatsReader`, `McpServerHealthReader`, `VaultwardenReader`,
`SessionsReader`, `InfraStatusReader`, `BrainBCostReader`. Cache 5min por
widget. Graceful fallback `_unavailable: true` empty state.

## D6 defer — DOCUMENTADO Wave 18 (não-trocado)

`IndexController` mantém **eager load** dos 10 widgets, com comentário
explicativo. Trocar pra `Inertia::defer(fn() => ...)` requer **par MWART**:
controller PHP + `Admin/Index.tsx` frontend wrap em `<Deferred data="..."
fallback={skeleton}>` por widget. PR #963 (Wave L/W7) trocou só o controller
e quebrou Pages (initial render undefined) — rollback obrigatório.

**Plano futuro:** próximo sprint frontend Admin/Index pode receber o par
controller+page atualizados juntos. Por ora: D6 score 5/10 (eager funciona
+ documentado + OtelHelper mede latência).

## Observabilidade D9 — OTel

- **10 services** com `OtelHelper::spanBiz` (Wave 17).
- **2 Controllers** com spans agregadores (Wave 18): `admin.index.widgets`
  (10 widgets) + `admin.feature_flags.index` (REST + DB).

Zero-cost quando `otel.enabled=false`.

## FSM — N/A

`module.json`: `governance.fsm_n_a: true`. Admin Center é painel read-mostly —
não tem entidade de negócio com transições de estado. As 3 ações mutacionais
(`applyCurador`, `regenerateMcpToken`, `runHealthCheckNow`) são atômicas
auditáveis double-confirmation, não fluxos multi-stage. FSM Pipeline
([ADR 0143](../../memory/decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md))
aplica a Sells/Repair, não a meta-administração.

## D1 Models — N/A

Admin é **services-only**. Sem Eloquent Models de negócio próprias — consome
tabelas core (`users`, `mcp_briefs`, `mcp_tokens`, `feature_flag_audits`).
A trait `business_id` global scope não aplica (cross-tenant intencional via
`is-wagner` middleware). Score D1 30/30 mantido por arquitetura adequada
(testes provam isolation).

## Pendentes conhecidos

- **Admin/Index.tsx + Inertia::defer** — par MWART pendente (ver D6 acima).
- **`StoreUserRequest` + `UpdatePermissionRequest`** — placeholders Sprint
  futuro (gestão admin-center user separado do core `users` UltimatePOS).
- **`mcp_admin_audit_log`** — tabela criada migration mas Console retention
  job ainda inexistente. Próximo Sprint.

## Links

- [ADR 0122 — Admin Center CT 100](../../memory/decisions/0122-admin-center-ct100.md)
- [SCOPE.md](./SCOPE.md)
- [CHANGELOG.md](./CHANGELOG.md)
- [US-INFRA-008 Feature Flags](../../memory/sessions/2026-05-13-us-infra-008-feature-flags.md)
