# RUNBOOK — Acesso ao CT 100 (oimpresso-mcp + Docker stack)

> **Tipo:** receita operacional "como executar comando hoje"
> **Validado:** 2026-05-06 (Claude Code @ wagner-pc)
> **Hostinger paralelo:** ver CLAUDE.md §7 (warm-up + retry)
> **Hardening setup:** ver `RUNBOOK-ssh-hardening-ct.md` (receita inicial)

---

## TL;DR — comando que funciona hoje

```bash
tailscale ssh root@ct100-mcp 'CMD'
```

- **User:** `root` (não `dev` — `dev` é receita opcional pra ADICIONAR usuário per-dev, não o user padrão)
- **Hostname:** `ct100-mcp` (Tailscale magic DNS) ou IP `100.99.207.66`
- **Auth:** chave SSH + Tailscale ACL automático

⚠️ **Primeiro acesso da sessão:** Tailscale SSH pede re-autenticação via URL. Comando devolve algo como:

```
# Tailscale SSH requires an additional check.
# To authenticate, visit: https://login.tailscale.com/a/abc123
```

Wagner abre a URL no browser, aprova, e os próximos comandos da mesma sessão SSH (~12h, configurável no Tailscale console) passam direto. **Ação manual obrigatória pra Claude Code** — não dá pra contornar via headless.

---

## Estado da rede (verified 2026-05-06)

| Acesso | Hostname Tailscale | IP | Auth |
|---|---|---|---|
| **CT 100 (Docker host)** | `ct100-mcp` | `100.99.207.66` | Tailscale SSH + chave |
| **Proxmox host empresa** | `pve-empresa` | `100.116.24.69` | Tailscale SSH + chave |
| Wagner laptop | `claude-code-wagner-pc` | `100.92.78.86` | — (origem) |

**LAN backup** (sem Tailscale): `ssh root@192.168.0.50` direto na rede da empresa.

---

## Containers rodando no CT 100 (snapshot 2026-05-06)

```bash
$ tailscale ssh root@ct100-mcp 'docker ps --format "table {{.Names}}\t{{.Image}}\t{{.Status}}"'
NAMES             IMAGE                              STATUS
meilisearch       getmeili/meilisearch:v1.43.0       Up 2 days
mysql-workers     mysql:8.0                          Up 6 days (healthy)
ollama-embedder   ollama/ollama:latest               Up 6 days
oimpresso-mcp     oimpresso/mcp:latest               Up 18 hours (healthy)
traefik           traefik:v3.6                       Up 7 days
portainer         portainer/portainer-ce:lts         Up 7 days
vaultwarden       vaultwarden/server:1.35.8-alpine   Up 7 days (healthy)
```

| Container | Função | Skill/ADR relacionada |
|---|---|---|
| `oimpresso-mcp` | MCP server (Laravel 13 + `laravel/mcp` ^0.7) | ADR 0053 |
| `meilisearch` | Hybrid retrieval (embedder OpenAI text-embedding-3-small) | ADR 0036 |
| `ollama-embedder` | Embeddings local (futuro, não em uso prod ainda) | — |
| `mysql-workers` | DB local pros workers (separado do Hostinger) | — |
| `traefik` | Reverse proxy + Let's Encrypt cert auto | ADR 0042 |
| `portainer` | UI Docker (admin Wagner) | — |
| `vaultwarden` | Cofre de senhas (vault.oimpresso.com) | — |

---

## Atalhos comuns

### Entrar no shell do oimpresso-mcp
```bash
tailscale ssh root@ct100-mcp 'docker exec -it oimpresso-mcp bash'
```
Working dir do container: `/var/www/html` (Laravel root, mesmo layout do app Hostinger).

### Ver logs de container
```bash
tailscale ssh root@ct100-mcp 'docker logs --tail 50 oimpresso-mcp'
```

### Restart oimpresso-mcp (após mudança de config)
```bash
tailscale ssh root@ct100-mcp 'cd /opt/docker/oimpresso-mcp && docker compose restart app'
```

