---
title: "RUNBOOK — Hierarquia de credenciais (ADR 0131)"
owner: W
status: ativo
last_validated: "2026-06-08"
---

# RUNBOOK — Hierarquia de credenciais (ADR 0131)

> **Onde mora cada tipo de credencial no oimpresso.** Decisão canônica: [ADR 0131](../../decisions/0131-tiering-memoria-canonico-local-segredo.md) — 3 tiers.

## Decisão em 1 pergunta

```
Este segredo é:
  ┌─ infra/operacional      → Vaultwarden vault.oimpresso.com
  ├─ runtime app prod       → Hostinger .env / CT 100 .env (transitório, em migração)
  ├─ máquina-local dev      → ~/.claude/oimpresso-local/vault-refs.md (PONTEIRO, nunca valor)
  └─ git canônico (docs)    → NUNCA segredo, só ponteiros
```

## Lugares físicos

| Tier | Onde | Quem lê | Como ler | Limitação |
|---|---|---|---|---|
| **1. Vaultwarden** (canônico) | `vault.oimpresso.com` UI / API | Wagner + Eliana | UI manual ou `bw` CLI | Setup manual (sem automação) |
| **2. Hostinger `~/public_html/.env`** (runtime prod) | SSH Hostinger | Hostinger PHP | `ssh hostinger 'grep KEY ~/.env'` | Plain text, IP único 177.74.67.30 |
| **3. CT 100 `/opt/<stack>/code/docker/<stack>/.env`** (stack secrets) | Tailscale SSH | Docker containers | `tailscale ssh root@ct100-mcp 'cat /opt/X/.env'` | Plain text, LAN only |
| **4. `D:\oimpresso.com\.env`** (dev local) | Filesystem Wagner | Herd PHP local | `grep KEY /d/oimpresso.com/.env` | ⚠️ Plain — TODO migrar tier 1 |
| **5. `~/.claude/oimpresso-local/vault-refs.md`** (ponteiros) | Filesystem dev pessoal | só o dev | `cat ~/.claude/oimpresso-local/vault-refs.md` | Não distribuído cross-dev |

## Inventário de credenciais conhecidas (2026-05-10)

| Credencial | Tier atual | Tier desejado | Item Vaultwarden |
|---|---|---|---|
| `HOSTINGER_API` (DNS/MySQL/cert) | 4 (D:\.env) | 1 (Vaultwarden) | `hostinger-api-prod` (TODO criar) |
| `LANGFUSE_PUBLIC_KEY` / `LANGFUSE_SECRET_KEY` | 2 (Hostinger .env) | 1 + 2 | `langfuse-keys-jana-prod` (TODO) |
| Langfuse stack 5 secrets (POSTGRES_PASSWORD, etc) | 3 (CT 100 .env) | 1 + 3 | `langfuse-ct100-secrets` (TODO) |
| `ADMIN_TOKEN` Vaultwarden | 1 ✅ | 1 | `vaultwarden-admin` (existe) |
| MCP server tokens (`mcp_<hex>`) per-dev | 4 (.claude/settings.local.json) | 1 per-dev | `mcp-oimpresso-<dev>` (TODO Wagner) |
| Senha admin Langfuse Wagner | 4 (chat history!) ⚠️ | 1 (Vaultwarden) | `langfuse-admin-wagner` (TODO Wagner trocar+salvar) |
| SSH key `~/.ssh/id_ed25519_oimpresso` | local filesystem | — (key não-segredo) | n/a |
| Banco Firebird WR Comercial (`SYSDBA/masterkey`) | hardcoded em Delphi binary | 1 (rotate quando possível) | `legacy-wr-comercial` (TODO) |

## Procedimentos canônicos

### A. Adicionar segredo NOVO

1. Gerar valor: `openssl rand -hex 24` (URL-safe) OU `openssl rand -base64 32` (header)
2. Salvar em Vaultwarden item descritivo (ex: `langfuse-keys-jana-prod`)
3. Deploy: SSH no destino (Hostinger/CT 100) e adicionar `.env`
4. Atualizar este RUNBOOK linha "Inventário" + commit

### B. Recuperar segredo existente

**Sempre prefira Vaultwarden (tier 1):**

```bash
# Via Vaultwarden UI: https://vault.oimpresso.com → buscar item
# OU via bw CLI (TODO setup):
bw get item "langfuse-keys-jana-prod" | jq -r '.notes'
```

Se ainda não migrado tier 1 → tier 2/3/4 fallback:

```bash
# Hostinger runtime (tier 2)
ssh -4 -i ~/.ssh/id_ed25519_oimpresso -p 65002 u906587222@148.135.133.115 'grep ^KEY= ~/public_html/.env'

# CT 100 stack secrets (tier 3)
tailscale ssh root@ct100-mcp 'cat /opt/<stack>/code/docker/<stack>/.env'

# Dev local (tier 4) — ⚠️ migrar pro tier 1 ASAP
grep ^KEY= /d/oimpresso.com/.env
```

### C. Rotar segredo (após exposição ou semestralmente)

1. Gerar novo valor
2. **Deploy em ORDEM** pra evitar downtime:
   - Atualizar Vaultwarden primeiro
   - Atualizar tier 2 (Hostinger `.env`) e/ou tier 3 (CT 100 `.env`)
   - Restart processes (`php artisan config:clear` + `docker compose restart`)
   - Revogar antigo no provedor (Langfuse UI / Hostinger API console / etc)
3. Documentar rotação em `memory/sessions/YYYY-MM-DD-secret-rotation-X.md`

### D. Auditar credenciais em git (zero tolerance)

```bash
# Pre-commit (idealmente hook)
gitleaks detect --source . --no-banner

# Periódico (CI ou manual)
git log --all -p | grep -E '(pk-lf-|sk-lf-|HOSTINGER_API=|BEGIN PRIVATE KEY)'
```

## Anti-padrões observados (2026-05-10 sessão)

1. **Senha admin Langfuse gerada em chat log** — segredo plain em transcript. Wagner deve trocar AGORA via UI Langfuse + salvar nova no Vaultwarden `langfuse-admin-wagner`.
2. **`oimpresso-local/vault-refs.md` continha keys plain** — sanitizado nesta sessão pra usar comandos read, não valores. ADR 0131 violation by Claude — recovery: ler `git log -p .claude/worktrees/critical-fixes/oimpresso-local/vault-refs.md` se precisar histórico.
3. **`HOSTINGER_API` em `D:\.env` plain** — Wagner usa-o ad-hoc em scripts locais. Funcional mas tier 4 dispersa. Migração pro Vaultwarden é P1 follow-up (não-urgente sem evidência de exposição).

## Referências

- [ADR 0131](../../decisions/0131-tiering-memoria-canonico-local-segredo.md) — Tiering canônico/local/segredo
- [ADR 0094](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md) — Constituição v2 (princípio 7 transparência exige segredos visíveis pra auditoria)
- [ADR 0132](../../decisions/0132-langfuse-self-host-ct100.md) — Langfuse stack (lista 5 secrets stack)
- `auto-memory` reference_hostinger.md (legado — em migração pra runbook)
- `auto-memory` reference_vaultwarden_credenciais.md (legado)
