---
title: "Infra — RUNBOOK Feature Flag Control (3 canais)"
us: US-INFRA-008
owner: Wagner
created: 2026-05-13
relacionadas:
  - US-INFRA-001 (GrowthBook self-hosted)
  - ADR 0093 (multi-tenant Tier 0)
  - ADR 0094 (Constituição v2 — transparência via audit)
  - ADR 0122 (Admin Center @ CT 100)
---

# RUNBOOK — Feature Flag Control (Artisan / MCP / Painel)

## Por que existe

`FeatureFlagService` (US-INFRA-001) só LÊ feature flags do GrowthBook. Pra
**escrever** (ligar/desligar regras por business_id, mata-switch de environment,
limpar cache) o caminho era abrir manualmente `https://growthbook.oimpresso.com`
e clicar no painel UI — fricção alta, sem audit no nosso lado, sem integração com
Claude no chat.

US-INFRA-008 entrega 3 canais redundantes, todos sobre o mesmo
`App\Services\GrowthBookAdminService`:

| Canal | Quem usa | Onde |
|---|---|---|
| **Artisan CLI** | Wagner / Felipe / Maiara via SSH ou local | `php artisan flag:*` |
| **Tool MCP** | Claude no chat (qualquer dev do time) | `mcp__oimpresso__flag-*` |
| **Painel Inertia** | Wagner via browser (Tailscale-only) | `/admin/feature-flags` |

Toda mudança grava 1 linha em `feature_flag_audits` (append-only).

## Pré-requisito: Personal Access Token

```
1. Abrir https://growthbook.oimpresso.com
2. Settings (canto inferior esquerdo) → Personal Access Tokens
3. Create Token → Role: admin → Scope: Full Access
4. Copiar token (formato secret_admin_xxxxxxxxxxxxx)
5. Salvar em:
   - Vaultwarden (vault.oimpresso.com) — item "GrowthBook Admin API Token"
   - .env Hostinger: GROWTHBOOK_ADMIN_API_TOKEN=secret_admin_xxx
   - .env CT 100:   GROWTHBOOK_ADMIN_API_TOKEN=secret_admin_xxx
6. Reiniciar daemon Laravel/MCP em CT 100 pra reler env
```

Sem token, todos os 3 canais retornam erro claro "não configurado".

## Cheatsheet — operações comuns

### 1. Ligar feature pra um business_id

```bash
# Artisan
php artisan flag:set useV2SellsCreate --biz=4 --enabled=true --clear-cache

# Chat (Claude via MCP)
"flag-set key=useV2SellsCreate biz_id=4 value=true"

# Painel
/admin/feature-flags → useV2SellsCreate → "Adicionar rule" biz_id=4 value=true
```

### 2. Desligar feature pra um business_id (emergência rollback)

```bash
php artisan flag:set useV2SellsCreate --biz=4 --enabled=false --clear-cache
# OU mais limpo (volta pro defaultValue):
php artisan flag:set useV2SellsCreate --biz=4 --remove --clear-cache
```

No chat: *"desliga useV2SellsCreate pra biz=4"* → Claude chama `flag-set`.

### 3. Mata-switch (matar feature global no env)

```bash
php artisan flag:env-toggle useV2SellsCreate --enabled=false --clear-cache
```

### 4. Listar todas / detalhar

```bash
php artisan flag:list
php artisan flag:get useV2SellsCreate
```

### 5. Limpar cache Laravel (TTL 60s)

```bash
php artisan flag:cache-clear
```

## Auditoria

```sql
-- Últimas 20 mudanças em qualquer flag
SELECT created_at, actor_label, flag_key, action, environment, diff_summary
FROM feature_flag_audits
ORDER BY id DESC LIMIT 20;

-- Histórico de 1 flag
SELECT * FROM feature_flag_audits
WHERE flag_key = 'useV2SellsCreate'
ORDER BY id DESC;
```

Schema: [database/migrations/2026_05_13_201220_create_feature_flag_audits_table.php](../../../database/migrations/2026_05_13_201220_create_feature_flag_audits_table.php).

## Convenção: rule id `biz-{N}`

Pra padronizar, **1 business_id = 1 rule** com:
- `id`: `biz-{N}` (ex: `biz-4`)
- `condition`: `{"business_id": N}`
- `type`: `force`
- `value`: `"true"` ou `"false"`

Rules sem prefixo `biz-` foram criadas direto pelo painel UI GrowthBook —
o painel `/admin/feature-flags` mostra todas, mas só permite remover/editar
as `biz-*` (rules complexas ficam read-only e o user vai pro painel oficial).

## Pegadinhas

| Sintoma | Causa | Solução |
|---|---|---|
| Toggle aplicado mas Laravel continua antigo | Cache local TTL 60s | `flag:cache-clear` ou flag `--clear-cache` |
| `flag:list` retorna "não configurado" | `.env` sem `GROWTHBOOK_ADMIN_API_TOKEN` | Adicionar token + reiniciar Octane/php-fpm |
| HTTP 401 | Token expirado/inválido | Gerar novo em Settings → PAT |
| HTTP 404 em `flag:get` | Feature key não existe | Criar primeiro via UI ou conferir nome |
| Audit log vazio | Migration `feature_flag_audits` não rodou | `php artisan migrate` |

## Histórico

- **2026-05-13** — US-INFRA-008 entregue (3 canais + audit). Disparado por
  emergência rollback Sells v2 biz=4 (Larissa/ROTA LIVRE) — toggle manual no UI
  custou ~60s de fricção que com tool MCP cai pra ~5s no chat.
