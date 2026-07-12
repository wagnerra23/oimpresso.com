---
name: INFRA-ACESSO-CANON — mapa único de todas as máquinas, acessos e o que roda onde
description: Fonte ÚNICA de acesso à infra do oimpresso — Tailscale (CT 100, dev), Hostinger (ERP prod), Proxmox, DNS, secrets. Como conectar em CADA máquina + o que roda onde + deploy. Claude NUNCA deve dizer "não tenho acesso" — está tudo aqui; secrets reais no Vaultwarden.
type: reference
authority: canonical
lifecycle: ativo
decided_at: 2026-05-29
related_adrs: [0045, 0053, 0058, 0062]
related: [hostinger.md, hostinger-remote-mysql.md, _INDEX-SECRETS.md]
---

# INFRA-ACESSO-CANON — todas as máquinas, como acessar, o que roda

> ⛔ **Claude: NUNCA diga "não tenho acesso" / "não sei como conectar".** Está tudo aqui.
> Tudo via **Tailscale** (já up no PC). Secrets reais no **Vaultwarden** (`vault.oimpresso.com`).

## Máquinas (Tailscale — `tailscale status`)

| Nome | IP Tailscale | O que é | Como acessar |
|---|---|---|---|
| **ct100-mcp** | `100.99.207.66` | **CT 100 Proxmox** — TODOS os daemons + o MCP server | `tailscale ssh root@ct100-mcp` (auth Tailscale, **sem senha**) |
| **pve-empresa** | `100.116.24.69` | Host Proxmox (hypervisor do CT 100) | `tailscale ssh root@pve-empresa` |
| **oimpresso-sistema** | `100.108.23.105` | Windows (máquina dev/sistema) | Tailscale |
| **claude-code-wagner-pc** | `100.92.78.86` | Este PC (onde o Claude Code roda) | — |
| **Hostinger** (não-Tailscale) | `148.135.133.115:65002` | **Shared hosting — ERP `oimpresso.com` prod** | `ssh -4 -i ~/.ssh/id_ed25519_oimpresso -p 65002 u906587222@148.135.133.115` (warm-up curl 5× antes — ver [hostinger.md](hostinger.md)) |

Chave SSH única: `~/.ssh/id_ed25519_oimpresso`. **Separação de runtime ([ADR 0062](../decisions/0062-separacao-runtime-hostinger-ct100.md)): Hostinger = ERP web (sem daemons); CT 100 = TODO daemon/IA/MCP.**

## CT 100 (`ct100-mcp`) — o coração da infra

**Acesso:** `tailscale ssh root@ct100-mcp "COMANDO"` — não pede senha (identidade Tailscale). Funciona non-interactive (ao contrário do SSH-senha, que é bloqueado neste agente GUI).

**Containers (docker, ~20):**
`meilisearch` · `ollama-embedder` · **`oimpresso-mcp`** (MCP server) · `bge-reranker` · `centrifugo` · `langfuse-web`/`worker`/`postgres-langfuse`/`redis-langfuse`/`clickhouse-langfuse` · `minio-langfuse` · `growthbook`(+`mongo`) · `whatsapp-whatsmeow` · `jaeger` · `mysql-workers` · `traefik` · `portainer` · `vaultwarden`.

### oimpresso-mcp (o MCP server — `mcp.oimpresso.com`)
- **Runtime:** FrankenPHP + Laravel Octane (16 workers, `--max-requests=500`). Laravel 13.6.
- **Código:** bind-mount **host `/opt/oimpresso-mcp/code` → container `/var/www/html`**. git fica no **host** (`/usr/bin/git`), NÃO no container.
- **Imagem:** `oimpresso/mcp:latest` · compose+Dockerfile: `docker/oimpresso-mcp/{docker-compose.yml,Dockerfile.octane}` (no repo).
- **DB:** usa o **MySQL da Hostinger** `u906587222_oimpresso` (compartilhado) — migrations rodadas lá; CT 100 só roda o app.
- **.env:** `/opt/oimpresso-mcp/code/.env` (host).

**Deploy (code-only, sem migration — schema vem da Hostinger):**
```bash
tailscale ssh root@ct100-mcp '
  cd /opt/oimpresso-mcp/code && git fetch origin main && git reset --hard origin/main &&
  docker exec oimpresso-mcp sh -c "cd /var/www/html && composer install --optimize-autoloader --no-interaction" &&
  docker exec oimpresso-mcp php artisan config:clear &&
  docker exec oimpresso-mcp php artisan octane:reload   # reload gracioso (workers em mem)
'
```
**Se mudar Dockerfile/exts → rebuild:** `cd docker/oimpresso-mcp && docker compose build && docker compose up -d` (recria container = breve downtime; tag a imagem antiga antes pra rollback).
**Rollback:** `git reset --hard <sha-anterior>` + `octane:reload` (~30s).

