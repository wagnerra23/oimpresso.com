---
name: runtime-rules-hostinger-ct100
description: Use ANTES de SSH no Hostinger, composer install/update em servidor, criar git worktree em servidor, ou qualquer comando que envolva laravel/mcp, laravel/octane, laravel/reverb, Centrifugo ou Horizon em ambiente remoto. Carrega a regra de separação de runtime (Hostinger ≠ CT 100 Proxmox) pra evitar contaminar shared hosting com pacotes/daemons que só pertencem ao CT 100.
---

# Runtime rules — Hostinger ≠ CT 100 Proxmox

> **Regra dura.** ADR canônica: [`0062-separacao-runtime-hostinger-ct100`](../../../memory/decisions/0062-separacao-runtime-hostinger-ct100.md).
> Mapa completo de ambientes: [`INFRA.md`](../../../INFRA.md) §6.

## Quando este skill ativa

Antes de qualquer comando que envolva:

- `ssh -p 65002 u906587222@148.135.133.115` (Hostinger)
- `tailscale ssh root@ct100-mcp` (CT 100 Proxmox)
- `composer install` / `composer update` em ambiente remoto
- `git worktree add` em path de servidor
- Tools/pacotes: `laravel/mcp`, `laravel/octane`, `laravel/reverb`, Centrifugo, Horizon supervised, autossh, FrankenPHP, Meilisearch daemon
- Deploy direto pra `oimpresso.com` ou `mcp.oimpresso.com`

## Mapa rápido

| Ambiente | Path | O que vive | O que NÃO instala |
|---|---|---|---|
| **Hostinger** (148.135.133.115:65002) | `~/domains/oimpresso.com/public_html` | App web (Inertia/L13.6) | ⛔ `laravel/mcp` · `laravel/octane` · `laravel/reverb` · daemons |
| **CT 100 Proxmox** (Tailscale 100.99.207.66) | `/opt/oimpresso-mcp/code` | MCP server, Octane, Centrifugo, Meilisearch | ⛔ servir `oimpresso.com` |
| **Local Wagner** (`D:\oimpresso.com`) | Herd + Laragon | Tudo (dev) | — |
| **CI GH Actions** | `.github/workflows/` | Pest, lint, sync lock, deploy | ⛔ daemons |

## Regras absolutas

1. ⛔ **NUNCA** instalar `laravel/mcp`, `laravel/octane` ou `laravel/reverb` no Hostinger (nem em worktree, nem em `/tmp`, nem em `~/test-*`). Comando proibido lá:
   ```bash
   composer install            # se composer.json puxa mcp/octane (puxa hoje)
   composer update <qualquer>  # idem
   composer require laravel/mcp
   ```
   Ver ADR 0062 pra fix futuro (split de composer.json ou allowlist).

2. ⛔ **NUNCA** rodar Pest da suite Copiloto/MCP no Hostinger (`vendor/bin/pest tests/Feature/Modules/Copiloto/Mcp/...`). Tem que rodar em CT 100 ou local.

3. ⛔ **NUNCA** alterar branch ativa em produção (Hostinger ou CT 100) pra "testar". Use worktree ou peça pro CI rodar.

4. ⛔ **NUNCA** editar arquivo direto via SSH. Sempre `git pull` no servidor. Drift = bug crônico (já queimou Eliana no upgrade 3.7→6.7, ver INFRA.md §2).

5. ⛔ **NUNCA** rodar daemon persistente no Hostinger (Reverb, Centrifugo, Horizon, autossh, Meilisearch, qualquer worker). Pra esses → CT 100.

6. ✅ **SEMPRE** que terminar worktree, limpar:
   ```bash
   git worktree remove --force <path>
   rm -rf <path>
   ```

## Caminhos canônicos por necessidade

| Preciso | Onde fazer |
|---|---|
| Validar PR antes de merge | GH Actions (`ci.yml` + `adr-lint.yml`) |
| Rodar Pest da suite app web (Form, Financeiro) | Local Wagner OU CI |
| Rodar Pest da suite Copiloto/MCP | CT 100 (Tailscale) OU local Wagner |
| Rodar `php artisan migrate` em prod | Hostinger (deploy controlado) |
| Rodar `php artisan mcp:*` em prod | CT 100 (container `oimpresso-mcp`) |
| Whitelist IP em Remote MySQL | Hostinger DNS API (ver INFRA.md §6.2.1) |
| Adicionar subdomínio (mcp/realtime/etc.) | Hostinger DNS API com `overwrite:false` (ADR 0045) |
| Restart container CT 100 | `tailscale ssh root@ct100-mcp` + `docker compose restart` |

## Antes de executar

Parar 5 segundos e responder:

1. **Qual servidor o comando vai bater?** (Hostinger / CT 100 / Local / CI)
2. **Esse servidor pode rodar isso?** (consultar matriz acima)
3. **Se quebrar, é reversível em <5min?** Senão, escala (publication-policy decide).

Se responder "Hostinger" + "instala mcp/octane/reverb" → **PARAR** e usar CT 100 ou local.

## Quando errar (já errei, vai errar de novo)

1. Reconhecer no chat imediatamente.
2. Limpar artefato (`git worktree remove --force` + `rm -rf`).
3. Reportar pro Wagner em 1 linha: o que fez, o que limpou, próximo passo.
4. Se aprenderam algo novo (ADR não cobre), atualizar ADR 0062 ou auto-mem.

## Referências

- [ADR 0062 — Separação dura de runtime](../../../memory/decisions/0062-separacao-runtime-hostinger-ct100.md)
- [INFRA.md §6 — Mapa de ativos](../../../INFRA.md)
- [Skill `proxmox-docker-host`](../proxmox-docker-host/SKILL.md) — receitas Docker no CT 100
- [Skill `publication-policy`](../publication-policy/SKILL.md) — quando Claude age vs escala
