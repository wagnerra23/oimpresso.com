# oimpresso-mcp — MCP server da empresa

> ADR 0053 / MEM-MCP-1.b — Stack Docker pro MCP server hospedado em CT 100
> Proxmox empresa. Conecta ao MySQL Hostinger via SSH tunnel sidecar.
>
> Subdomínio: `mcp.oimpresso.com` (Traefik + Let's Encrypt automático)
> DB: MySQL Hostinger compartilhado via tunnel
> Auth: Sanctum tokens (`mcp_<hex>`) emitidos pelo app principal

## Arquitetura

```
[Wagner / Felipe / Luiz Claude Code]
        │ HTTPS
        ▼
[mcp.oimpresso.com] (DNS A → 177.74.67.30 Proxmox)
        │
        ▼
[Traefik CT 100] (cert R12 Let's Encrypt automático)
        │
        ▼
[container oimpresso-mcp]
   - PHP 8.4 FPM + Nginx (single container, supervisord)
   - Bind mount: /opt/oimpresso-mcp/code → /var/www/html (read-only)
        │
        ▼
[container oimpresso-mcp-tunnel] (sidecar autossh)
        │
        │ tunnel localhost:3306 → 148.135.133.115:65002
        ▼
[Hostinger MySQL u906587222_oimpresso]
   - tabelas mcp_* (governance + memory cache)
   - tabelas existentes (read-only para Copiloto)
```

## Setup inicial (uma vez)

### 1. Criar diretórios no host CT 100

```bash
ssh root@<ct-100-ip>

mkdir -p /opt/oimpresso-mcp/{ssh,storage,bootstrap-cache,logs}
cd /opt/oimpresso-mcp
```

### 2. Clonar o repo (código bind-mountado, não copiado)

```bash
git clone https://github.com/wagnerra23/oimpresso.com.git code
cd code
git config core.fileMode false
```

### 3. Configurar SSH key pro tunnel

```bash
# Copiar chave SSH do Hostinger pro host do CT 100
# (mesma chave id_ed25519_oimpresso usada por Wagner pra entrar no Hostinger)
scp ~/.ssh/id_ed25519_oimpresso root@<ct-100-ip>:/opt/oimpresso-mcp/ssh/
ssh root@<ct-100-ip> "chmod 600 /opt/oimpresso-mcp/ssh/id_ed25519_oimpresso"
```

### 4. Setar .env

```bash
cd /opt/oimpresso-mcp/code/docker/oimpresso-mcp
cp .env.example .env
```

Preencher `.env` com:
- `APP_KEY` — mesma do Hostinger (`grep APP_KEY ~/domains/oimpresso.com/public_html/.env`)
- `DB_PASSWORD` — mesma do Hostinger (DB compartilhado)
- `COPILOTO_MCP_SYNC_TOKEN` — gerar com `openssl rand -hex 32`

### 5. Build + up

```bash
cd /opt/oimpresso-mcp/code/docker/oimpresso-mcp
docker compose build
docker compose up -d

# Aguarda healthcheck do tunnel (precisa SSH funcionar)
docker compose logs -f tunnel
```

### 6. Smoke test

```bash
# Do próprio CT 100
curl http://oimpresso-mcp/api/mcp/health

# De fora (após DNS + Traefik propagarem ~30s)
curl https://mcp.oimpresso.com/api/mcp/health
```

Esperado:
```json
{
  "status": "ok",
  "service": "oimpresso-mcp",
  "version": "0.1",
  "spec_mcp": "2025-06-18",
  "ts": "2026-04-29T..."
}
```

### 7. Gerar primeiro token (no app principal Hostinger, pq DB compartilhado)

```bash
ssh -p 65002 u906587222@148.135.133.115
cd ~/domains/oimpresso.com/public_html
php artisan mcp:token:gerar --user=1 --name="Wagner laptop"
```

Copiar raw token (`mcp_...`) e colar em `.claude/settings.local.json`:

```json
{
  "mcpServers": {
    "oimpresso": {
      "url": "https://mcp.oimpresso.com/api/mcp",
      "headers": {"Authorization": "Bearer mcp_..."}
    }
  }
}
```

### 8. Smoke autenticado

```bash
curl https://mcp.oimpresso.com/api/mcp/health/auth \
  -H "Authorization: Bearer mcp_..."
```

Retorna info do user + count de docs acessíveis.

## Atualização — caminho main→CT100 (ADR 0062 + 0256)

> O `deploy.yml` é **Hostinger-only** (ADR 0062 separa os runtimes), então NÃO há
> CI publicando aqui. O caminho canônico é o host se **auto-atualizar** (GitOps pull)
> por cron + uma **sentinela externa** que grita se isso parar.
> Origem: incidente 2026-06-17 — ~17 dias de código velho servido em silêncio (dados
> frescos da DB compartilhada mascaravam o drift do código).

### Deploy (canônico — script versionado, não comando solto)

```bash
tailscale ssh root@ct100-mcp
bash /opt/oimpresso-mcp/code/docker/oimpresso-mcp/scripts/self-update.sh
```

O `self-update.sh` é idempotente: `fetch` → se atrás de `origin/main`, faz backup dos
dirty files → `reset --hard origin/main` → `composer install` (só se lock mudou) →
rebuild (só se `docker/oimpresso-mcp/` mudou) → `up -d --force-recreate` → smoke.

**Duas pegadinhas que o `git pull` solto NÃO resolve** (catalogadas no incidente):
1. **`reset --hard origin/main`, não `git pull`** — main tem história reescrita; um
   merge quebra com *"no common ancestor"*.
2. **`--force-recreate` sempre** — `opcache.validate_timestamps=Off` no container;
   sem recreate o código novo no bind-mount **não sobe** (drift silencioso).

### Cron (instalar UMA vez no host — única config de host permitida)

```bash
# crontab -e (root no CT 100)
*/15 * * * * flock -n /tmp/mcp-self-update.lock /opt/oimpresso-mcp/code/docker/oimpresso-mcp/scripts/self-update.sh >> /opt/oimpresso-mcp/logs/self-update.log 2>&1
```

### Sentinela de drift (não precisa de tailscale nem secret)

`.github/workflows/mcp-drift-sentinel.yml` roda no GitHub a cada 30min: compara o
campo `commit` de `/api/mcp/health` com o HEAD de `main`. Se servido ficar > 6h atrás
(`MCP_DRIFT_MAX_LAG_HOURS`), **alarma**: workflow vermelho + issue rotulada `mcp-drift`
(o "inbox ops"). O commit servido é escrito pelo `entrypoint-octane.sh` a cada boot.

**NÃO usar Portainer Stacks** — Portainer fica só pra UI de logs/debug.
Edição via UI gera divergência com o git (ver discussão sessão 29-abr-2026).

## Migrations

`mcp_*` tables foram criadas no Hostinger MySQL via app principal:

```bash
# No Hostinger
ssh -p 65002 u906587222@148.135.133.115
cd ~/domains/oimpresso.com/public_html
php artisan migrate --path=Modules/Copiloto/Database/Migrations
```

Container MCP só LÊ/ESCREVE — não roda migrations próprias.

## Logs

```bash
# nginx + php-fpm via supervisord
docker compose logs -f mcp

# tunnel SSH
docker compose logs -f tunnel

# Acesso direto ao container
docker compose exec mcp sh
```

## Troubleshooting

### tunnel não inicia (healthcheck fail)
```bash
# Testa manual
docker compose run --rm tunnel sh -c "ssh -i /root/.ssh/id_ed25519_oimpresso -p 65002 u906587222@148.135.133.115 'echo OK'"
```

### cert TLS não emite
```bash
# Veja logs Traefik
docker logs traefik 2>&1 | grep mcp.oimpresso.com
# Confirme DNS resolveu pra 177.74.67.30
nslookup mcp.oimpresso.com
```

### MCP retorna 502
```bash
# nginx vê PHP-FPM?
docker compose exec mcp sh -c "nc -zv localhost 9000"
# bind mount tem código?
docker compose exec mcp ls -la /var/www/html/public/index.php
```

## ADRs relacionados
- 0053 — MCP server da empresa (decisão estratégica)
- 0042 — Reverb (mesmo padrão Traefik label)
- 0043 — Docker+Traefik vs LXC nativo
- 0045 — Hostinger DNS API (subdomínio criado via API)
