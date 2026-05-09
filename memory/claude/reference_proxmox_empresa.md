---
name: Servidor Proxmox empresa (ativo de homologação)
description: Hardware dedicado disponível na empresa pra rodar daemons (Reverb, Meilisearch, workers) que Hostinger compartilhado não suporta — ver INFRA.md §6.1
type: reference
originSessionId: 32066199-13c2-4cc8-922b-d65034040e23
---
Wagner declarou em 2026-04-28: existe na empresa uma máquina física com **128 GB RAM, 2 TB HD, Proxmox VE instalado, IP fixo**, status ativo e disponível.

**Quando usar:** qualquer serviço que precise de daemon persistente, controle de portas, supervisor, ou config nginx custom — onde Hostinger compartilhado bloqueia (sem supervisord, sem systemd-user, sem controle nginx, hPanel cron só resolve heartbeat).

**Casos de uso já mapeados:**
1. **Reverb** (WebSocket broadcaster) — ADR 0042, [PR #64](https://github.com/wagnerra23/oimpresso.com/pull/64)
2. **Meilisearch** (memória semântica Jana) — A4 Felipe + ADR 0036
3. **Horizon + Vizra ADK workers** — ADR 0035 (Larissa, FaithCheck, etc.)
4. **VM staging** — réplica pré-produção (mitiga histórico de crashes diretos em prod)

**Não usar pra:** o app PHP-FPM principal (`oimpresso.com`) — fica no Hostinger Cloud Startup. Proxmox é só pra daemons/serviços auxiliares.

**Detalhes pendentes** (Wagner preencher em INFRA.md §6.1): IP público fixo, hostname, upload Mbps, acesso SSH, URL painel Proxmox.

**Riscos lembrados:** energia/link da empresa (UPS + 4G failover), backup off-site (snapshot Proxmox não sai do HW), DNS via Cloudflare laranja-off pra WS nativo.

**Documentação canônica:** [INFRA.md §6.1](INFRA.md). Atualizada na sessão Reverb install 2026-04-28 (PR #64).
