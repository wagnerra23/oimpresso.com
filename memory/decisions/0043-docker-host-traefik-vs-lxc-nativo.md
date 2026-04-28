# ADR 0043 — Docker + Traefik + Portainer num LXC, em vez de N LXCs nativos

**Status:** ✅ Aceita
**Data:** 2026-04-28
**Escopo:** Plataforma — provisionamento dos serviços-daemon (Reverb, Meilisearch, workers Vizra ADK) no servidor Proxmox da empresa
**Decisor:** Wagner [W] (preferência declarada: "Docker com Traefik, Portainer que era o que eu usava")
**Implementador:** Claude (sessão 2026-04-28)
**Branch:** `claude/reverb-install`

---

## Contexto

[ADR 0042](0042-reverb-substitui-pusher-cloud.md) decidiu que o deploy do Reverb iria pro Proxmox da empresa (Hostinger compartilhado é inviável — sem supervisord, sem controle nginx).

A primeira proposta era **1 LXC nativo por serviço** (CT 100 reverb, CT 101 meilisearch, CT 102 workers, etc.), com nginx + certbot dentro de cada um. Wagner pediu uma alternativa: **Docker com Traefik e Portainer**, stack que ele já conhecia.

## Decisão

**Adotar:** 1 CT LXC unprivileged (`docker-host`, 192.168.0.50) hospedando Docker Engine 29 + Compose v5; **todos os serviços-daemon** (Reverb, Meilisearch, workers, etc.) ficam em containers Docker dentro desse CT, gerenciados via `compose.yml` versionado em [`infra/proxmox/docker-host/`](../../infra/proxmox/docker-host/).

**Reverse-proxy:** Traefik 3.5 com Let's Encrypt automático (HTTP-01 challenge), descobre serviços via labels Docker.

**UI:** Portainer CE LTS, exposto em `portainer.wr2.com.br` via Traefik.

## Alternativas consideradas

| Opção | Por que não foi a escolhida |
|---|---|
| **N LXCs nativos** (1 CT por serviço, nginx+certbot em cada) | Cada serviço novo precisa ser provisionado manualmente: criar CT, instalar nginx, gerar cert, configurar reverse-proxy. Não é IaC. Desgastante quando crescer pra 4-5 serviços. |
| **VM única com Docker** (em vez de LXC) | Tecnicamente mais isolada, mas overhead Docker (~5-10%) + boot 30s vs 2s do LXC. Pra workloads PHP+Go+Rust, LXC + nesting+keyctl funciona bem. Se aparecer quirk grave (ex.: Meilisearch precisar de mmap/lockmem que LXC unprivileged bloqueia), migrar pra VM é trivial — o `compose.yml` move junto. |
| **Kubernetes (k3s)** | Overhead absurdo pra 4-5 serviços. Curva de aprendizado. Não justifica. |
| **Manter LXC nativo + ansible/terraform** | Volta ao mesmo overhead operacional do "nativo". Compose YAML é mais simples e Wagner já entende. |

## Consequências

**Positivas:**

