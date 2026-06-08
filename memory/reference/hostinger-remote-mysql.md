---
name: Hostinger Remote MySQL direto — srv1818.hstgr.io:3306
description: Caminho canônico CT 100 → Hostinger MySQL. autossh REJEITADO 29-abr-2026 (Connection timed out 65002). Solução Remote MySQL whitelist hPanel + DIRETO srv1818.hstgr.io:3306. Senha real no Vaultwarden.
type: reference
---
# Hostinger Remote MySQL — acesso direto do CT 100

## Decisão arquitetural (29-abr-2026)

**autossh tunnel CT 100 → Hostinger:65002 REJEITADO** — `Connection timed out` consistente (provável bloqueio fail2ban/firewall stateful upstream Hostinger). `nc -zv` reporta porta open mas SSH handshake não completa.

**Solução adotada**: ativar **Remote MySQL no hPanel Hostinger** pra IP 177.74.67.30 (CT 100 público) + conectar direto na porta 3306.

## Credenciais MySQL (canônico)

| Campo | Valor |
|---|---|
| Host | `srv1818.hstgr.io` (resolve `193.203.166.217`) |
| Port | `3306` |
| User | `u906587222_oimpresso` |
| Database | `u906587222_oimpresso` |
| Senha | **valor no Vaultwarden** (item `hostinger-mysql-oimpresso`) |

**`srv1818.hstgr.io` NÃO é `148.135.133.115`** — o IP `148.135.133.115` é web/SSH (porta 65002). MySQL fica em servidor separado Hostinger.

## Pre-req do hPanel

Wagner liberou Remote MySQL whitelist no hPanel pra IP **177.74.67.30** (IP público empresa = CT 100 NAT). Sem isso, MySQL Hostinger recusa conexões externas.

Como verificar/adicionar: hPanel → Sites → oimpresso.com → Avançado → Remote MySQL → IP whitelist.

## Testar conexão (do CT 100)

```bash
tailscale ssh root@ct100-mcp 'docker run --rm mysql:8 mysql \
  -h srv1818.hstgr.io \
  -u u906587222_oimpresso \
  -p<senha do Vaultwarden> \
  -e "SELECT VERSION(); SHOW DATABASES;"'
```

Resultado esperado:
```
VERSION()
11.8.6-MariaDB-log
Database
information_schema
u906587222_oimpresso
```

**MySQL Hostinger roda MariaDB 11.8.6** (não MySQL 8) — alguns SQL constructs podem diferir.

## Pattern de uso em compose Docker

**Daemon whatsapp-baileys** (2026-05-12 — auth state migration):

`/opt/whatsapp-baileys/build/.env` (mode 600, não commitado):
```env
AUTH_STATE_BACKEND=mysql
WHATSAPP_AUTH_STATE_ENCRYPTION_KEY=<APP_KEY base64 do .env Hostinger>
MYSQL_AUTH_STATE_HOST=srv1818.hstgr.io
MYSQL_AUTH_STATE_PORT=3306
MYSQL_AUTH_STATE_USER=u906587222_oimpresso
MYSQL_AUTH_STATE_PASS=<valor Vaultwarden>
MYSQL_AUTH_STATE_DB=u906587222_oimpresso
```

`docker-compose.yml`:
```yaml
services:
  whatsapp-baileys:
    env_file:
      - .env
    environment:
      # ... outras vars
```

`docker compose up -d whatsapp-baileys` recreate container pegando novos env vars (sem rebuild full).

## Por que NÃO usar mysql-workers local (CT 100)

`mysql-workers` (`/opt/oimpresso-mysql/docker-compose.yml`) roda MySQL 8 LOCAL no CT 100 porta 3306 → é banco **diferente** (`oimpresso_workers`), não o banco prod Hostinger. Usar pra workers Horizon, não pra dados aplicação.

Pra confirmar qual MySQL está ouvindo onde:
```bash
tailscale ssh root@ct100-mcp 'ss -tlnp | grep 3306'
# 127.0.0.1:3306 docker-proxy = mysql-workers local
```

## Pattern adoptado por outros services CT 100

`oimpresso-mcp` (MCP server canon) usa mesmo pattern:
```yaml
DB_HOST: srv1818.hstgr.io
DB_PORT: 3306
```

`oimpresso-app` no Hostinger usa `DB_HOST: localhost` (mesma máquina). Daemon CT 100 = remote → `srv1818.hstgr.io`.

## Pegadinhas

1. **MariaDB ≠ MySQL** — `SHOW VARIABLES LIKE 'version'` retorna `11.8.6-MariaDB-log`. Algumas funções diferem (JSON syntax, window functions parcial em 10.x).
2. **`localhost` no GRANT MySQL Hostinger pode não cobrir `::1`** (IPv6) — daemon Node mysql2 precisa força IPv4 ou usar `127.0.0.1` direto.
3. **Conexões simultâneas limitadas** pelo plano Hostinger (~100 conn/account no Cloud Startup). Daemon Baileys + Laravel app + cron + workers compartilham pool — atenção a leaks.
4. **TLS opcional**: Hostinger suporta TLS MySQL mas não é mandatório. Pra dados sensíveis (auth state encryption WhatsApp), AES-256-CBC application-level é suficiente.

## Lição

Wagner em 2026-05-12 perguntou "onde tá a porta?" — eu tinha pensado em montar autossh sidecar do zero. Resposta era: **JÁ ESTÁ LIBERADO** (whitelist hPanel + srv1818.hstgr.io) — pattern documentado mas eu não lembrei até pesquisar memória.

**Sempre antes de propor solução de infra**: pesquisar infra-proxmox-ct100.md + hostinger.md + grep `/opt/*/docker-compose.yml` por patterns existentes.