### Listar tools MCP registradas
```bash
tailscale ssh root@ct100-mcp 'docker exec oimpresso-mcp php artisan mcp:list-tools 2>/dev/null || docker exec oimpresso-mcp grep -rE "tool.*name" config/ routes/'
```

### Conferir SSH tunnel pro MySQL Hostinger
```bash
tailscale ssh root@ct100-mcp 'systemctl status autossh-mysql 2>/dev/null || ss -tlnp | grep 3307'
```

---

## Pegadinhas conhecidas

### 1. `tailscale: failed to look up local user "dev"`
- User `dev` não é o padrão. Use `root`.
- Receita pra ADICIONAR user `dev` (ou outro per-dev) está em `RUNBOOK-ssh-hardening-ct.md` §4.

### 2. `tailscale: failed to look up local user "BOOK-XXXX\\wagne"`
- Aconteceu em comando sem user explícito. Tailscale SSH tenta passar user do Windows local.
- **Sempre prefixar:** `tailscale ssh root@ct100-mcp` (não `tailscale ssh ct100-mcp`).

### 3. URL de auth check no primeiro comando
- Comportamento normal do Tailscale SSH server (modo `check`).
- Não é erro — Wagner abre URL e aprova, depois passa direto por algumas horas.
- Se Claude Code está autônomo (sem Wagner ao lado), agendar comando pra horário em que Wagner esteja disponível pra clicar.

### 4. Comando muito grande via aspas
- `tailscale ssh root@ct100-mcp 'cmd'` engole aspas internas — pra SQL/PHP complexo, usar heredoc:
```bash
tailscale ssh root@ct100-mcp 'bash -s' <<'EOF'
docker exec oimpresso-mcp php artisan tinker --execute="echo 'oi';"
EOF
```

### 5. `ssh root@100.99.207.66` direto (não via tailscale ssh)
- **Funciona** se você tem chave SSH instalada no CT 100.
- Mas Tailscale SSH é preferível: ACL granular, audit em Tailscale console, sem precisar gerenciar chaves manualmente.

---

## Fluxo: registrar nova tool MCP no oimpresso-mcp

Caso de uso: implementar Sprint que adiciona tool nova (ex: Sprint 1 `brief-fetch`).

```bash
# 1. Entrar no shell
tailscale ssh root@ct100-mcp 'docker exec -it oimpresso-mcp bash'

# 2. Pull da branch nova (CT 100 tem deploy próprio, separado do Hostinger)
cd /var/www/html
git pull origin main

# 3. Editar config/mcp.php (laravel/mcp ^0.7) — ver runbook específico do Sprint
# Ex: memory/requisitos/Infra/RUNBOOK-mcp-tool-brief-fetch.md

# 4. Restart container pra recarregar config
exit  # sair do exec
cd /opt/docker/oimpresso-mcp
docker compose restart app

# 5. Validar tool listada
docker exec oimpresso-mcp php artisan mcp:list-tools | grep <nome-tool>
```

---

## Refs

- **CLAUDE.md §1** — Stack-alvo IA (mcp.oimpresso.com canônico)
- **INFRA.md §6.2** — CT 100 Proxmox empresa estado
- **ADR 0042** — Infra empresa padrão (Proxmox + Docker + Traefik)
- **ADR 0053** — MCP server canônico (CT 100 + SSH tunnel pro MySQL Hostinger)
- **ADR 0058** — Centrifugo + FrankenPHP (CT 100, Reverb abandonado)
- **ADR 0061** — Zero auto-mem privada
- `RUNBOOK-ssh-hardening-ct.md` — receita hardening inicial (zero)

---

**Última atualização:** 2026-05-06 — incluído fluxo Tailscale SSH auth check via URL após Sprint 1 ativação real (descoberta: user é `root`, não `dev`; hostname é `ct100-mcp` magic DNS).
