---
name: Padrão de infraestrutura da empresa (Proxmox + Docker + Traefik)
description: Arquitetura canônica decidida em 2026-04-28 — onde cada tipo de serviço roda e por quê
type: project
originSessionId: 94b4bbe1-c312-4bfe-8bd6-9c89d0340bca
---
## Decisão canônica: onde cada coisa roda

| Camada | Onde | Tecnologia | Motivo |
|--------|------|------------|--------|
| App PHP principal (`oimpresso.com`) | **Hostinger Cloud Startup** | PHP-FPM 8.4 | Hosting gerenciado, SSL automático, suporte 24h |
| Daemons persistentes (Meilisearch, Reverb, Horizon workers) | **CT docker-host (192.168.0.50)** | Docker + Traefik | Hostinger compartilhado mata background processes; Proxmox tem 125 GB RAM / 2 TB HD disponível |
| Gerenciamento de infra | **Proxmox VE 9.1.1 (192.168.0.2)** | Painel + API | Hardware físico na empresa: Xeon E5-2680v4 14C |

**Por que Docker no CT e não diretamente no host Proxmox:**  
Isolamento, rollback via `docker compose down/up`, versionamento em `infra/proxmox/docker-host/compose.yml`, sem sujar o host Proxmox.

## Padrão de exposição externa

Todos os serviços do docker-host saem via **Traefik 3.6** com Let's Encrypt automático:
- Domínio: `*.oimpresso.com` (DNS em Hostinger — hPanel wagnerra@gmail.com)
- IP público empresa: `177.74.67.30` (ISP ateky.net.br)
- Port forward: `443 → 192.168.0.50:443` + `80 → 192.168.0.50:80` (ACME HTTP-01)
- Traefik dashboard: `https://traefik.oimpresso.com/` (BasicAuth admin / zrG8nSxI0DIcWEIe)
- Portainer: `https://portainer.oimpresso.com/` (admin / Infra@Docker2026!)

## Serviços no docker-host (todos em oimpresso.com)

| Serviço | Subdomínio | Status | Cert LE |
|---------|-----------|--------|---------|
| Traefik 3.6 | traefik.oimpresso.com | ✅ rodando | R13 expira 2026-07-27 |
| Portainer CE LTS 2.39.1 | portainer.oimpresso.com | ✅ rodando | R13 |
| Vaultwarden 1.35.8-alpine | vault.oimpresso.com | ✅ rodando | R12 expira 2026-07-27 |
| Reverb (Laravel WebSocket) | reverb.oimpresso.com | ✅ rodando — smoke ✅ | R12 |
| **Meilisearch v1.10.3** | meilisearch.oimpresso.com | ✅ container + Traefik OK + embedder OpenAI configurado + vector search end-to-end ✅ (validado 2026-04-28). **DNS A record AINDA não está no autoritativo** (NXDOMAIN em ns1.dns-parking.com) — Wagner precisa salvar no hPanel | aguarda DNS |
| Horizon workers | interno | ⏳ não iniciado | — |

**MEILI_MASTER_KEY:** `9c08945878571ecb76b70d25deb3852b` (salvar no Vaultwarden)
**REVERB_APP_KEY:** `5921152f-5c00-4bb6-92f0-0ed94a75c68d` (já no Hostinger .env)

**Para ligar DNS manualmente:** hPanel Hostinger → Domínios → oimpresso.com → DNS → A record `meilisearch` → `177.74.67.30` (Proxy OFF, TTL 3600)

## Acesso LAN (limitação)

SSH para 192.168.0.50 (docker-host) só funciona **na LAN da empresa** — sem VPN configurada.  
Quando remoto: usar Portainer web (`https://portainer.wr2.com.br/`) para gerenciar containers via browser.

**Why:** Nenhuma porta SSH está exposta publicamente no router para 192.168.0.50 ou 192.168.0.2.

## ⚠️ Gotcha: rede Docker do Traefik

Quando adicionar container novo ao docker-host **via API Portainer** (não via `docker compose up`), atenção:
- Traefik está na rede `docker-host_default` (criada implicitamente pelo `compose up`)
- Containers criados via API caem em `bridge` (Docker default) — Traefik **não consegue alcançar** → HTTP 504 Gateway Timeout
- **Sempre setar:** `HostConfig.NetworkMode: "docker-host_default"` + label `traefik.docker.network: "docker-host_default"`

Aprendido com Meilisearch 2026-04-28 (recriado para corrigir).

## Como apply: adicionar Meilisearch ao docker-host

1. SSH no docker-host OU usar Portainer web: adicionar serviço `meilisearch` ao `/opt/docker-host/compose.yml`
2. Adicionar DNS `meilisearch.wr2.com.br → 177.74.67.30` no KingHost
3. Atualizar `.env` Hostinger: `MEILISEARCH_HOST=https://meilisearch.wr2.com.br`
4. Traefik pega o cert automático via Let's Encrypt ACME

Receita Meilisearch no compose.yml — bloco a adicionar:
```yaml
  meilisearch:
    image: getmeili/meilisearch:v1.10.3
    restart: unless-stopped
    environment:
      MEILI_MASTER_KEY: "TFLfQX3Diuz42MydPn68AYH9Km1JbaBI"
      MEILI_ENV: "production"
      MEILI_NO_ANALYTICS: "true"
    volumes:
      - meilisearch_data:/meili_data
    labels:
      - "traefik.enable=true"
      - "traefik.http.routers.meilisearch.rule=Host(`meilisearch.wr2.com.br`)"
      - "traefik.http.routers.meilisearch.entrypoints=websecure"
      - "traefik.http.routers.meilisearch.tls.certresolver=letsencrypt"
      - "traefik.http.services.meilisearch.loadbalancer.server.port=7700"
    networks:
      - proxy

volumes:
  meilisearch_data:
```