### Meilisearch (CT 100)
- Não publica porta no host. Consultar **de dentro do container**: `docker exec meilisearch sh -c 'curl -s http://localhost:7700/... -H "Authorization: Bearer $MEILI_MASTER_KEY"'` (key via env interno, nunca imprimir).
- Índices: `jana_memoria_facts` (Jana, per-cliente) + `mcp_memory_documents` (MCP, global). filterableAttributes do mcp_memory_documents: `[status,type,module,slug]` (SEM business_id — corpus global); jana_memoria_facts: `[business_id,user_id,valid_until]`.
- **Embedder = `qwen3_local`** (qwen3-embedding:0.6b, 1024d, via `ollama-embedder`). NÃO usar `nomic` (inútil em PT-BR — cosine ~0.97 pra tudo; eval Sprint 9: nomic 0.158 vs FULLTEXT 0.700). **`COPILOTO_MEMORIA_EMBEDDER=qwen3_local`** no .env (DEVE casar com o embedder do índice — se divergir, "Cannot find embedder X").
- ⚙️ **Config-as-code (não setar manual!):** o embedder vive em `config copiloto.meilisearch_indexes` e é aplicado por **`php artisan jana:meilisearch-setup`** (idempotente). Era manual via curl (Sprint 9b) e SE PERDEU 2× → agora codificado. Após mudar embedder: Meilisearch re-embeda; `scout:import` força reindex.

## DNS (Hostinger API)
`developers.hostinger.com/api/dns/v1/zones/oimpresso.com` (PUT `overwrite:false`). Token no Vaultwarden (`hostinger-api-token`). A-records de serviço → **`177.74.67.30`** (IP público CT 100). Detalhe: [ADR 0045](../decisions/0045-hostinger-dns-api-endpoint-canonico.md) + [hostinger.md](hostinger.md).

## Secrets
**Vaultwarden** `vault.oimpresso.com` (container CT 100). Índice canon: [_INDEX-SECRETS.md](../_INDEX-SECRETS.md). NUNCA commitar valor; sempre ponteiro.

### Ler segredo pelo agente — `get-secret.sh` (canônico, Opção B)
Mecanismo único pra o agente ler QUALQUER segredo do Vaultwarden sem escalar pro Wagner e sem manusear valor no chat. Fonte no git: [`scripts/infra/get-secret.sh`](../../scripts/infra/get-secret.sh) → deployado no CT 100 em `/root/bin/get-secret.sh`.
```bash
tailscale ssh root@ct100-mcp '/root/bin/get-secret.sh <slug>'            # imprime o segredo
tailscale ssh root@ct100-mcp '/root/bin/get-secret.sh <slug> --field X'  # custom field
tailscale ssh root@ct100-mcp '/root/bin/get-secret.sh --status'          # diagnóstico
```
- **Login:** service account `claude-agent` (API key) → login `--apikey` + unlock com master password, sessão cacheada em `/root/.bw-session` (chmod 600). Reaproveita a sessão entre chamadas.
- **Setup 1× (SÓ Wagner):** cria o user `claude-agent` no Vaultwarden admin (SIGNUPS_ALLOWED=false → via admin/invite), gera a API key, cola `BW_CLIENTID`/`BW_CLIENTSECRET`/`BW_PASSWORD` em `/root/.vaultwarden-agent-creds` (chmod 600) e compartilha os itens de segredo com o `claude-agent`. Sem isso, `get-secret.sh` sai com código `3` (NÃO CONFIGURADO) e imprime o passo-a-passo.
- **Escalável:** o mesmo mecanismo serve Asaas/Sicoob/Hostinger/etc — 1 setup pra todos os segredos futuros.

## Estado conhecido (2026-05-29)
- ⚠️ **`oimpresso-mcp` estava 1302 commits atrás** de `origin/main` (HEAD #799). DB em dia (0 migrations pendentes). Deploy do código novo estava **bloqueado**: `Dockerfile.octane` não tinha `gd/soap/sockets/opentelemetry` → `composer install` falhava. **Corrigido no Dockerfile 2026-05-29** (rebuild necessário pra aplicar).

## Regras de ouro
- CT 100 → **`tailscale ssh root@ct100-mcp`** (sempre funciona, sem senha).
- Hostinger → SSH `-4 -p 65002` com warm-up (ver [hostinger.md](hostinger.md)).
- NUNCA `computer-use` pra operar servidor — sempre SSH/API.
- NUNCA editar arquivo no servidor sem commit no git (drift).
- NUNCA daemons/octane/meilisearch no Hostinger ([ADR 0062](../decisions/0062-separacao-runtime-hostinger-ct100.md)).
- Meilisearch query → de dentro do container (porta não publicada).
