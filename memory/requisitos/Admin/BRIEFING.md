# BRIEFING — Modules/Admin (Admin Center @ CT 100)

> **Estado consolidado 1-pager** · Atualizado: 2026-05-16
> Canon: [SPEC.md](SPEC.md) · ADR mãe: [0122](../../decisions/0122-admin-center-ct100.md)

## O que é

Painel **Wagner-only** que agrega visão de toda infra/governance/time do oimpresso num único cockpit. Subdomínio `admin.oimpresso.com` (Tailscale-only via CT 100, DNS A → `100.99.207.66`). NÃO substitui Officeimpresso superadmin nem `/copiloto/admin/team` — agrega read-mostly + 3 mutations controladas.

## Diferenciais

- **CT 100-only** — zero superfície de ataque pública; DNS interno Tailscale
- **Triple-gate**: `tailscale-only` middleware → `auth` → `is-wagner` (user_id=1 AND business_id=1 AND role superadmin) — gate IP zero-cost ANTES de auth
- **Audit append-only** em `mcp_admin_audit_log` — toda mutation grava reason + payload + user_id
- **Double-confirmation** em endpoints mutacionais (reason ≥5 chars + confirm bool)
- **Read-mostly** — 4 widgets (Brief, Health, Cycles, ADRs Tier 0) + 3 mutations (Curador apply, MCP token rotate, run-now health-check)
- **Multi-tenant Tier 0 preservado** — `withoutGlobalScopes` apenas com `// SUPERADMIN: <razão>` mandatório (ADR 0093)
- **Feature flags GrowthBook scoped por business** — painel `/admin/feature-flags` audita toda mudança biz-rule

## Status atual

| Componente | Estado |
|---|---|
| Sprint 1 MVP (US-ADM-001..010) | ✅ done — scaffold + 4 widgets + auth gate + Pest 6 cenários |
| Sprint 2 Mutations (3 endpoints) | ✅ done — `MutationsController` com `AdminAuditLogger` |
| Sprint 3 Feature Flags painel | ✅ done — `FeatureFlagsController` + audit table |
| Tests cross-tenant (this PR) | 🟡 em PR — `CrossTenantAdminTest` + `MultiTenantPermissionTest` |
| Charter Index.tsx | 🔜 backlog |
| RUNBOOK acesso CT 100 | ✅ existe ([Infra/RUNBOOK-acesso-ct100.md](../Infra/RUNBOOK-acesso-ct100.md)) |

## Gaps conhecidos (módulo grade 49/100)

- **D1 testes 10/30** — cross-tenant + permission isolation faltando (PR atual cobre)
- **D3 charter 6/15** — `Index.charter.md` ainda não criado (US-ADMIN-007)
- **Curador apply** ainda stub Sprint 2 — não mexe filesystem real
- **MCP token regenerate** retorna 404 em homolog (tabela `mcp_tokens` só vive CT 100 prod)

## Arquivos canônicos

- Código: `Modules/Admin/{Http,Services,Database/Migrations}/`
- Tests: `Modules/Admin/Tests/Feature/*.php` (7 arquivos após PR)
- SPEC: `memory/requisitos/Admin/SPEC.md` (canônico US-ADM-001..N)
- ADR mãe: [0122](../../decisions/0122-admin-center-ct100.md)
- Helper auth: `tests/Helpers/AdminAuthHelper.php`
- Audit table: `mcp_admin_audit_log` (migration 2026_05_10)
- Feature flags audit: `feature_flag_audits` (Sprint 3)

## Próximos passos sugeridos

1. Smoke walkthrough Wagner via Tailscale (US-ADM-010)
2. Criar `Index.charter.md` ao lado de `Pages/Admin/Index.tsx` (Charter > Spec, ADR 0094 §3)
3. Sprint 4 — Curador integrado real (US-ADM-011..014)