- **Stack como código.** `compose.yml` no repo, próximo serviço é só adicionar bloco. Reproducible.
- **TLS automático via Traefik.** Labels `traefik.http.routers.X.rule=Host(...)` + `tls.certresolver=le` = cert Let's Encrypt emitido e renovado sozinho. Zero `certbot --renew` no cron.
- **Portainer dá UI familiar pro Wagner** (já era a ferramenta dele — vi no port forward #11 da tabela NAT do TP-Link).
- **1 CT pra gerenciar** em vez de N. Snapshots Proxmox cobrem todo o stack atomicamente.
- **Migração entre máquinas é trivial** — `compose.yml` + volumes Docker movem juntos.
- **Reverb container** consome ~50 MB RAM ocioso, ~150 MB com 100 conexões WS. 8 GB do CT comporta Reverb + Meilisearch + Portainer + Traefik com folga.

**Negativas / dívidas técnicas assumidas:**

- **+1 camada de troubleshooting** (Docker → container → app). Quando der pau, é mais lugar pra olhar log.
- **Quirks de Docker em LXC unprivileged** (storage driver overlay2 vs fuse-overlayfs, alguns kernel modules bloqueados). Validamos hello-world — funciona. Se aparecer issue específica, fallback é VM.
- **Ponto único de falha**: 1 CT cai = todo o stack cai. Mitigação: snapshots Proxmox + onboot=1.
- **Imagem Docker ≠ código fonte versionado**. Reverb precisa de `Dockerfile` no repo que clone+`composer install` em build time, ou volume mount do código. Decisão de arquitetura adiada pra PR seguinte.

**Neutro:**

- LXC unprivileged + features `nesting=1,keyctl=1` é o setup recomendado pelo Proxmox 9 pra Docker. Validado.

## Plano de implementação

**Feito (sessão 2026-04-28, branch `claude/reverb-install`):**

1. ✅ Criar CT 100 `docker-host` via API REST Proxmox (vmid=100, 4 vCPU, 8 GB RAM, 60 GB disk em local-lvm, IP 192.168.0.50/24, unprivileged, nesting+keyctl, onboot=1, SSH key Claude injetada).
2. ✅ Provisionar dentro: apt update, ca-certificates+curl+gnupg+git, repo Docker oficial, instalar `docker-ce + docker-compose-plugin`. Smoke test `docker run hello-world` OK.
3. ✅ Criar [`infra/proxmox/docker-host/`](../../infra/proxmox/docker-host/) no repo:
   - [`compose.yml`](../../infra/proxmox/docker-host/compose.yml) — Traefik 3.5 + Portainer CE LTS
   - [`.env.example`](../../infra/proxmox/docker-host/.env.example) — template ACME_EMAIL + TRAEFIK_DASHBOARD_AUTH
   - [`README.md`](../../infra/proxmox/docker-host/README.md) — pré-requisitos (DNS + port forwards), deploy steps, smoke test, backup

**Pendências (Wagner — fora do código):**

- 🟡 Criar A records DNS: `reverb / portainer / traefik.wr2.com.br → 177.74.67.30` (proxy OFF)
- 🟡 Editar TP-Link: regra #3 (https) IP Interno `192.168.0.2 → 192.168.0.50`; adicionar nova regra `80 → 192.168.0.50:80`

**Pendências (Claude — PR seguinte):**

- 🟢 Adicionar serviço `reverb` no `compose.yml` com `Dockerfile` próprio (build do repo, runtime PHP 8.4 + reverb)
- 🟢 Adicionar serviço `meilisearch` (substitui binário standalone do Hostinger)
- 🟢 Apertar regra port forward #10 (`servidores 7000-8049`) pra excluir 8006 — painel Proxmox tá publicamente exposto

**Smoke test ponta-a-ponta:**

Vai rodar quando DNS + port forwards prontos. Critérios:
1. `curl -I https://traefik.wr2.com.br/` → 401 (basic auth) com cert Let's Encrypt válido
2. `curl -I https://portainer.wr2.com.br/` → 200 com cert válido
3. Painel Portainer abre normalmente do browser externo

## Rollback

- **Stack down**: `cd /opt/docker-host && docker compose down`
- **Stack remove com volumes**: `docker compose down -v` (perde acme.json + Portainer config — refaz)
- **CT remove completo**: `pct stop 100 && pct destroy 100` no Proxmox host (5s pra recriar via mesmo `pct create` documentado)
- **Reverter port forwards**: editar TP-Link de volta pra `192.168.0.2`

## Relacionadas

- [ADR 0035](0035-stack-ai-canonica-wagner-2026-04-26.md) — Stack canônica IA
- [ADR 0042](0042-reverb-substitui-pusher-cloud.md) — Reverb substitui Pusher cloud
- [INFRA.md §6.1](../../INFRA.md) — Servidor Proxmox empresa (specs + acesso)

---

**Resumo executivo (1 linha):** 1 CT LXC `docker-host` com Docker+Traefik+Portainer hospeda todos os daemons (Reverb, Meilisearch, workers) — TLS automático via labels, stack versionada em `compose.yml`, UI familiar pro Wagner, 8 GB de RAM cobrem com folga.
