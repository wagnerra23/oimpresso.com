# `infra/proxmox/docker-host` — stack Docker do CT 100

> **Onde roda:** CT 100 (LXC unprivileged Debian 12) no servidor Proxmox da empresa, IP LAN `192.168.0.50`. Ver [INFRA.md §6.1](../../../INFRA.md).

## O que tem aqui

| Arquivo | Função |
|---|---|
| `compose.yml` | Stack Docker: Traefik + Portainer (Reverb adicionado em PR seguinte) |
| `.env.example` | Template das variáveis. Copiar pra `.env` no CT e preencher (gitignored) |

## Por que Docker+Traefik+Portainer (não LXC nativo)

Ver [ADR 0043](../../../memory/decisions/0043-docker-host-traefik-vs-lxc-nativo.md). TL;DR: Traefik resolve TLS automático com Let's Encrypt e descobre serviços via labels; stack vira código versionado (`compose.yml`); Portainer dá UI familiar; 1 VM gerencia melhor que N CTs separados.

## Pré-requisitos pra subir o stack

### 1. DNS (Cloudflare ou onde estiver o `oimpresso.com`)

Criar 3 A records, **proxy DESLIGADO** (laranja-OFF se Cloudflare — senão WebSocket quebra):

```
reverb.oimpresso.com      A   177.74.67.30
portainer.oimpresso.com   A   177.74.67.30
traefik.oimpresso.com     A   177.74.67.30
vault.oimpresso.com       A   177.74.67.30
```

### 2. Port forwards no router TP-Link (192.168.0.1 → Direcionamento NAT → Servidores Virtuais)

| Ação | Regra | Porta Ext | IP Interno | Porta Int | Proto |
|---|---|---|---|---|---|
| Editar #3 (https) | https | 443 | **192.168.0.50** (era .2) | 443 | TCP |
| Adicionar nova | http | **80** | 192.168.0.50 | 80 | TCP |

A regra **80** é obrigatória pro Let's Encrypt validar via HTTP-01 challenge.

### 3. Acesso ao CT

```
ssh -i ~/.ssh/id_ed25519_oimpresso root@192.168.0.50
```

(Chave já injetada na criação do CT.)

## Como deployar (primeira vez)

```bash
# No CT, como root:
mkdir -p /opt/docker-host && cd /opt/docker-host

# Copiar compose.yml e .env.example desse diretório do repo
# (sugestão: clonar o repo em /opt/oimpresso e fazer ln -s)

cp .env.example .env
vim .env   # preencher TRAEFIK_DASHBOARD_AUTH e ACME_EMAIL

# Gerar bcrypt do dashboard auth:
docker run --rm httpd:2.4-alpine htpasswd -nbB admin SUA_SENHA
# Cole o output em TRAEFIK_DASHBOARD_AUTH (escapar $ → $$)

docker compose up -d
docker compose logs -f traefik
# Esperar log "Server registered" e "Certificate obtained successfully"
```

## Smoke test pós-deploy

```bash
# Em qualquer máquina externa:
curl -I https://traefik.oimpresso.com/        # 401 (basic auth) ou 200 — ok
curl -I https://portainer.oimpresso.com/      # 200 — ok
openssl s_client -connect traefik.oimpresso.com:443 -servername traefik.oimpresso.com < /dev/null 2>&1 | grep -E "issuer|subject"
# Esperar: issuer=Let's Encrypt
```

## Atualizar o stack

```bash
cd /opt/docker-host
docker compose pull       # baixar imagens novas
docker compose up -d      # recriar com novas
docker image prune -f     # limpar antigas
```

## Backup

- `traefik-acme` (volume Docker) — contém `acme.json` (certificados Let's Encrypt). Se perder, refaz HTTP-01.
- `portainer-data` (volume Docker) — config Portainer (users, stacks, endpoints).

```bash
# Backup manual de volumes:
docker run --rm -v traefik-acme:/data -v $(pwd):/backup alpine \
  tar czf /backup/traefik-acme-$(date +%Y%m%d).tar.gz -C /data .
docker run --rm -v portainer-data:/data -v $(pwd):/backup alpine \
  tar czf /backup/portainer-data-$(date +%Y%m%d).tar.gz -C /data .
```

## Próximos passos

- [ ] Adicionar serviço `reverb` no compose com Dockerfile próprio (build do repo, runtime PHP 8.4) — PR separado
- [ ] Adicionar `meilisearch` (substituir o binário standalone do Hostinger pelo container)
- [ ] Apertar regra port forward #10 (`servidores 7000-8049`) pra **excluir 8006** — painel Proxmox tá publicamente exposto
