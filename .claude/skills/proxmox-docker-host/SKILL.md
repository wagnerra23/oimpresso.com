---
name: proxmox-docker-host
description: Use ao mexer com infra Proxmox/CT 100/containers Docker do oimpresso. Carrega receitas: subir novo subdomínio Traefik, criar container compose-managed, autossh tunnel pro Hostinger MySQL, troubleshoot Portainer vs compose CLI. Substitui leitura de INFRA.md §6 + ADRs 0042-0045.
---

# Proxmox + Docker Host — receitas operacionais

## Topologia

```
Proxmox VE 9.1.1 (sistema)
    ├── IP público: 177.74.67.30 (TP-Link 192.168.0.1 NAT)
    ├── IP LAN:     192.168.0.2
    ├── Painel:     :8006 (exposto publicamente)
    └── CT 100 docker-host (LXC Debian 12)
          ├── IP LAN: 192.168.0.50
          ├── Hostname: docker-host
          └── Containers Docker:
                ├── traefik       (v3.6, TLS automático)
                ├── portainer     (UI observação — NÃO source-of-truth)
                ├── vaultwarden
                ├── reverb        (Laravel WebSocket)
                ├── meilisearch   (v1.10.3, vector search)
                └── oimpresso-mcp (em construção — ADR 0053)
```

## Acesso

| Serviço | URL/IP | Credencial |
|---|---|---|
| Proxmox web | `https://177.74.67.30:8006` | root@pam |
| Proxmox API token | — | `root@pam!mcp2=e15a341f-cd82-4d99-8fd7-8f3b4d17a09b` |
| Portainer | `https://portainer.oimpresso.com` | admin / `Infra@Docker2026!` |
| CT 100 SSH | (porta 22 NÃO exposta publicamente) | root / senha em Vaultwarden |
| Acesso CT 100 alternativo | Proxmox web → CT 100 → Console | terminal direto |

## Padrão deploy: compose-managed, Portainer-observed (ADR 0053 §discussão)

**NUNCA usar Portainer Stacks** como source-of-truth (limitações de spec). Em vez disso:

1. `docker-compose.yml` versionado em git no repo
2. Deploy via `docker compose up -d` SSH'ado no CT 100
3. Portainer só pra **logs/exec/restart** (UI sem ser owner)

## Receita: Subir novo serviço com subdomínio + cert TLS automático

### 1. Criar DNS A record (já tem receita — ADR 0045)

```bash
TOKEN="g8JeEn9GsgBlVhsk9uSyxNBwaZpYRFk9zNdQj0Gm7ca72750"
curl -s -X PUT \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  "https://developers.hostinger.com/api/dns/v1/zones/oimpresso.com" \
  -d '{
    "overwrite": false,
    "zone": [{
      "name": "novo-servico",
      "type": "A",
      "ttl": 300,
      "records": [{"content": "177.74.67.30"}]
    }]
  }'
```

Propaga em ~30s.

### 2. Criar `docker-compose.yml` no repo com Traefik labels

Padrão consolidado (ver `docker/oimpresso-mcp/docker-compose.yml` como referência):

```yaml
services:
  meu-servico:
    image: ...
    networks:
      - docker-host_default
    labels:
      - "traefik.enable=true"
      - "traefik.docker.network=docker-host_default"
      - "traefik.http.routers.meu.rule=Host(`novo-servico.oimpresso.com`)"
      - "traefik.http.routers.meu.entrypoints=websecure"
      - "traefik.http.routers.meu.tls=true"
      - "traefik.http.routers.meu.tls.certresolver=letsencrypt"
      - "traefik.http.services.meu.loadbalancer.server.port=PORTA"
      # HTTP → HTTPS
      - "traefik.http.routers.meu-http.rule=Host(`novo-servico.oimpresso.com`)"
      - "traefik.http.routers.meu-http.entrypoints=web"
      - "traefik.http.routers.meu-http.middlewares=meu-redirect"
      - "traefik.http.middlewares.meu-redirect.redirectscheme.scheme=https"

networks:
  docker-host_default:
    external: true
```

### 3. Deploy via SSH

```bash
ssh root@<ct-100>
cd /opt/<servico>
git pull
docker compose up -d
```

## Receita: SSH tunnel pro Hostinger MySQL (autossh sidecar)

Padrão usado no `oimpresso-mcp` — sidecar separado mantendo túnel persistente:

```yaml
tunnel:
  image: kroniak/ssh-client:3.20
  command: >
    sh -c "
      apk add --no-cache autossh netcat-openbsd &&
      autossh -M 0
        -o 'ServerAliveInterval=30'
        -o 'ServerAliveCountMax=3'
        -o 'ExitOnForwardFailure=yes'
        -N -L 0.0.0.0:3306:127.0.0.1:3306
        -p 65002 -i /root/.ssh/id_ed25519_oimpresso
        u906587222@148.135.133.115
    "
  volumes:
    - /opt/<servico>/ssh:/root/.ssh:ro
  healthcheck:
    test: ["CMD-SHELL", "nc -z localhost 3306"]
```

App principal usa `DB_HOST=tunnel, DB_PORT=3306` (alias rede Docker).

## Troubleshooting

### Cert Let's Encrypt não emite
```bash
# 1. DNS resolveu pro IP certo?
nslookup novo-servico.oimpresso.com
# 2. Traefik logs mostram challenge?
docker logs traefik 2>&1 | grep novo-servico
# 3. Container está em docker-host_default?
docker inspect oimpresso-mcp | grep NetworkMode
```

### 504 Gateway Timeout do Traefik
Geralmente: container está em rede errada. Confirmar:
```bash
docker network inspect docker-host_default | grep <container>
```

### Portainer mostra estado divergente
Portainer cacheia stacks. Se rodou `docker compose up` direto, cacheada UI fica errada:
```bash
# Pra Portainer "esquecer" e mostrar atual:
# Settings → Stacks → Migrate to Compose → escolhe arquivo do disco
```

## Padrão de credenciais

- **NUNCA** commitar `.env` com senhas reais (ADR 0030)
- `.env.example` com placeholders fica no repo
- `.env` real fica em `/opt/<servico>/.env` no CT 100, gitignored
- SSH keys em `/opt/<servico>/ssh/`, chmod 600, gitignored

## Receita pra MCP server (ADR 0053)

Bootstrap one-shot:
```bash
ssh root@<ct-100>  # ou pct enter 100 do Proxmox host
curl -fsSL https://raw.githubusercontent.com/wagnerra23/oimpresso.com/main/docker/oimpresso-mcp/scripts/bootstrap-ct100.sh | bash
```

Ou ver docs completos em `docker/oimpresso-mcp/README.md`.
