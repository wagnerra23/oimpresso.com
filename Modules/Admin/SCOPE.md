---
module: Admin
purpose: "Centro de Operações Wagner-only (admin.oimpresso.com em CT 100/Tailscale-only). Dashboard executivo: brief + health + cycles + ADR Tier 0 alerts. Bloqueio duro is-wagner + TailscaleOnly middleware."
contains:
  - "DataController"
  - "FeatureFlagsController"
  - "IndexController"
  - "InstallController"
  - "MutationsController"
not_contains:
  - "Superadmin cliente-side → Modules/Superadmin (mantido)"
  - "Admin team MCP → /copiloto/admin/team (Hostinger)"
  - "Acessível pelo time → bloqueio duro is-wagner"
trust_required: L1
owner: wagner
permission_prefix: admin.*
charter_adr: 0122
related_adrs:
  - 0122-admin-center-ct100
  - 0062-separacao-runtime-hostinger-ct100
url_prefixes:
  - /admin/*
drift_alerts: []
---

# Modules/Admin — Centro de Operações

> ADR mãe: [0122](../../memory/decisions/0122-admin-center-ct100.md)
> SPEC: [memory/requisitos/Admin/SPEC.md](../../memory/requisitos/Admin/SPEC.md)
> Subdomínio: `admin.oimpresso.com` (CT 100/Tailscale-only)

## Status Sprint 1

| US | Status | Notas |
|---|---|---|
| US-ADM-001 scaffold | ✅ Sprint 1 (este PR) | módulo nWidart, 3 rotas Install + /admin |
| US-ADM-002 Traefik+DNS | ⏳ pendente Wagner | DNS A admin.oimpresso.com → 100.99.207.66 + container CT 100 |
| US-ADM-003 auth gate | ✅ Sprint 1 (este PR) | IsWagner + TailscaleOnly middleware + audit log migration |
| US-ADM-004 Page shell | ✅ Sprint 1 dia 3 | Pages/Admin/Index.tsx + 4 widget components React + charter |
| US-ADM-005 Brief widget | ✅ Sprint 1 dia 3 | BriefAdapter (cache 5min) + WidgetBrief preview markdown |
| US-ADM-006 Health widget | ✅ Sprint 1 dia 3 | HealthSnapshotReader + WidgetHealth (lê snapshot file) |
| US-ADM-007 Cycles widget | ✅ Sprint 1 dia 3 | CyclesAggregator + WidgetCycles (mcp_cycles+mcp_tasks) |
| US-ADM-008 ADR Tier 0 widget | ✅ Sprint 1 dia 3 | AdrAlertReader reusa snapshot + top-bar alerta vermelha |
| US-ADM-009 Pest | 🟡 parcial | scaffold + 2 testes graceful adapters; matriz 6 cenários auth pendente Sprint 1 dia 5 |
| US-ADM-010 smoke | ⏳ pendente Wagner | Tailscale + DNS resolvendo OU `ADMIN_BYPASS_LOCAL=true` em dev local |

**Bypass DEV** (Sprint 1 dia 3): adicionado `config('admin.bypass_local')` em `IsWagner` + `TailscaleOnly`. Em `APP_ENV=local` + `.env ADMIN_BYPASS_LOCAL=true`, ambos middlewares passam direto. Wagner testa local sem Tailscale.

## Não-goals

❌ NÃO substitui Officeimpresso superadmin (cliente-side mantido)
❌ NÃO substitui /copiloto/admin/team (mantido em Hostinger)
❌ NÃO acessível pelo time (bloqueio duro `is-wagner`)
❌ NÃO acessível pela internet pública (Tailscale CIDR whitelist)

## US-PRE pendentes (Wagner faz)

1. **DNS A record** `admin.oimpresso.com` → `100.99.207.66` via Hostinger DNS API
2. **Container Docker** no CT 100 com FrankenPHP + Horizon + autossh tunnel pra MySQL Hostinger
3. **TLS strategy**: Let's Encrypt HTTP-01 não funciona em IP Tailscale-only. Decidir entre:
   - cert auto-assinado + adicionar CA no laptop Wagner (simples, manual)
   - DNS-01 challenge via Hostinger DNS API (automatizado, requer plugin Traefik)

## Riscos transversais (Agent D security review 2026-05-10)

- TLS Tailscale-only ≠ HTTP-01 trivial — Sprint 1 pode usar self-signed temp
- `is_wagner` hardcoded — fallback_username via env mitiga DB corruption
- CIDR Tailscale 100.99.0.0/16 frágil em re-onboard — env-driven mitiga
- Conflito Traefik labels com mcp.oimpresso.com — labels name único `admin` ≠ `mcp`
