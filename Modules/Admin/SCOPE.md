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
| US-ADM-004 Page shell | 🟡 placeholder | IndexController invoca Inertia 'Admin/Index'; .tsx pendente Sprint 1 |
| US-ADM-005..008 widgets | ⏳ Sprint 1 dia 3-4 | Brief/Health/Cycles/ADRs Tier 0 |
| US-ADM-009 Pest | 🟡 placeholder | matriz 6 cenários pendente |
| US-ADM-010 smoke | ⏳ pendente Wagner | precisa Tailscale ativo + DNS resolvendo |

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
