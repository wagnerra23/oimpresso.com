---
name: sandbox-hostnames
description: Hostnames canônicos do oimpresso — prod único, sem sandbox separado. Evita Claude/dev pegar hostname stale de config files.
type: reference
---

# Hostnames canônicos oimpresso

> **Última auditoria:** 2026-05-20 (sessão Wave 7-C Timeline FSM — Wagner corrigiu Claude afirmando "oi.wr2.com.br" como sandbox).

## Ambientes ativos

| Ambiente | URL | Onde roda | Como deploya |
|---|---|---|---|
| **Produção** | `https://oimpresso.com/` | Hostinger shared hosting | Workflow GHA `quick-sync.yml` (push to main → SSH rsync) |
| **Dev local** | `https://localhost:5173/` (Vite) + `http://localhost:8000/` (artisan serve) | Máquina do dev | `npm run dev` + `php artisan serve` |
| **CT 100 Proxmox** | endpoints internos (Centrifugo/FrankenPHP/Reverb) | Container Docker | compose-managed, ver [INFRA.md](../../INFRA.md) §6 + [proxmox-docker-host skill](../../.claude/skills/proxmox-docker-host) |
| **MCP server** | `mcp.oimpresso.com` | Hostinger subdomain | webhook GitHub→MCP sync |

## Ambientes DESCONTINUADOS (não usar)

| URL stale | Substituído por | Onde ainda aparece |
|---|---|---|
| ~~`oi.wr2.com.br`~~ | `oimpresso.com` | [vite.config.js](../../vite.config.js) (corrigido 2026-05-20) — antes era hostname legado da era WR2 Sistemas |
| ~~`oficinaimpresso.com.br`~~ | `oimpresso.com` | possíveis docs antigos — sempre prefira `oimpresso.com` |

## Não existe sandbox/staging separado

Diferente do que vite.config.js sugeria, **não há ambiente sandbox** entre dev local e prod. O fluxo é:

```
dev local (npm run dev + php artisan serve)
   ↓  git push branch
GitHub PR + CI (Pest + Visual Regression Pest 4 Browser + 14 gates)
   ↓  merge main
quick-sync.yml SSH rsync → Hostinger
   ↓
prod biz=1 (oimpresso.com)
```

Smoke real pós-merge **roda em prod biz=1** (skill `tela-smoke-pos-merge` Tier B, browser MCP read-only). Multi-tenant Tier 0 ([ADR 0093](../decisions/0093-multi-tenant-isolation-tier-0.md)) garante que biz=1 (Wagner) é isolado de biz=4 (Larissa) etc.

## Como aplicar

- **Antes de afirmar "sandbox" ou "staging":** consulte este doc. Não infira de config files.
- **Ao mexer em hostname:** atualize aqui se ambiente novo aparecer.
- **Smoke pós-merge:** sempre contra `https://oimpresso.com/` com `business_id=1`, nunca `wr2.com.br` ou variantes.

## Refs

- [memory/reference/deploy-recovery-patterns.md](deploy-recovery-patterns.md) — checklist "tela não aparece em prod pós-merge"
- [memory/reference/checklist-pos-merge.md](checklist-pos-merge.md) — 8 passos pós-merge incluso smoke
- [.github/workflows/quick-sync.yml](../../.github/workflows/quick-sync.yml) — workflow deploy SSH
- [INFRA.md](../../INFRA.md) — Hostinger vs CT 100 separação ([ADR 0062](../decisions/0062-separacao-runtime-hostinger-ct100.md))
- [memory/decisions/0061-conhecimento-canonico-git-mcp-zero-automem.md](../decisions/0061-conhecimento-canonico-git-mcp-zero-automem.md) — ZERO auto-mem privada (este doc vai pro git canon, time enxerga via MCP)
