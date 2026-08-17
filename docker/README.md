# `docker/` — Containers do projeto oimpresso

Stacks Docker pros containers do CT 100 (Proxmox empresa).
ADR canônica: [`memory/decisions/0060-tudo-rede-interna-proxmox-bye-hostinger.md`](../memory/decisions/0060-tudo-rede-interna-proxmox-bye-hostinger.md).

## Containers atuais

> ⚠️ **A coluna "Status" foi separada em duas em 2026-08-16, e não é por estilo.** Ela era um campo
> escrito à mão que apodreceu: marcava `🔲 setup pendente` para serviços que a
> [auditoria Ops/DR de 2026-07-05](../memory/requisitos/Infra/AUDITORIA-OPS-DR-2026-07.md) já media
> rodando — incluindo o Centrifugo, cuja perda ela classifica como **P0** no SPOF-2. Um doc que chama
> de "pendente" o que está em produção manda o operador procurar trabalho que não existe, e esconder
> o que existe.
>
> **Este arquivo não declara mais runtime.** Ele diz o que o repositório PROVA (a stack está aqui?) e
> aponta a medição datada. Estado agora se pergunta ao runtime: `tailscale ssh root@ct100-mcp 'docker ps'`.

| Diretório | Subdomínio | Função | Stack versionada aqui | Medido rodando |
|---|---|---|---|---|
| `oimpresso-mcp/` | `mcp.oimpresso.com` | MCP server FrankenPHP (ADR 0053) | ✅ completa (Dockerfile + compose + entrypoints + bootstrap) | ✅ 2026-07-05 |
| `ollama-embedder/` | (interno LAN) | Embedder local Nomic/BGE-M3 (ADR 0060) | ✅ `docker-compose.yml` | ✅ 2026-07-05 (auditoria §Realtime/busca) |
| `oimpresso-workers/` | `workers.oimpresso.com` | Workers pesados Laravel (ADR 0060) | ✅ `docker-compose.yml` + `Caddyfile` | ❓ **não confirmado** — a auditoria lista `mysql-workers` (um MySQL), que não é o worker Laravel |
| ~~`centrifugo/`~~ | `realtime.oimpresso.com` | Realtime WS+SSE (ADR 0058) | ❌ **o diretório NÃO existe neste repo** — o compose e o `config.json` vivem só como heredoc dentro do [`RUNBOOK-deploy-centrifugo.md`](../memory/requisitos/Infra/RUNBOOK-deploy-centrifugo.md) | ✅ 2026-07-05 (e SPOF-2 o nomeia como perda **P0**) |

> 🔴 **A linha do Centrifugo é dívida real, não formatação.** Serviço em produção, classificado P0 na
> auditoria, com a configuração **não versionada** — recriá-lo hoje exige copiar heredoc de um runbook
> à mão. É o oposto do que o §D.2 do Plano Mestre chama de configuração versionada.

## Padrão arquitetural (ADR 0042 + 0060)

- **App principal Laravel** continua na **Hostinger** (Larissa estável SLA 99.9%)
- **Daemons/CPU-pesado/IA** → CT 100 docker-host (192.168.0.50)
- **Source-of-truth código:** GitHub (push → webhook → CT git pull)
- **Source-of-truth DB:** MySQL Hostinger (CT pode ter replica leitura futuro)
- **Source-of-truth secrets:** Vaultwarden (`vault.oimpresso.com`)

## Setup novo container (receita)

1. **Acessa Proxmox web:** `https://177.74.67.30:8006` (Wagner Vaultwarden creds)
2. **Console no CT 100** (docker-host, IP 192.168.0.50)
3. **Cria pasta:** `mkdir -p /opt/oimpresso-{nome}/code && cd /opt/oimpresso-{nome}`
4. **Clona repo (se precisar do código):** `git clone https://github.com/wagnerra23/oimpresso.com.git code`
5. **Copia compose deste repo** ou refaz a partir do template
6. **`.env` real** baseado no `.env.example` (nunca commitar `.env`)
7. **Up:** `docker compose up -d`
8. **Smoke:** `curl http://localhost:<porta>/health` interno + `https://<sub>.oimpresso.com/health` externo

## Troubleshooting

### Cert TRAEFIK DEFAULT em vez de Let's Encrypt
- Confirma `traefik.enable=true` na label
- Confirma `traefik.docker.network=docker-host_default` (network external correto)
- Confirma DNS A record do subdomínio aponta pra 177.74.67.30 (`nslookup ...`)

### 504 Gateway Timeout
- Container está em network errada? `docker inspect <container> | grep Network`
- Healthcheck falhando? `docker inspect --format='{{json .State.Health}}' <container>`
- Porta interna do `loadbalancer.server.port` não bate com EXPOSE?

### Memory limit OOMKilled
- Aumenta `deploy.resources.limits.memory` no compose
- Verifica `docker stats <container>`

## Refs

- ADR 0042 — Infra empresa padrão
- ADR 0053 — MCP server governança
- ADR 0058 — Centrifugo (realtime)
- ADR 0060 — Opção C híbrida (este split de responsabilidades)
- Auto-mem `reference_proxmox_acesso_2026_04_29.md` — receita acesso CT
