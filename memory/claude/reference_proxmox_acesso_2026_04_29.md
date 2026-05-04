---
name: Proxmox CT 100 — acesso + bootstrap services
description: Receita pra recuperar acesso ao CT 100 docker-host + subir novo serviço Docker. Aprendido na sessão MCP server 29-abr-2026
type: reference
originSessionId: 32066199-13c2-4cc8-922b-d65034040e23
---
## Acesso ao CT 100 (docker-host LXC Debian 12)

### Por que precisa fazer manual via console
- **Porta SSH 22 NÃO está exposta publicamente** (TP-Link NAT só 443+8006)
- **Proxmox API REST não tem `pct exec`** — só `vncproxy`/`termproxy` (WebSocket interativo)
- **Portainer API** só roda exec dentro de containers Docker, não no host LXC

### Caminho único hoje (humano via web)
1. Browser: `https://177.74.67.30:8006`
2. Login Proxmox: `root@pam` (senha em Vaultwarden)
3. Painel esquerdo → `100 (docker-host)` → botão **Console**
4. Login Debian no console: `root` / `4R781JvuwYiWqJgTea8oHw`

### Soluções permanentes (futuro — escolher 1)
- **Tailscale** ⭐ recomendado (5 min setup; free <100 devices)
- **SSH com whitelist IP** (TP-Link NAT 22XXX → .50:22)
- **MCP server `infra.exec_ct100` tool** (depois do MCP estar up)
- **Cloudflare Tunnel** no CT 100

## Bootstrap one-shot pra subir novo serviço Docker

Cola no console CT 100 após login (depois de ajustar APP_KEY/DB_PASSWORD que vêm do Hostinger):

```bash
# Pré-requisito: SSH ao Hostinger funcional + tem APP_KEY/DB_PASSWORD em mãos
# Pegar com:
#   ssh -p 65002 u906587222@148.135.133.115 'grep -E "^(APP_KEY|DB_PASSWORD)=" .env'

SERVICE_DIR="/opt/<NOME-SERVICO>"
mkdir -p $SERVICE_DIR/{ssh,storage}
cd $SERVICE_DIR

# 1. Clone repo (se for usar código do projeto)
git clone https://github.com/wagnerra23/oimpresso.com.git code
cd code && git pull && cd ..

# 2. Gerar SSH key pra tunnel/auth (idempotente)
[ -f ssh/id_ed25519 ] || ssh-keygen -t ed25519 -f ssh/id_ed25519 -N "" -q
chmod 600 ssh/id_ed25519

# 3. Criar .env com valores reais (substitua antes de colar)
cat > code/docker/<servico>/.env <<EOF
APP_KEY=base64:<COPIAR_DO_HOSTINGER>
DB_PASSWORD=<COPIAR_DO_HOSTINGER>
# ...etc
EOF

# 4. Mostrar pubkey pra adicionar no Hostinger (remoto)
cat ssh/id_ed25519.pub
```

Depois do humano adicionar pubkey via SSH no Hostinger:

```bash
# Validar tunnel
ssh -i ssh/id_ed25519 -o StrictHostKeyChecking=accept-new \
    -p 65002 u906587222@148.135.133.115 'echo OK'

# Build + up (idempotente)
cd code/docker/<servico>
docker compose build && docker compose up -d
docker compose logs -f tunnel
```

## Receita pra adicionar pubkey no Hostinger via SSH (Claude/dev faz)

```bash
PUBKEY="ssh-ed25519 AAAA... user@host"
ssh -4 -i ~/.ssh/id_ed25519_oimpresso -p 65002 u906587222@148.135.133.115 "
  mkdir -p ~/.ssh && chmod 700 ~/.ssh
  grep -qF '$PUBKEY' ~/.ssh/authorized_keys 2>/dev/null \
    || echo '$PUBKEY' >> ~/.ssh/authorized_keys
  chmod 600 ~/.ssh/authorized_keys
"
```

## Padrão "compose-managed, Portainer-observed" (ADR 0053)

- Source-of-truth: `docker-compose.yml` versionado em git
- Deploy: `docker compose up -d` SSH'ado no CT 100 (NÃO Portainer Stacks)
- Portainer fica só pra UI logs/exec/restart

Razão: Portainer Stacks tem limitações conhecidas com compose-spec recente
(`profiles:`, `extends:`, `develop:` etc).

## Receita Traefik labels (subdomínio + cert TLS automático)

```yaml
labels:
  - "traefik.enable=true"
  - "traefik.docker.network=docker-host_default"
  - "traefik.http.routers.X.rule=Host(`X.oimpresso.com`)"
  - "traefik.http.routers.X.entrypoints=websecure"
  - "traefik.http.routers.X.tls=true"
  - "traefik.http.routers.X.tls.certresolver=letsencrypt"
  - "traefik.http.services.X.loadbalancer.server.port=PORTA"
  # HTTP→HTTPS redirect
  - "traefik.http.routers.X-http.rule=Host(`X.oimpresso.com`)"
  - "traefik.http.routers.X-http.entrypoints=web"
  - "traefik.http.routers.X-http.middlewares=X-redirect"
  - "traefik.http.middlewares.X-redirect.redirectscheme.scheme=https"

networks:
  docker-host_default:
    external: true
```

## SSH tunnel ao Hostinger MySQL via autossh sidecar

Pattern em `docker/oimpresso-mcp/docker-compose.yml`:

```yaml
tunnel:
  image: kroniak/ssh-client:3.20
  command: >
    sh -c "apk add --no-cache autossh netcat-openbsd &&
      autossh -M 0
        -o ServerAliveInterval=30
        -o ServerAliveCountMax=3
        -o ExitOnForwardFailure=yes
        -N -L 0.0.0.0:3306:127.0.0.1:3306
        -p 65002 -i /root/.ssh/id_ed25519_oimpresso
        u906587222@148.135.133.115"
  volumes:
    - /opt/<servico>/ssh:/root/.ssh:ro
  healthcheck:
    test: ["CMD-SHELL", "nc -z localhost 3306"]
```

App principal usa `DB_HOST=tunnel`.

## Troubleshooting comum

### Cert TRAEFIK DEFAULT (em vez de Let's Encrypt)
- Container não está com label `traefik.enable=true`
- Container não está em `docker-host_default` network
- DNS não propagou (verificar `nslookup mcp.oimpresso.com`)

### 504 Gateway Timeout do Traefik
- Container está em network errada — confirma com `docker inspect`
- Healthcheck do Traefik não passa — checa porta interna

### Tunnel SSH não conecta
- Pubkey não adicionada em `~/.ssh/authorized_keys` no Hostinger
- Permissão errada na chave (`chmod 600 ssh/id_ed25519_oimpresso`)
- Hostinger não aceita ed25519 (raro; testar com `-o PubkeyAcceptedKeyTypes=+ssh-rsa`)

## Credenciais críticas (ver Vaultwarden)

| Sistema | User | Senha |
|---|---|---|
| Proxmox web | `root@pam` | (Vaultwarden) |
| CT 100 root | `root` | `4R781JvuwYiWqJgTea8oHw` |
| Portainer | `admin` | `Infra@Docker2026!` |
| Hostinger SSH | `u906587222` | (chave id_ed25519_oimpresso) |
| Hostinger MySQL | `u906587222_oimpresso` | `Wscrct*2312` |

⚠️ Estas credenciais devem estar TAMBÉM no Vaultwarden — esta auto-mem é cache local Wagner-Claude. Se Vaultwarden cair, este arquivo é fallback.
